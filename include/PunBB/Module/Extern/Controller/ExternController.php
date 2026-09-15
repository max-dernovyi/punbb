<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Controller;

use PunBB\Module\Extern\Api\Data\FeedEntryInterface;
use PunBB\Module\Extern\Api\Data\FeedItemInterface;
use PunBB\Module\Extern\Api\SyndicationInterface;
use PunBB\Module\Extern\Authentication\BasicAuthenticationInterface;
use PunBB\Module\Extern\Event\ExternActionRequested;
use PunBB\Module\Extern\Event\ExternRequested;
use PunBB\Module\Extern\Event\FeedAssembling;
use PunBB\Module\Extern\Event\FeedRendering;
use PunBB\Module\Extern\Event\FeedRequested;
use PunBB\Module\Extern\Event\OnlineListAssembling;
use PunBB\Module\Extern\Event\StatisticsShowing;
use PunBB\Module\Extern\Model\FeedItem;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * extern.php: the board syndicated to other sites. action=feed writes the
 * recent topics, or a topic's recent posts, as RSS, Atom, XML or a list of
 * links; online and online_full write who is online; stats the board's
 * figures. A feed reader may sign in with HTTP Basic authentication.
 */
final class ExternController implements ControllerInterface {
	/** How many items a feed carries unless it is asked for between 1 and MAX_ITEMS. */
	public const DEFAULT_ITEMS = 15;

	public const MAX_ITEMS = 50;

	/** The length a subject is cut at in a list of links, unless FORUM_EXTERN_MAX_SUBJECT_LENGTH says otherwise. */
	public const SUBJECT_LENGTH = 30;

	private const TEMPLATES = __DIR__.'/../templates/';

	/** The type each format is sent as. */
	private const CONTENT_TYPES = array(
		'rss'	=> 'text/xml; charset=utf-8',
		'atom'	=> 'text/xml; charset=utf-8',
		'xml'	=> 'application/xml; charset=utf-8',
		'html'	=> 'text/html; charset=utf-8',
	);

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly TemplateRenderer $templates,
		private readonly SyndicationInterface $syndication,
		private readonly BasicAuthenticationInterface $authentication,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ExternRequested());

		if ($this->visitor->isGuest() && $request->authUser !== null)
			$this->authentication->authenticate($request->authUser, $request->authPassword ?? '');

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->refuse($this->language->text('common', 'No view'));

		$action = $request->query['action'] ?? 'feed';

		return match ($action) {
			'feed'					=> $this->feed($request),
			'online', 'online_full'	=> $this->online($action === 'online_full'),
			'stats'					=> $this->statistics(),
			default					=> $this->unknown(is_scalar($action) ? (string) $action : ''),
		};
	}

	/** $message, with a guest asked to sign in. */
	private function refuse(Html $message): Response {
		if (!$this->visitor->isGuest())
			return new Response($message->html);

		return new Response($message->html, 401, array('WWW-Authenticate' => 'Basic realm="'.$this->settings->value('o_board_title').' External Syndication"'));
	}

	private function unknown(string $action): Response {
		$this->events->dispatch(new ExternActionRequested($action));

		return new Response($this->language->text('common', 'Bad request')->html);
	}

	private function feed(Request $request): Response {
		$type = $request->query['type'] ?? null;
		$show = isset($request->query['show']) && is_scalar($request->query['show']) ? intval($request->query['show']) : self::DEFAULT_ITEMS;

		$requested = new FeedRequested(is_string($type) && in_array($type, FeedRequested::TYPES, true) ? $type : 'html', $show < 1 || $show > self::MAX_ITEMS ? self::DEFAULT_ITEMS : $show);
		$this->events->dispatch($requested);

		$assembled = isset($request->query['tid'])
			? $this->postsFeed(is_scalar($request->query['tid']) ? intval($request->query['tid']) : 0, $requested->count())
			: $this->topicsFeed($request, $requested->count());

		if ($assembled === null)
			return $this->refuse($this->language->text('common', 'Bad request'));

		return $this->write($requested->type(), $assembled);
	}

	private function postsFeed(int $topicId, int $count): ?FeedAssembling {
		$topic = $this->syndication->topic($topicId, $this->visitor->groupId());
		if ($topic === null)
			return null;

		$censoring = $this->settings->enabled('o_censoring');
		$subject = $censoring ? $this->formatter->censor($topic->subject()) : $topic->subject();

		$feed = new FeedAssembling(FeedAssembling::COMPLETE, FeedAssembling::POSTS,
			$this->settings->value('o_board_title').$this->language->text('common', 'Title separator')->html.$subject,
			$this->urls->link('topic', array($topicId, $this->urls->slug($subject)))->html,
			sprintf($this->language->text('common', 'RSS description topic')->html, $subject),
			array());

		foreach ($this->syndication->posts($topicId, $count) as $post)
		{
			$title = $topic->firstPostId() === $post->id() ? $subject : $this->language->text('common', 'RSS reply')->html.$subject;
			$feed = $this->added($feed, $post, $title, $this->urls->link('post', array($post->id()))->html, $censoring);
		}

		return $this->assembled($feed);
	}

	private function topicsFeed(Request $request, int $count): FeedAssembling {
		$forumName = '';
		$forumIds = array();
		$excluding = false;

		$included = $request->query['fid'] ?? null;
		if (is_scalar($included) && $included !== '')
		{
			$forumIds = array_map(intval(...), explode(',', trim((string) $included)));

			if (count($forumIds) === 1)
			{
				$name = $this->syndication->forumName($forumIds[0], $this->visitor->groupId());
				if ($name !== null)
					$forumName = $this->language->text('common', 'Title separator')->html.$name;
			}
		}

		// Excluding forums overrides including them, as it always has
		$excluded = $request->query['nfid'] ?? null;
		if (is_scalar($excluded) && $excluded !== '')
		{
			$forumIds = array_map(intval(...), explode(',', trim((string) $excluded)));
			$excluding = true;
		}

		$board = $this->settings->value('o_board_title');
		$feed = new FeedAssembling(FeedAssembling::COMPLETE, FeedAssembling::TOPICS, $board.$forumName, $this->urls->link('index')->html,
			sprintf($this->language->text('common', 'RSS description')->html, $board), array());

		$censoring = $this->settings->enabled('o_censoring');
		foreach ($this->syndication->topics($this->visitor->groupId(), $forumIds, $excluding, ($request->query['sort'] ?? null) === 'last_post', $count) as $topic)
		{
			$subject = $censoring ? $this->formatter->censor($topic->subject()) : $topic->subject();
			$feed = $this->added($feed, $topic, $subject, $this->urls->link('topic_new_posts', array($topic->id(), $this->urls->slug($subject)))->html, $censoring);
		}

		return $this->assembled($feed);
	}

	/** $feed with an item for $entry added, as the observers of the addition leave it. */
	private function added(FeedAssembling $feed, FeedEntryInterface $entry, string $title, string $link, bool $censoring): FeedAssembling {
		$message = $censoring ? $this->formatter->censor($entry->message()) : $entry->message();

		$email = null;
		$uri = null;
		if ($entry->posterId() > 1)
		{
			if ($entry->showsEmail() && !$this->visitor->isGuest())
				$email = $entry->accountEmail();

			$uri = $this->urls->link('user', array($entry->posterId()))->html;
		}
		else if ($entry->guestEmail() !== '' && !$this->visitor->isGuest())
			$email = $entry->guestEmail();

		$items = $feed->items();
		$items[] = new FeedItem($entry->id(), $title, $link, $this->formatter->message($message, $entry->hidesSmilies())->html, $entry->poster(), $email, $uri, $entry->posted());

		$added = new FeedAssembling(FeedAssembling::ITEM, $feed->kind(), $feed->title(), $feed->link(), $feed->description(), $items, $entry);
		$this->events->dispatch($added);

		return $added;
	}

	private function assembled(FeedAssembling $feed): FeedAssembling {
		$complete = new FeedAssembling(FeedAssembling::COMPLETE, $feed->kind(), $feed->title(), $feed->link(), $feed->description(), $feed->items());
		$this->events->dispatch($complete);

		return $complete;
	}

	private function write(string $type, FeedAssembling $feed): Response {
		$items = $feed->items();
		$variables = array('items' => array());

		if ($type === 'html')
		{
			$limit = defined('FORUM_EXTERN_MAX_SUBJECT_LENGTH') ? constant('FORUM_EXTERN_MAX_SUBJECT_LENGTH') : null;
			$length = is_numeric($limit) ? (int) $limit : self::SUBJECT_LENGTH;

			foreach ($items as $item)
				$variables['items'][] = array(
					'link'		=> new Html($item->link()),
					'title'		=> $item->title(),
					'subject'	=> mb_strlen($item->title()) > $length ? (new Html(mb_substr($item->title(), 0, $length - 5)))->trim()->html.'…' : $item->title(),
				);
		}
		else
		{
			$position = array('rss' => FeedRendering::RSS_INFO, 'atom' => FeedRendering::ATOM_INFO, 'xml' => FeedRendering::XML_INFO)[$type];
			$itemPosition = array('rss' => FeedRendering::RSS_ITEM, 'atom' => FeedRendering::ATOM_ITEM, 'xml' => FeedRendering::XML_ITEM)[$type];

			$info = new FeedRendering($position, $feed->kind(), $feed->title(), $feed->link(), $feed->description(), $items);
			$this->events->dispatch($info);

			foreach ($items as $item)
			{
				$rendering = new FeedRendering($itemPosition, $feed->kind(), $feed->title(), $feed->link(), $feed->description(), $items, $item);
				$this->events->dispatch($rendering);

				$variables['items'][] = self::item($item) + array('info' => new Html($rendering->markup()));
			}

			$built = $items !== array() ? $items[0]->published() : time();

			$variables += array(
				'title'			=> self::cdata($feed->title()),
				'link'			=> new Html($feed->link()),
				'self'			=> $this->urls->current(),
				'description'	=> self::cdata($feed->description()),
				'built'			=> $built,
				'version'		=> $this->settings->enabled('o_show_version') ? $this->settings->value('o_cur_version') : null,
				'info'			=> new Html($info->markup()),
				'posts'			=> $feed->kind() === FeedAssembling::POSTS,
			);
		}

		return new Response($this->templates->render(self::TEMPLATES.$type.'.phtml', $variables), 200, array(
			'Content-Type'	=> self::CONTENT_TYPES[$type],
			'Expires'		=> gmdate('D, d M Y H:i:s').' GMT',
			'Cache-Control'	=> 'must-revalidate, post-check=0, pre-check=0',
			'Pragma'		=> 'public',
		));
	}

	/** @return array<string, mixed> what an XML format writes of $item */
	private static function item(FeedItemInterface $item): array {
		return array(
			'id'			=> $item->id(),
			'title'			=> self::cdata($item->title()),
			'link'			=> new Html($item->link()),
			'description'	=> self::cdata($item->description()),
			'name'			=> self::cdata($item->authorName()),
			'email'			=> $item->authorEmail() !== null ? self::cdata($item->authorEmail()) : null,
			'uri'			=> $item->authorUri() !== null ? new Html($item->authorUri()) : null,
			'published'		=> $item->published(),
		);
	}

	/** $text for the inside of a CDATA section, which only its own end can close. */
	private static function cdata(string $text): Html {
		return new Html(str_replace(']]>', ']]&gt;', $text));
	}

	private function online(bool $full): Response {
		$guests = 0;
		$members = array();

		foreach ($this->syndication->onlineVisitors() as $visitor)
		{
			if ($visitor->isGuest())
				++$guests;
			else
				$members[(string) count($members)] = $this->visitor->can(GroupPermission::ViewUsers)
					? Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($visitor->userId())), $visitor->ident())->html
					: Html::escape($visitor->ident())->html;
		}

		$list = new OnlineListAssembling($guests, count($members), $members, $full);
		$this->events->dispatch($list);

		$links = array();
		foreach ($list->names() as $name)
			$links[] = (string) $list->entry($name);

		$strings = $this->language->strings('index');

		return new Response($this->templates->render(self::TEMPLATES.'online.phtml', array(
			'guestsLabel'	=> self::string($strings, 'Guests online'),
			'guests'		=> $this->formatter->number($list->guests()),
			'membersLabel'	=> self::string($strings, 'Users online'),
			'members'		=> $full && $links !== array() ? new Html(implode(self::string($strings, 'Online list separator')->html, $links)) : $this->formatter->number($list->memberCount()),
		)), 200, self::headers());
	}

	private function statistics(): Response {
		$showing = new StatisticsShowing($this->syndication->statistics());
		$this->events->dispatch($showing);

		$statistics = $showing->statistics();
		$strings = $this->language->strings('index');

		return new Response($this->templates->render(self::TEMPLATES.'stats.phtml', array(
			'users'		=> Html::format(self::string($strings, 'No of users'), $this->formatter->number($statistics->userCount())),
			'newest'	=> Html::format(self::string($strings, 'Newest user'), Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($statistics->newestUserId())), $statistics->newestUsername())),
			'topics'	=> Html::format(self::string($strings, 'No of topics'), $this->formatter->number($statistics->topicCount())),
			'posts'		=> Html::format(self::string($strings, 'No of posts'), $this->formatter->number($statistics->postCount())),
		)), 200, self::headers());
	}

	/** @return array<string, string> what a list of links, online or statistics are sent with */
	private static function headers(): array {
		return array(
			'Content-type'	=> 'text/html; charset=utf-8',
			'Expires'		=> gmdate('D, d M Y H:i:s').' GMT',
			'Cache-Control'	=> 'must-revalidate, post-check=0, pre-check=0',
			'Pragma'		=> 'public',
		);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
