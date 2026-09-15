<?php

declare(strict_types=1);

namespace PunBB\Module\Search\View;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Event\ForumResultAssembling;
use PunBB\Module\Search\Event\PostResultAssembling;
use PunBB\Module\Search\Event\ResultRowStarting;
use PunBB\Module\Search\Event\TopicResultAssembling;
use PunBB\Module\Search\Event\TopicResultsHeadAssembling;
use PunBB\Module\Search\Api\Data\ListingInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The results on a page as the templates show them: each post, topic or forum
 * built stage by stage as its observers change it, counted as it is placed.
 */
final class ResultRows {
	public function __construct(
		private readonly EventDispatcher $events,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter
	) {}

	/**
	 * @param list<mixed> $page
	 * @param array<string, Html> $search
	 * @return list<array<string, mixed>>
	 */
	public function posts(ListingInterface $listing, Paging $paging, array $page, array $search): array {
		$topic = $this->language->strings('topic');
		$rows = array();
		$itemCount = 0;

		foreach ($page as $post)
		{
			if (!$post instanceof ResultPostInterface)
				continue;

			[$before, $itemCount] = $this->start($listing, $post, $itemCount);
			$result = new PostResult();
			$subject = $this->settings->enabled('o_censoring') ? $this->formatter->censor($post->subject()) : $post->subject();
			$isTopic = $post->id() === $post->firstPostId();

			$at = function (string $stage) use ($post, $subject, $paging, $result, &$itemCount): PostResultAssembling {
				$event = new PostResultAssembling($stage, $post, $subject, $paging->offset + $itemCount, $result, $itemCount);
				$this->events->dispatch($event);
				$itemCount = $event->itemCount();

				return $event;
			};

			$postLink = $this->urls->link('post', array($post->id()));
			$result->ident->set('num', Html::format('<span class="post-num">%s</span>', $this->formatter->number($paging->offset + $itemCount))->html);
			$result->ident->set('byline', Html::format('<span class="post-byline">%s</span>', Html::format(self::string($topic, $isTopic ? 'Topic byline' : 'Reply byline'), Html::format('<strong>%s</strong>', $post->poster())))->html);
			$result->ident->set('link', Html::format('<span class="post-link"><a class="permalink" rel="bookmark" title="%s" href="%s">%s</a></span>', self::string($topic, 'Permalink post'), $postLink, $this->formatter->time($post->posted(), TimeFormat::DateTime))->html);

			$before .= $at(PostResultAssembling::IDENT)->markup();

			$topicLink = $this->urls->link('topic', array($post->topicId(), $this->urls->slug($subject)));
			$forumLink = $this->urls->link('forum', array($post->forumId(), $this->urls->slug($post->forumName())));

			$result->subject = Html::format('<a class="permalink" rel="bookmark" title="%s" href="%s">%s</a> <small>%s</small>', self::string($topic, 'Permalink topic'), $topicLink,
				Html::format(self::string($topic, $isTopic ? 'Topic title' : 'Reply title'), $subject),
				Html::format(self::string($topic, 'Search replies'), $this->formatter->number($post->replyCount()), Html::format('<a href="%s">%s</a>', $forumLink, $post->forumName())))->html;

			$result->author = $post->posterId() > 1 && $this->visitor->can(GroupPermission::ViewUsers)
				? Html::format('<strong class="username"><a title="%s" href="%s">%s</a></strong>', Html::format(self::string($search, 'Go to profile'), $post->poster()), $this->urls->link('user', array($post->posterId())), $post->poster())->html
				: Html::format('<strong class="username">%s</strong>', $post->poster())->html;

			$result->actions->set('forum', Html::format('<span><a href="%s">%s<span>: %s</span></a></span>', $forumLink, self::string($search, 'Go to forum'), $post->forumName())->html);
			if (!$isTopic)
				$result->actions->set('topic', Html::format('<span><a class="permalink" rel="bookmark" title="%s" href="%s">%s<span>: %s</span></a></span>', self::string($topic, 'Permalink topic'), $topicLink, self::string($search, 'Go to topic'), $subject)->html);
			$result->actions->set('post', Html::format('<span><a class="permalink" rel="bookmark" title="%s" href="%s">%s<span> %s</span></a></span>', self::string($topic, 'Permalink post'), $postLink, self::string($search, 'Go to post'), $this->formatter->number($paging->offset + $itemCount))->html);

			$result->message = $this->formatter->message($post->message(), $post->hidesSmilies())->html;

			$result->status->set('0', 'post');
			$result->status->set('1', $itemCount % 2 !== 0 ? 'odd' : 'even');
			if ($itemCount === 1)
				$result->status->set('firstpost', 'firstpost');
			if ($paging->offset + $itemCount === $paging->last)
				$result->status->set('lastpost', 'lastpost');
			if ($isTopic)
				$result->status->set('topicpost', 'topicpost');

			$before .= $at(PostResultAssembling::ROW)->markup();

			$status = $result->status->join(' ');
			$ident = $result->ident->join(' ');
			$entry = $at(PostResultAssembling::ENTRY)->markup();

			$rows[] = array(
				'before'	=> new Html($before),
				'status'	=> new Html($status),
				'ident'		=> new Html($ident),
				'subject'	=> new Html($result->subject),
				'message'	=> new Html($result->message),
				'entry'		=> new Html($entry),
				'actions'	=> new Html($result->actions->join(' ')),
			);
		}

		return $rows;
	}

	/**
	 * @param list<mixed> $page
	 * @param TrackedTopics $tracked the topics and forums the visitor read since their last visit
	 * @param array<string, Html> $search
	 * @return array<string, mixed> the head's markup and summary, and the rows
	 */
	public function topics(ListingInterface $listing, Paging $paging, array $page, TrackedTopics $tracked, array $search): array {
		$forum = $this->language->strings('forum');

		$head = new TopicResultsHeadAssembling(
			new Parts(array('title' => Html::format('<strong class="subject-title">%s</strong>', self::string($forum, 'Topics'))->html)),
			new Parts(array(
				'forum'		=> Html::format('<strong class="info-forum">%s</strong>', self::string($forum, 'Forum'))->html,
				'replies'	=> Html::format('<strong class="info-replies">%s</strong>', self::string($forum, 'replies'))->html,
				'lastpost'	=> Html::format('<strong class="info-lastpost">%s</strong>', self::string($forum, 'last post'))->html,
			))
		);
		$this->events->dispatch($head);

		$rows = array();
		$itemCount = 0;

		foreach ($page as $topic)
			if ($topic instanceof ResultTopicInterface)
				[$rows[], $itemCount] = $this->topic($listing, $paging, $topic, $tracked, $itemCount, $forum, $search);

		return array(
			'headBefore'	=> new Html($head->markup()),
			'summary'		=> Html::format(self::string($forum, 'Search subtitle'), self::joined($head, TopicResultsHeadAssembling::SUBJECT, ' '), self::joined($head, TopicResultsHeadAssembling::INFO, ', ')),
			'rows'			=> $rows,
		);
	}

	/**
	 * @param list<mixed> $page
	 * @return list<array<string, mixed>>
	 */
	public function forums(ListingInterface $listing, array $page): array {
		$index = $this->language->strings('index');
		$rows = array();
		$itemCount = 0;
		$category = 0;
		$categoryCount = 0;

		foreach ($page as $forum)
		{
			if (!$forum instanceof ResultForumInterface)
				continue;

			[$before, $itemCount] = $this->start($listing, $forum, $itemCount);
			$result = new ForumResult();
			$row = array('opens' => false, 'closes' => false, 'headBefore' => new Html(''), 'category' => '', 'summary' => new Html(''), 'categoryNumber' => 0);

			$at = function (string $stage) use ($forum, $result, &$itemCount, &$categoryCount): string {
				$event = new ForumResultAssembling($stage, $forum, $result, $itemCount, $categoryCount);
				$this->events->dispatch($event);
				$itemCount = $event->itemCount();

				return $event->markup();
			};

			if ($forum->categoryId() !== $category)
			{
				$row['closes'] = $category !== 0;
				++$categoryCount;
				$itemCount = 1;

				$result->headerSubject->set('title', Html::format('<strong class="subject-title">%s</strong>', self::string($index, 'Forums'))->html);
				$result->headerInfo->set('topics', Html::format('<strong class="info-topics">%s</strong>', self::string($index, 'topics'))->html);
				$result->headerInfo->set('post', Html::format('<strong class="info-posts">%s</strong>', self::string($index, 'posts'))->html);
				$result->headerInfo->set('lastpost', Html::format('<strong class="info-lastpost">%s</strong>', self::string($index, 'last post'))->html);

				$headBefore = $at(ForumResultAssembling::CATEGORY_HEAD);
				$category = $forum->categoryId();

				$row = array(
					'opens'				=> true,
					'closes'			=> $row['closes'],
					'headBefore'		=> new Html($headBefore),
					'category'			=> $forum->categoryName(),
					'summary'			=> Html::format(self::string($index, 'Category subtitle'), new Html($result->headerSubject->join(' ')), new Html($result->headerInfo->join(', '))),
					'categoryNumber'	=> $categoryCount,
				);
			}

			$rowBefore = '';

			if ($forum->redirectUrl() !== '')
			{
				$result->bodySubject->set('title', Html::format('<h3 class="hn"><a class="external" href="%s" title="%s"><span>%s</span></a></h3>',
					$forum->redirectUrl(), Html::format(self::string($index, 'Link to'), $forum->redirectUrl()), $forum->name())->html);
				$result->status->set('redirect', 'redirect');

				if ($forum->description() !== '')
					$result->subject->set('desc', $forum->description());

				$result->subject->set('redirect', Html::format('<span>%s</span>', self::string($index, 'External forum'))->html);

				$rowBefore .= $at(ForumResultAssembling::REDIRECT_SUBJECT);

				if (!$result->subject->isEmpty())
					$result->bodySubject->set('desc', '<p>'.$result->subject->join(' ').'</p>');

				$result->bodyInfo->set('topics', Html::format('<li class="info-topics"><span class="label">%s</span></li>', self::string($index, 'No topic info'))->html);
				$result->bodyInfo->set('posts', Html::format('<li class="info-posts"><span class="label">%s</span></li>', self::string($index, 'No post info'))->html);
				$result->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span></li>', self::string($index, 'No lastpost info'))->html);

				$rowBefore .= $at(ForumResultAssembling::REDIRECT_BODY);
			}
			else
			{
				$result->title->set('title', Html::format('<a href="%s"><span>%s</span></a>', $this->urls->link('forum', array($forum->id(), $this->urls->slug($forum->name()))), $forum->name())->html);

				$rowBefore .= $at(ForumResultAssembling::TITLE);

				$result->bodySubject->set('title', '<h3 class="hn">'.$result->title->join(' ').'</h3>');

				if ($forum->description() !== '')
					$result->subject->set('desc', $forum->description());

				$rowBefore .= $at(ForumResultAssembling::SUBJECT);

				if (!$result->subject->isEmpty())
					$result->bodySubject->set('desc', '<p>'.$result->subject->join(' ').'</p>');

				$result->bodyInfo->set('topics', Html::format('<li class="info-topics"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($forum->topicCount()), self::string($index, $forum->topicCount() === 1 ? 'topic' : 'topics'))->html);
				$result->bodyInfo->set('posts', Html::format('<li class="info-posts"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($forum->postCount()), self::string($index, $forum->postCount() === 1 ? 'post' : 'posts'))->html);

				if ($forum->lastPost() !== null)
					$result->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span> <strong><a href="%s">%s</a></strong> <cite>%s</cite></li>', self::string($index, 'Last post'),
						$this->urls->link('post', array($forum->lastPostId() ?? 0)), $this->formatter->time($forum->lastPost(), TimeFormat::DateTime), Html::format(self::string($index, 'Last poster'), $forum->lastPoster() ?? ''))->html);
				else
					$result->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><strong>%s</strong></li>', $this->language->text('common', 'Never'))->html);

				$rowBefore .= $at(ForumResultAssembling::BODY);
			}

			$result->style = self::style($itemCount, $result->status);

			$rowBefore .= $at(ForumResultAssembling::ROW);

			$rows[] = $row + array(
				'before'	=> new Html($before),
				'rowBefore'	=> new Html($rowBefore),
				'id'		=> $forum->id(),
				'style'		=> $result->style,
				'status'	=> $result->status->join(' '),
				'subject'	=> new Html($result->bodySubject->join("\n\t\t\t\t")),
				'info'		=> new Html($result->bodyInfo->join("\n\t\t\t\t")),
			);
		}

		return $rows;
	}

	/**
	 * A topic's row, built stage by stage as its observers change it.
	 *
	 * @param array<string, Html> $forum
	 * @param array<string, Html> $search
	 * @return array{array<string, mixed>, int} what the template shows of the row, and the row count
	 */
	private function topic(ListingInterface $listing, Paging $paging, ResultTopicInterface $topic, TrackedTopics $tracked, int $itemCount, array $forum, array $search): array {
		[$before, $itemCount] = $this->start($listing, $topic, $itemCount);
		$result = new TopicResult();
		$subject = $this->settings->enabled('o_censoring') ? $this->formatter->censor($topic->subject()) : $topic->subject();

		$at = function (string $stage) use ($topic, $subject, $paging, $result, &$itemCount, &$before): void {
			$event = new TopicResultAssembling($stage, $topic, $subject, $paging->offset + $itemCount, $result, $itemCount);
			$this->events->dispatch($event);

			$before .= $event->markup();
			$itemCount = $event->itemCount();
		};

		if (!$this->visitor->isGuest() && $this->settings->enabled('o_show_dot') && $topic->hasPosted())
		{
			$result->title->set('posted', Html::format('<span class="posted-mark">%s</span>', self::string($forum, 'You posted indicator'))->html);
			$result->status->set('posted', 'posted');
		}

		if ($topic->isSticky())
		{
			$result->titleStatus->set('sticky', Html::format('<em class="sticky">%s</em>', self::string($forum, 'Sticky'))->html);
			$result->status->set('sticky', 'sticky');
		}

		if ($topic->isClosed())
		{
			$result->titleStatus->set('closed', Html::format('<em class="closed">%s</em>', self::string($forum, 'Closed'))->html);
			$result->status->set('closed', 'closed');
		}

		$at(TopicResultAssembling::TITLE_STATUS);

		if (!$result->titleStatus->isEmpty())
			$result->title->set('status', Html::format('<span class="item-status">%s</span>', Html::format(self::string($forum, 'Item status'), new Html($result->titleStatus->join(', '))))->html);

		$arguments = array($topic->id(), $this->urls->slug($subject));
		$result->title->set('link', Html::format('<a href="%s">%s</a>', $this->urls->link('topic', $arguments), $subject)->html);

		$at(TopicResultAssembling::TITLE);

		$result->bodySubject->set('title', Html::format('<h3 class="hn"><span class="item-num">%s</span> %s</h3>', $this->formatter->number($paging->offset + $itemCount), new Html($result->title->join(' ')))->html);

		$pages = $this->visitor->postsPerPage() > 0 ? (int) ceil(($topic->replyCount() + 1) / $this->visitor->postsPerPage()) : 1;
		if ($pages > 1)
			$result->nav->set('pages', Html::format('<span>%s&#160;</span>%s', self::string($forum, 'Pages'),
				$this->urls->pagination($pages, -1, 'topic', $arguments, $this->language->text('common', 'Page separator')))->html);

		if ($this->hasUnread($topic, $tracked))
		{
			$result->nav->set('new', Html::format('<em class="item-newposts"><a href="%s" title="%s">%s</a></em>', $this->urls->link('topic_new_posts', $arguments), self::string($forum, 'New posts info'), self::string($forum, 'New posts'))->html);
			$result->status->set('new', 'new');
		}

		$at(TopicResultAssembling::NAV);

		$result->subject->set('starter', Html::format('<span class="item-starter">%s</span>', Html::format(self::string($forum, 'Topic starter'), $topic->poster()))->html);

		if (!$result->nav->isEmpty())
			$result->subject->set('nav', Html::format('<span class="item-nav">%s</span>', Html::format(self::string($forum, 'Topic navigation'), new Html($result->nav->join('&#160;&#160;'))))->html);

		$at(TopicResultAssembling::SUBJECT);

		$result->bodySubject->set('desc', '<p>'.$result->subject->join(' ').'</p>');

		if ($result->status->isEmpty())
			$result->status->set('normal', 'normal');

		$at(TopicResultAssembling::STATUS);

		$result->style = self::style($itemCount, $result->status);

		$result->bodyInfo->set('forum', Html::format('<li class="info-forum"><span class="label">%s</span><a href="%s">%s</a></li>', self::string($search, 'Posted in'),
			$this->urls->link('forum', array($topic->forumId(), $this->urls->slug($topic->forumName()))), $topic->forumName())->html);
		$result->bodyInfo->set('replies', Html::format('<li class="info-replies"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($topic->replyCount()), self::string($forum, $topic->replyCount() === 1 ? 'Reply' : 'Replies'))->html);
		$result->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span> <strong><a href="%s">%s</a></strong> <cite>%s</cite></li>', self::string($forum, 'Last post'),
			$this->urls->link('post', array($topic->lastPostId())), $this->formatter->time($topic->lastPost(), TimeFormat::DateTime), Html::format(self::string($forum, 'by poster'), $topic->lastPoster()))->html);

		$at(TopicResultAssembling::ROW);

		return array(array(
			'before'	=> new Html($before),
			'style'		=> $result->style,
			'status'	=> $result->status->join(' '),
			'subject'	=> new Html($result->bodySubject->join("\n\t\t\t\t")),
			'info'		=> new Html($result->bodyInfo->join("\n\t\t\t\t")),
		), $itemCount);
	}

	/**
	 * Starts a result: its observers run and the result is counted.
	 *
	 * @return array{string, int} the markup before it, and the count with it
	 */
	private function start(ListingInterface $listing, ResultPostInterface|ResultTopicInterface|ResultForumInterface $result, int $itemCount): array {
		$event = new ResultRowStarting($listing, $result, $itemCount);
		$this->events->dispatch($event);

		return array($event->markup(), $event->itemCount() + 1);
	}

	/**
	 * Whether the topic has a post since the visitor's last visit that they
	 * have not read, since they read the topic and since they marked its forum read.
	 */
	private function hasUnread(ResultTopicInterface $topic, TrackedTopics $tracked): bool {
		if ($this->visitor->isGuest() || $topic->lastPost() <= $this->visitor->lastVisit())
			return false;

		$read = $tracked->topic($topic->id());
		$markedRead = $tracked->forum($topic->forumId());

		return ($read === 0 || $read < $topic->lastPost()) && ($markedRead === 0 || $markedRead < $topic->lastPost());
	}

	/** The row's classes after main-item: odd or even, the first, then its status. */
	private static function style(int $itemCount, Parts $status): string {
		return ($itemCount % 2 !== 0 ? ' odd' : ' even').($itemCount === 1 ? ' main-first-item' : '').(!$status->isEmpty() ? ' '.$status->join(' ') : '');
	}

	private static function joined(TopicResultsHeadAssembling $event, string $group, string $glue): Html {
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
