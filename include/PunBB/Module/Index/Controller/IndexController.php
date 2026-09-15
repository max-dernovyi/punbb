<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Index\Event\CategoryHeadAssembling;
use PunBB\Module\Index\Event\ForumRowAssembling;
use PunBB\Module\Index\Event\IndexRendering;
use PunBB\Module\Index\Event\IndexRequested;
use PunBB\Module\Index\Event\OnlineInfoAssembling;
use PunBB\Module\Index\Event\OnlineVisitorListing;
use PunBB\Module\Index\Event\StatisticsAssembling;
use PunBB\Module\Index\View\ForumRow;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * index.php: the categories and forums the visitor may read, which of them
 * have posts they have not read, the board's statistics and who is online.
 */
final class IndexController implements ControllerInterface {
	private const FORUMS = __DIR__.'/../templates/forums.phtml';

	private const INFO = __DIR__.'/../templates/info.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly BoardIndexInterface $board,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new IndexRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		$strings = $this->language->strings('index');

		/** @var array<int, array<int, int>> $activeTopics forum id => topic id => its last post */
		$activeTopics = array();
		$tracked = new TrackedTopics();

		if (!$this->visitor->isGuest())
		{
			foreach ($this->board->activeTopics($this->visitor->groupId(), $this->visitor->lastVisit()) as $topic)
				$activeTopics[$topic->forumId()][$topic->topicId()] = $topic->lastPost();

			$tracked = $this->visitor->trackedTopics();
		}

		$head = new PageHead('index', array(), indexable: true, mainTitle: Html::escape($this->settings->value('o_board_title')));

		return $this->pages->respond($head, fn (): array => array(
			'main'	=> $this->forums($strings, $activeTopics, $tracked),
			'info'	=> $this->info($strings),
		));
	}

	/**
	 * @param array<string, Html> $strings
	 * @param array<int, array<int, int>> $activeTopics
	 */
	private function forums(array $strings, array $activeTopics, TrackedTopics $tracked): Html {
		$start = $this->position(IndexRendering::MAIN_OUTPUT_START);

		$items = array();
		$category = 0;
		$categoryCount = 0;
		$itemCount = 0;

		foreach ($this->board->forums($this->visitor->groupId()) as $forum)
		{
			$row = new ForumRow();
			$loop = $this->stage(ForumRowAssembling::START, $forum, $row, $itemCount);
			$before = $loop->markup();
			$itemCount = $loop->itemCount() + 1;

			if ($forum->categoryId() !== $category)
			{
				++$categoryCount;
				$itemCount = 1;

				$head = new CategoryHeadAssembling($forum, $categoryCount,
					new Parts(array('title' => Html::format('<strong class="subject-title">%s</strong>', self::string($strings, 'Forums'))->html)),
					new Parts(array(
						'topics'	=> Html::format('<strong class="info-topics">%s</strong>', self::string($strings, 'topics'))->html,
						'post'		=> Html::format('<strong class="info-posts">%s</strong>', self::string($strings, 'posts'))->html,
						'lastpost'	=> Html::format('<strong class="info-lastpost">%s</strong>', self::string($strings, 'last post'))->html,
					)));
				$this->events->dispatch($head);

				$items[] = array(
					'category'	=> true,
					'start'		=> new Html($before),
					'closes'	=> $category !== 0,
					'before'	=> new Html($head->markup()),
					'name'		=> $forum->categoryName(),
					'summary'	=> Html::format(self::string($strings, 'Category subtitle'), self::joined($head, CategoryHeadAssembling::SUBJECT, ' '), self::joined($head, CategoryHeadAssembling::INFO, ', ')),
					'number'	=> $categoryCount,
				);

				$category = $forum->categoryId();
				$before = '';
			}

			[$markup, $itemCount] = $forum->redirectUrl() !== ''
				? $this->redirectRow($forum, $row, $strings, $itemCount)
				: $this->forumRow($forum, $row, $strings, $itemCount, $activeTopics, $tracked);

			$row->setStyle((($itemCount % 2 !== 0) ? ' odd' : ' even').($itemCount === 1 ? ' main-first-item' : '').(!$row->status->isEmpty() ? ' '.$row->status->join(' ') : ''));

			$display = $this->stage(ForumRowAssembling::ROW, $forum, $row, $itemCount);
			$itemCount = $display->itemCount();

			$items[] = array(
				'category'	=> false,
				'before'	=> new Html($before.$markup.$display->markup()),
				'id'		=> $forum->id(),
				'style'		=> $row->style(),
				'status'	=> $row->status->join(' '),
				'subject'	=> new Html($row->bodySubject->join("\n\t\t\t\t")),
				'info'		=> new Html($row->bodyInfo->join("\n\t\t\t\t")),
			);
		}

		$body = $this->templates->render(self::FORUMS, array(
			'items'		=> $items,
			'message'	=> $this->language->text('common', 'Forum message'),
			'empty'		=> self::string($strings, 'Empty board'),
		));

		return (new Html($start.$body.$this->position(IndexRendering::END)))->trim();
	}

	/**
	 * @param array<string, Html> $strings
	 * @return array{string, int} the markup the stages added, and the row count they left
	 */
	private function redirectRow(ForumInterface $forum, ForumRow $row, array $strings, int $itemCount): array {
		$row->bodySubject->set('title', Html::format('<h3 class="hn"><a class="external" href="%s" title="%s"><span>%s</span></a></h3>',
			$forum->redirectUrl(), Html::format(self::string($strings, 'Link to'), $forum->redirectUrl()), $forum->name())->html);
		$row->status->set('redirect', 'redirect');

		if ($forum->description() !== '')
			$row->subject->set('desc', $forum->description());

		$row->subject->set('redirect', Html::format('<span>%s</span>', self::string($strings, 'External forum'))->html);

		$subject = $this->stage(ForumRowAssembling::REDIRECT_SUBJECT, $forum, $row, $itemCount);

		if (!$row->subject->isEmpty())
			$row->bodySubject->set('desc', '<p>'.$row->subject->join(' ').'</p>');

		$row->bodyInfo->set('topics', Html::format('<li class="info-topics"><span class="label">%s</span></li>', self::string($strings, 'No topic info'))->html);
		$row->bodyInfo->set('posts', Html::format('<li class="info-posts"><span class="label">%s</span></li>', self::string($strings, 'No post info'))->html);
		$row->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span></li>', self::string($strings, 'No lastpost info'))->html);

		$body = $this->stage(ForumRowAssembling::REDIRECT_BODY, $forum, $row, $subject->itemCount());

		return array($subject->markup().$body->markup(), $body->itemCount());
	}

	/**
	 * @param array<string, Html> $strings
	 * @param array<int, array<int, int>> $activeTopics
	 * @return array{string, int} the markup the stages added, and the row count they left
	 */
	private function forumRow(ForumInterface $forum, ForumRow $row, array $strings, int $itemCount, array $activeTopics, TrackedTopics $tracked): array {
		$markup = '';

		$row->title->set('title', Html::format('<a href="%s"><span>%s</span></a>', $this->urls->link('forum', array($forum->id(), $this->urls->slug($forum->name()))), $forum->name())->html);

		if ($this->hasUnread($forum, $activeTopics, $tracked))
		{
			$row->status->set('new', 'new');
			$row->title->set('status', Html::format('<small>%s</small>', Html::format(self::string($strings, 'Forum has new'),
				Html::format('<a href="%s" title="%s">%s</a>', $this->urls->link('search_new_results', array($forum->id())), self::string($strings, 'New posts title'), self::string($strings, 'Forum new posts'))))->html);
		}

		$title = $this->stage(ForumRowAssembling::TITLE, $forum, $row, $itemCount);
		$markup .= $title->markup();
		$itemCount = $title->itemCount();

		$row->bodySubject->set('title', '<h3 class="hn">'.$row->title->join(' ').'</h3>');

		if ($forum->description() !== '')
			$row->subject->set('desc', $forum->description());

		if ($this->settings->enabled('o_show_moderators') && $forum->moderators() !== array())
		{
			$number = 0;
			foreach ($forum->moderators() as $moderator)
				$row->moderators->set((string) $number++, $this->visitor->can(GroupPermission::ViewUsers)
					? Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($moderator->userId())), $moderator->username())->html
					: Html::escape($moderator->username())->html);

			$moderators = $this->stage(ForumRowAssembling::MODERATORS, $forum, $row, $itemCount);
			$markup .= $moderators->markup();
			$itemCount = $moderators->itemCount();

			$row->subject->set('modlist', Html::format('<span class="modlist">%s</span>', Html::format(self::string($strings, 'Moderated by'), new Html($row->moderators->join(', '))))->html);
		}

		$subject = $this->stage(ForumRowAssembling::SUBJECT, $forum, $row, $itemCount);
		$markup .= $subject->markup();
		$itemCount = $subject->itemCount();

		if (!$row->subject->isEmpty())
			$row->bodySubject->set('desc', '<p>'.$row->subject->join(' ').'</p>');

		$row->bodyInfo->set('topics', Html::format('<li class="info-topics"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($forum->topicCount()), self::string($strings, $forum->topicCount() === 1 ? 'topic' : 'topics'))->html);
		$row->bodyInfo->set('posts', Html::format('<li class="info-posts"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($forum->postCount()), self::string($strings, $forum->postCount() === 1 ? 'post' : 'posts'))->html);

		$lastPost = $forum->lastPost();
		$row->bodyInfo->set('lastpost', $lastPost !== null
			? Html::format('<li class="info-lastpost"><span class="label">%s</span> <strong><a href="%s">%s</a></strong> <cite>%s</cite></li>', self::string($strings, 'Last post'),
				$this->urls->link('post', array($forum->lastPostId() ?? 0)), $this->formatter->time($lastPost, TimeFormat::DateTime), Html::format(self::string($strings, 'Last poster'), $forum->lastPoster() ?? ''))->html
			: Html::format('<li class="info-lastpost"><strong>%s</strong></li>', $this->language->text('common', 'Never'))->html);

		$body = $this->stage(ForumRowAssembling::BODY, $forum, $row, $itemCount);

		return array($markup.$body->markup(), $body->itemCount());
	}

	/**
	 * Whether the forum has a post since the visitor's last visit that they
	 * have not read, in a topic they have not read since, and since they
	 * marked the forum read.
	 *
	 * @param array<int, array<int, int>> $activeTopics
	 */
	private function hasUnread(ForumInterface $forum, array $activeTopics, TrackedTopics $tracked): bool {
		$lastPost = $forum->lastPost();
		$markedRead = $tracked->forum($forum->id());

		if ($this->visitor->isGuest() || $lastPost === null || $lastPost <= $this->visitor->lastVisit() || ($markedRead !== 0 && $lastPost <= $markedRead))
			return false;

		foreach ($activeTopics[$forum->id()] ?? array() as $topicId => $topicLastPost)
		{
			$read = $tracked->topic($topicId);

			if (($read === 0 || $read < $topicLastPost) && ($markedRead === 0 || $markedRead < $topicLastPost))
				return true;
		}

		return false;
	}

	/** @param array<string, Html> $strings */
	private function info(array $strings): Html {
		$before = $this->position(IndexRendering::INFO_OUTPUT_START);

		$statistics = $this->board->statistics();
		$newest = $this->visitor->can(GroupPermission::ViewUsers)
			? Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($statistics->newestUserId())), $statistics->newestUsername())
			: Html::escape($statistics->newestUsername());

		$lines = new StatisticsAssembling($statistics, array(
			'no_of_users'	=> $this->statisticsLine('st-users', self::string($strings, 'No of users'), $this->formatter->number($statistics->userCount())),
			'newest_user'	=> $this->statisticsLine('st-users', self::string($strings, 'Newest user'), $newest),
			'no_of_topics'	=> $this->statisticsLine('st-activity', self::string($strings, 'No of topics'), $this->formatter->number($statistics->topicCount())),
			'no_of_posts'	=> $this->statisticsLine('st-activity', self::string($strings, 'No of posts'), $this->formatter->number($statistics->postCount())),
		));
		$this->events->dispatch($lines);
		$before .= $lines->markup();

		$entries = array();
		foreach ($lines->names() as $name)
			$entries[] = (string) $lines->entry($name);

		$between = $this->position(IndexRendering::STATS_END).$this->position(IndexRendering::USERS_ONLINE_START);

		$online = $this->settings->enabled('o_users_online');
		$counts = new Html('');
		$members = null;
		$newOnlineData = '';
		$after = '';

		if ($online)
		{
			$names = new Parts();
			$guests = 0;
			$number = 0;

			foreach ($this->board->onlineVisitors() as $visitor)
			{
				$listing = new OnlineVisitorListing($visitor);
				$this->events->dispatch($listing);
				$between .= $listing->markup();

				if ($visitor->isGuest())
					++$guests;
				else
					$names->set((string) $number++, $this->visitor->can(GroupPermission::ViewUsers)
						? Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($visitor->userId())), $visitor->ident())->html
						: Html::escape($visitor->ident())->html);
			}

			$info = new OnlineInfoAssembling(new Parts(array(
				'guests'	=> $this->onlineCount($strings, 'Guests', $guests),
				'users'		=> $this->onlineCount($strings, 'Users', $number),
			)), $names, $guests, $number);
			$this->events->dispatch($info);
			$between .= $info->markup();

			$counts = Html::format(self::string($strings, 'Currently online'), self::joined($info, OnlineInfoAssembling::COUNTS, self::string($strings, 'Online stats separator')->html));
			$members = $info->names(OnlineInfoAssembling::MEMBERS) !== array() ? self::joined($info, OnlineInfoAssembling::MEMBERS, self::string($strings, 'Online list separator')->html) : null;
			$newOnlineData = $this->position(IndexRendering::NEW_ONLINE_DATA);
			$after = $this->position(IndexRendering::USERS_ONLINE_END);
		}

		$after .= $this->position(IndexRendering::INFO_END);

		return (new Html($this->templates->render(self::INFO, array(
			'before'		=> new Html($before),
			'statistics'	=> self::string($strings, 'Statistics'),
			'lines'			=> new Html(implode("\n\t\t", $entries)),
			'between'		=> new Html($between),
			'online'		=> $online,
			'counts'		=> $counts,
			'members'		=> $members,
			'newOnlineData'	=> new Html($newOnlineData),
			'after'			=> new Html($after),
		))))->trim();
	}

	private function statisticsLine(string $class, Html $label, Html $figure): string {
		return Html::format('<li class="%s"><span>%s</span></li>', $class, Html::format($label, Html::format('<strong>%s</strong>', $figure)))->html;
	}

	/** @param array<string, Html> $strings */
	private function onlineCount(array $strings, string $who, int $count): string {
		return match ($count) {
			0		=> self::string($strings, $who.' none')->html,
			1		=> Html::format(self::string($strings, $who.' single'), $this->formatter->number($count))->html,
			default	=> Html::format(self::string($strings, $who.' plural'), $this->formatter->number($count))->html,
		};
	}

	private function stage(string $stage, ForumInterface $forum, ForumRow $row, int $itemCount): ForumRowAssembling {
		$event = new ForumRowAssembling($stage, $forum, $row, $itemCount);
		$this->events->dispatch($event);

		return $event;
	}

	private function position(string $position): string {
		$event = new IndexRendering($position);
		$this->events->dispatch($event);

		return $event->markup();
	}

	/** The parts of $group an event carries, joined with $glue. */
	private static function joined(CategoryHeadAssembling|OnlineInfoAssembling $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
