<?php
/**
 * moderate.php as a module, built with no forum: looking up an address, who
 * moderates a forum, a topic's posts deleted or split off, and a forum's
 * topics moved, merged, deleted, opened, closed, stuck and unstuck.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Moderate\Api\Data\FirstPostInterface;
use PunBB\Module\Moderate\Api\Data\MergeTargetInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\Api\Data\MovedTopicInterface;
use PunBB\Module\Moderate\Api\Data\NewTopicInterface;
use PunBB\Module\Moderate\Api\Data\RedirectTopicInterface;
use PunBB\Module\Moderate\Api\ModeratedPostsInterface;
use PunBB\Module\Moderate\Api\ModeratedTopicsInterface;
use PunBB\Module\Moderate\Controller\ModerateController;
use PunBB\Module\Moderate\Controller\PostsModeration;
use PunBB\Module\Moderate\Controller\TopicsModeration;
use PunBB\Module\Moderate\Event\DeleteTopicsStep;
use PunBB\Module\Moderate\Event\HostLookupStep;
use PunBB\Module\Moderate\Event\MergeTopicsStep;
use PunBB\Module\Moderate\Event\ModerateActionRequested;
use PunBB\Module\Moderate\Event\ModeratedPostAssembling;
use PunBB\Module\Moderate\Event\ModeratedTopicAssembling;
use PunBB\Module\Moderate\Event\ModerationFormRendering;
use PunBB\Module\Moderate\Event\ModerationRequested;
use PunBB\Module\Moderate\Event\ModeratorChecking;
use PunBB\Module\Moderate\Event\MoveTopicsStep;
use PunBB\Module\Moderate\Event\PostListRendering;
use PunBB\Module\Moderate\Event\PostsModerationStep;
use PunBB\Module\Moderate\Event\TargetForumRendering;
use PunBB\Module\Moderate\Event\TopicListRendering;
use PunBB\Module\Moderate\Event\TopicStateStep;
use PunBB\Module\Moderate\Indexing\PostIndexInterface;
use PunBB\Module\Moderate\Model\FirstPost;
use PunBB\Module\Moderate\Model\ListedTopic;
use PunBB\Module\Moderate\Model\MergeTarget;
use PunBB\Module\Moderate\Model\ModeratedForum;
use PunBB\Module\Moderate\Model\ModeratedPost;
use PunBB\Module\Moderate\Model\ModeratedTopic;
use PunBB\Module\Moderate\Model\Moderator;
use PunBB\Module\Moderate\Model\MovedTopic;
use PunBB\Module\Moderate\Model\TargetForum;
use PunBB\Module\Moderate\Network\HostnameLookupInterface;
use PunBB\Module\Moderate\Sync\BoardSyncInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Posting\PreparsedMessage;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

final class FakeModeration implements ModeratedPostsInterface, ModeratedTopicsInterface, PostIndexInterface, HostnameLookupInterface, PostRulesInterface {
	/** @var array<int, ModeratedForum> */
	public array $forums = array();

	/** @var array<int, ModeratedTopic> */
	public array $moderatedTopics = array();

	/** @var list<ModeratedPost> */
	public array $posts = array();

	/** @var list<ListedTopic> */
	public array $listed = array();

	/** @var list<TargetForum> */
	public array $targets = array();

	/** @var array<int, array{int, string}> topic id => its forum and subject */
	public array $subjects = array();

	/** @var array<int, string> post id => its address */
	public array $addresses = array(5 => '198.51.100.5');

	public int $replies = -1;

	public int $topicCount = -1;

	public ?MergeTarget $mergeTarget = null;

	/** @var list<string> */
	public array $log = array();

	public function posterAddress(int $postId): ?string {
		return $this->addresses[$postId] ?? null;
	}

	public function topic(int $id, int $forumId): ?ModeratedTopicInterface {
		return $this->moderatedTopics[$id] ?? null;
	}

	public function countReplies(int $topicId, int $firstPostId, int ...$postIds): int {
		$this->log[] = 'count replies '.implode(',', $postIds).' in '.$topicId.' but '.$firstPostId;

		return $this->replies >= 0 ? $this->replies : count($postIds);
	}

	public function deletePosts(int ...$postIds): void {
		$this->log[] = 'delete posts '.implode(',', $postIds);
	}

	public function firstPost(int $id): ?FirstPostInterface {
		return new FirstPost($id, 'poster of '.$id, 1000 + $id);
	}

	public function addTopics(NewTopicInterface ...$topics): void {
		foreach ($topics as $topic)
			$this->log[] = 'add topic '.$topic->subject().' by '.$topic->poster().' at '.$topic->posted().' from post '.$topic->firstPostId().' in '.$topic->forumId();
	}

	public function lastTopicId(): int {
		return 77;
	}

	public function movePosts(int $topicId, int ...$postIds): void {
		$this->log[] = 'move posts '.implode(',', $postIds).' to '.$topicId;
	}

	public function posts(int $topicId, int $offset, int $limit): array {
		$this->log[] = 'posts of '.$topicId.' from '.$offset.' at most '.$limit;

		return $this->posts;
	}

	public function forum(int $id, int $groupId): ?ModeratedForumInterface {
		return $this->forums[$id] ?? null;
	}

	public function topics(int $forumId, bool $byPosted, int $offset, int $limit, ?int $postedBy): array {
		$this->log[] = 'topics of '.$forumId.($byPosted ? ' by posted' : '').' from '.$offset.' at most '.$limit.' posted by '.var_export($postedBy, true);

		return $this->listed;
	}

	public function subject(int $id): ?string {
		return $this->subjects[$id][1] ?? null;
	}

	public function subjectIn(int $id, int $forumId): ?string {
		return ($this->subjects[$id][0] ?? 0) === $forumId ? $this->subjects[$id][1] : null;
	}

	public function moveTargets(int $exceptId, int $groupId): array {
		return $this->targets;
	}

	public function forumName(int $id): ?string {
		return isset($this->forums[$id]) ? $this->forums[$id]->name() : null;
	}

	public function countTopics(int $forumId, int ...$topicIds): int {
		return $this->topicCount >= 0 ? $this->topicCount : count($topicIds);
	}

	public function removeRedirects(int $forumId, int ...$topicIds): void {
		$this->log[] = 'remove redirects to '.implode(',', $topicIds).' in '.$forumId;
	}

	public function moveTopics(int $forumId, int ...$topicIds): void {
		$this->log[] = 'move topics '.implode(',', $topicIds).' to '.$forumId;
	}

	public function movedTopic(int $id): ?MovedTopicInterface {
		return new MovedTopic('poster of '.$id, 'subject of '.$id, 100 + $id, 200 + $id);
	}

	public function addRedirects(RedirectTopicInterface ...$redirects): void {
		foreach ($redirects as $redirect)
			$this->log[] = 'redirect to '.$redirect->movedTo().' in '.$redirect->forumId().': '.$redirect->subject().' by '.$redirect->poster().' '.$redirect->posted().'/'.$redirect->lastPost();
	}

	public function mergeTarget(int $forumId, int ...$topicIds): MergeTargetInterface {
		return $this->mergeTarget ?? new MergeTarget(count($topicIds), min($topicIds));
	}

	public function redirectMerged(int $toId, bool $leaveRedirects, int ...$topicIds): void {
		$this->log[] = 'redirect '.implode(',', $topicIds).' to '.$toId.($leaveRedirects ? ' leaving redirects' : '');
	}

	public function mergePosts(int $toId, int ...$topicIds): void {
		$this->log[] = 'merge posts of '.implode(',', $topicIds).' into '.$toId;
	}

	public function removeMergedSubscriptions(int $toId, int ...$topicIds): void {
		$this->log[] = 'remove subscriptions to '.implode(',', $topicIds).' but '.$toId;
	}

	public function removeMergedTopics(int $toId, int ...$topicIds): void {
		$this->log[] = 'remove topics '.implode(',', $topicIds).' but '.$toId;
	}

	public function redirectForums(int ...$topicIds): array {
		return array(4);
	}

	public function removeTopics(int ...$topicIds): void {
		$this->log[] = 'remove topics '.implode(',', $topicIds);
	}

	public function removeSubscriptions(int ...$topicIds): void {
		$this->log[] = 'remove subscriptions to '.implode(',', $topicIds);
	}

	public function postIds(int ...$topicIds): array {
		return array(11, 12);
	}

	public function removePosts(int ...$topicIds): void {
		$this->log[] = 'remove posts of '.implode(',', $topicIds);
	}

	public function closeTopics(bool $closed, int $forumId, int ...$topicIds): void {
		$this->log[] = ($closed ? 'close ' : 'open ').implode(',', $topicIds).' in '.$forumId;
	}

	public function stickTopics(bool $sticky, int $forumId, int ...$topicIds): void {
		$this->log[] = ($sticky ? 'stick ' : 'unstick ').implode(',', $topicIds).' in '.$forumId;
	}

	public function strip(int ...$postIds): void {
		$this->log[] = 'strip '.implode(',', $postIds);
	}

	public function hostname(string $address): string {
		return '<b>host of '.$address.'</b>';
	}

	public function subjectMaximumLength(): int {
		return 10;
	}

	public function messageMaximumBytes(): int {
		return 100;
	}

	public function preparse(string $text, array $errors): PreparsedMessage {
		return new PreparsedMessage($text, $errors);
	}

	public function preparseSignature(string $text, array $errors): PreparsedMessage {
		return $this->preparse($text, $errors);
	}
}

final class RecordingSync implements BoardSyncInterface {
	/** @param list<string> $log */
	public function __construct(private array &$log) {}

	public function topic(int $topicId): void {
		$this->log[] = 'sync topic '.$topicId;
	}

	public function forum(int $forumId): void {
		$this->log[] = 'sync forum '.$forumId;
	}
}

class ModerateControllerTest extends TestCase {
	private PageKit $kit;

	private FakeModeration $moderation;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ModerationRequested::class, HostLookupStep::class, ModeratorChecking::class, PostsModerationStep::class, MoveTopicsStep::class, MergeTopicsStep::class,
			DeleteTopicsStep::class, TopicStateStep::class, ModerateActionRequested::class, ModerationFormRendering::class, TargetForumRendering::class, PostListRendering::class,
			ModeratedPostAssembling::class, TopicListRendering::class, ModeratedTopicAssembling::class, MessageShowing::class, MessageRendering::class, RedirectShowing::class,
			RedirectHeadAssembling::class, ConfirmFormRequested::class, ConfirmFormRendering::class));
		$this->kit->language->real = array('common', 'misc', 'topic', 'forum', 'post');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->settings->values['o_topic_views'] = '1';
		$this->kit->visitor->administrator = true;
		$this->kit->visitor->moderating = true;
		$this->kit->visitor->id = 2;
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::Moderate);

		$this->moderation = new FakeModeration();
		$this->moderation->forums = array(
			1 => new ModeratedForum(1, 'Lounge <1>', '', 3, array(new Moderator(7, 'member')), false),
			2 => new ModeratedForum(2, 'Archive', '', 0, array(), true),
			3 => new ModeratedForum(3, 'Elsewhere', 'http://example.com/', 0, array(), false),
		);
		$this->moderation->moderatedTopics = array(5 => new ModeratedTopic(5, 'Topic "five"', 'starter', 21, 1000, 3));
		$this->moderation->subjects = array(5 => array(1, 'Topic "five"'), 6 => array(1, '0'));
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$log = &$this->moderation->log;
		$sync = new RecordingSync($log);
		$kit = $this->kit;
		$confirmations = new ConfirmPage($kit->dispatcher, $kit->pages(), new TemplateRenderer(), $kit->redirects(), $kit->language, $kit->settings, $kit->urls, $kit->visitor, $kit->tokens);

		$posts = new PostsModeration($kit->dispatcher, $kit->pages(), new TemplateRenderer(), $kit->messages(), $kit->redirects(), $this->moderation, $sync, $this->moderation, $this->moderation,
			$kit->visitor, $kit->language, $kit->settings, $kit->urls, $kit->formatter, $kit->tokens, $kit->flash);
		$topics = new TopicsModeration($kit->dispatcher, $kit->pages(), new TemplateRenderer(), $kit->messages(), $kit->redirects(), $confirmations, $this->moderation, $sync, $this->moderation,
			$kit->visitor, $kit->language, $kit->settings, $kit->urls, $kit->formatter, $kit->tokens, $kit->flash);
		$controller = new ModerateController($kit->dispatcher, $kit->messages(), $kit->redirects(), $this->moderation, $this->moderation, $posts, $topics, $this->moderation, $kit->visitor, $kit->language, $kit->urls);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'moderate.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	/** @return list<string> */
	private function crumbs(): array {
		return array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs);
	}

	public function testAnAddressIsLookedUpForAnyAdministratorOrModerator(): void {
		$bad = '<p>Bad request. The link you followed is incorrect or outdated.</p>';

		$this->kit->visitor->moderating = false;
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('get_host' => '192.0.2.9')));

		$this->kit->visitor->moderating = true;
		$this->assertStringContainsString($bad, $this->page(array('get_host' => array('192.0.2.9'))));
		$this->assertStringContainsString($bad, $this->page(array('get_host' => '9')), 'a post with no address');
		$this->assertStringContainsString($bad, $this->page(array('get_host' => 'localhost')));

		$this->kit->events->dispatched = array();
		$body = $this->page(array('get_host' => '192.0.2.9'));

		$this->assertStringContainsString('<p>The IP address is: 192.0.2.9<br />The host name is: &lt;b&gt;host of 192.0.2.9&lt;/b&gt;<br /><br /><a href="/admin_users?a=1&amp;b=2?show_users=192.0.2.9">Show more users for this IP</a></p>', $body, 'the name the reverse zone gives is text');
		$this->assertSame(array('ModerationRequested', 'HostLookupStep', 'HostLookupStep'), array_slice($this->kit->events->dispatched, 0, 3));

		$this->assertStringContainsString('The IP address is: 198.51.100.5<br />', $this->page(array('get_host' => '5')), 'a post\'s address');
		$this->assertStringContainsString('The IP address is: 2001:db8::1<br />', $this->page(array('get_host' => '2001:db8::1')));
	}

	public function testAForumIsModeratedByAnAdministratorOrTheModeratorsItLists(): void {
		$bad = '<p>Bad request. The link you followed is incorrect or outdated.</p>';
		$denied = '<p>You do not have permission to access this page.</p>';

		$this->assertStringContainsString($bad, $this->page(array('fid' => '0')));
		$this->assertStringContainsString($bad, $this->page(array('fid' => '9')));
		$this->assertStringContainsString($bad, $this->page(array('fid' => '3')), 'a forum on another site');

		$this->kit->visitor->administrator = false;
		$this->assertStringContainsString('[modforum]', $this->page(array('fid' => '1')), 'the forum lists the member');
		$this->assertStringContainsString($denied, $this->page(array('fid' => '2')));

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->assertStringContainsString($denied, $this->page(array('fid' => '1')), 'a listed member whose group no longer moderates');

		$this->kit->visitor->administrator = true;
		$this->kit->events->observe(ModeratorChecking::class, static fn (ModeratorChecking $event) => $event->treatAsModerating(false));
		$this->assertStringContainsString($denied, $this->page(array('fid' => '1')));
	}

	public function testCancellingGoesBackToTheForum(): void {
		$this->assertStringStartsWith('302 /forum/1/slug-lounge-1-?a=1&b=2 ', $this->page(array('fid' => '1', 'tid' => '5'), array('cancel' => '1')));
	}

	public function testATopicsPostsAreListedSelectableButTheFirst(): void {
		$this->kit->visitor->postsPerPage = 2;
		$this->kit->settings->values['o_censoring'] = '1';
		$this->moderation->moderatedTopics[5] = new ModeratedTopic(5, 'Darn topic', 'starter', 21, 1000, 3);
		$this->moderation->posts = array(
			new ModeratedPost(23, 'mod <m>', 4, 'Third', true, 1300, 1350, 'admin <a>', 'Veteran', 12, 4, 'Moderator'),
			new ModeratedPost(24, 'Guest poster', 1, 'Fourth', false, 1400, null, '', '', 0, 2, null),
		);

		$body = $this->page(array('fid' => '1', 'tid' => '5', 'p' => '2'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('modtopic', 2), array($head->id, $head->page));
		$this->assertSame(array('Board & Co', 'Lounge <1>', 'd*rn topic', 'Moderate topic'), $this->crumbs());
		$this->assertSame('Moderate topic: d*rn topic', $head->mainTitle?->html);
		$this->assertSame('(Page 2 of 2)', $head->pageCount?->html);
		$this->assertSame('<p class="paging"><span class="pages">Pages</span> [pages 2 of 2 at moderate_topic]</p>', $head->pagePost['paging']->html);
		$this->assertSame(array('prev', 'first'), array_keys($head->navigation));
		$this->assertSame(array('posts of 5 from 2 at most 2'), $this->moderation->log);

		$this->assertStringStartsWith("200  [modtopic]<div class=\"main-head\">\n\n\t\t<p class=\"options\"><span  class=\"first-item\"><span class=\"select-all js_link\" data-check-form=\"mr-post-actions-form\">Select all</span></span></p>\t\t<h2 class=\"hn\"><span>Posts: 3-4/4 in 2</span></h2>", $body);
		$this->assertStringContainsString("<form id=\"mr-post-actions-form\" class=\"newform\" method=\"post\" accept-charset=\"utf-8\" action=\"/moderate_topic/1/5?a=1&amp;b=2\">\n\t<div class=\"main-content main-topic\">", $body);
		$this->assertStringContainsString("\t\t</div>\n\n\t\t\t<div class=\"post odd firstpost replypost\">\n\t\t\t\t<div id=\"p23\" class=\"posthead\">", $body);
		$this->assertStringContainsString('<h3 class="hn post-ident"><span class="post-num">3</span> <span class="post-byline"><span>Reply by </span><a title="Go to mod &lt;m&gt;\'s profile" href="/user/4?a=1&amp;b=2">mod &lt;m&gt;</a></span> <span class="post-link"><a class="permalink" rel="bookmark" title="Permanent link to this post" href="/post/23?a=1&amp;b=2"><time>1300</time></a></span> <span class="post-edit">(edited by admin &lt;a&gt; <time>1350</time>)</span></h3>', $body);
		$this->assertStringContainsString("</h3>\n\t\t\t\t<p class=\"item-select\"><input type=\"checkbox\" id=\"fld23\" name=\"posts[]\" value=\"23\" /> <label for=\"fld23\">Select post 3</label></p>\n\t\t\t\t</div>", $body);
		$this->assertStringContainsString("<li class=\"username\"><a title=\"Go to mod &lt;m&gt;'s profile\" href=\"/user/4?a=1&amp;b=2\">mod &lt;m&gt;</a></li>\n\t\t\t\t\t\t<li class=\"usertitle\"><span>Veteran</span></li>", $body);
		$this->assertStringContainsString('<h4 class="entry-title">Re: d*rn topic</h4>', $body);
		$this->assertStringContainsString('<p>Third (no smilies)</p>', $body);
		$this->assertStringContainsString('<div class="post even lastpost replypost">', $body);
		$this->assertStringContainsString('<span class="post-byline"><span>Reply by </span><strong>Guest poster</strong></span>', $body);
		$this->assertStringContainsString("\t</div>\n\n\t<div class=\"main-options mod-options gen-content\">\n\t\t<p class=\"options\"><span class=\"submit first-item\"><input type=\"submit\" name=\"delete_posts\" value=\"Delete selected posts\" /></span> <span class=\"submit\"><input type=\"submit\" name=\"split_posts\" value=\"Split selected posts\" /></span> <span><a href=\"/delete/21?a=1&amp;b=2\">Delete whole topic</a></span></p>", $body);
		$this->assertStringEndsWith("<h2 class=\"hn\"><span>Posts: 3-4/4 in 2</span></h2>\n\t</div>", $body);
		$this->assertSame(array('PUNBB.common.addDOMReadyEvent(PUNBB.common.initToggleCheckboxes);'), $this->kit->chromes->scripts);

		$this->moderation->posts = array(new ModeratedPost(21, 'starter', 3, 'First', false, 1000, null, '', '', 1, 3, null));
		$first = $this->page(array('fid' => '1', 'tid' => '5'));
		$this->assertStringContainsString("</h3>\n\t\t\t\t</div>", $first, 'the first post has no checkbox');
		$this->assertStringContainsString('<div class="post odd firstpost topicpost">', $first);
		$this->assertStringContainsString('<h4 class="entry-title">Topic: d*rn topic</h4>', $first);
	}

	public function testObserversChangeAPostsPartsAndItsCheckbox(): void {
		$this->moderation->posts = array(new ModeratedPost(22, 'member', 3, 'Second', false, 1200, null, '', '', 1, 3, null));
		$this->kit->events->observe(ModeratedPostAssembling::class, function (ModeratedPostAssembling $event): void {
			match ($event->stage()) {
				ModeratedPostAssembling::IDENT				=> $event->set(ModeratedPostAssembling::PART_POST_IDENT, 'probe', '<span>probe '.$event->number().'</span>'),
				ModeratedPostAssembling::PRE_ITEM_SELECT	=> $event->setSelect('<p>probe select</p>'),
				ModeratedPostAssembling::ENTRY				=> $event->append("<p>probe entry</p>\n"),
				default										=> null,
			};
		});
		$this->kit->events->observe(PostListRendering::class, static function (PostListRendering $event): void {
			if ($event->position() === PostListRendering::PRE_MOD_OPTIONS)
				$event->remove(PostListRendering::MOD_OPTIONS, 'del_topic');
		});

		$body = $this->page(array('fid' => '1', 'tid' => '5'));

		$this->assertStringContainsString('<span>probe 1</span></h3>', $body);
		$this->assertStringContainsString("\t\t\t\t<p>probe select</p>\n", $body);
		$this->assertStringContainsString("\t\t\t\t\t\t</div>\n<p>probe entry</p>\n\t\t\t\t\t</div>", $body);
		$this->assertStringNotContainsString('Delete whole topic', $body);
	}

	public function testPostsAreDeletedOnceConfirmed(): void {
		$this->assertStringContainsString('<p>You must select at least one post.</p>', $this->page(array('fid' => '1', 'tid' => '5'), array('delete_posts' => '1', 'posts' => '0')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('fid' => '1', 'tid' => '9'), array('delete_posts' => '1')));
		$this->assertStringStartsWith('302 /topic/5/slug-topic-five-?a=1&b=2 ', $this->page(array('fid' => '1', 'tid' => '5'), array('delete_posts_cancel' => '1')));

		$this->kit->chromes->opened = array();
		$form = $this->page(array('fid' => '1', 'tid' => '5'), array('delete_posts' => '1', 'posts' => array('22', '23')));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('dialogue', 'delete_posts'), array($head->id, $head->view));
		$this->assertSame(array('Board & Co', 'Lounge <1>', 'Topic "five"', 'Delete selected posts'), $this->crumbs());
		$this->assertStringContainsString("<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/moderate_topic/1/5?a=1&amp;b=2')."\" />\n\t\t\t\t<input type=\"hidden\" name=\"posts\" value=\"22,23\" />\n\t\t\t</div>", $form);
		$this->assertStringContainsString('<label for="fld1"><span>Please confirm:</span> Confirm deletion of all selected posts.</label>', $form);

		$this->assertStringStartsWith('302 /topic/5/slug-topic-five-?a=1&b=2 ', $this->page(array('fid' => '1', 'tid' => '5'), array('delete_posts_comply' => '1', 'posts' => '22,23')), 'no confirmation');
		$this->assertSame(array(), $this->moderation->log);

		$this->moderation->replies = 1;
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1', 'tid' => '5'), array('delete_posts_comply' => '1', 'req_confirm' => '1', 'posts' => '22,21')));

		$this->moderation->replies = -1;
		$this->moderation->log = array();
		$done = $this->page(array('fid' => '1', 'tid' => '5'), array('delete_posts_comply' => '1', 'req_confirm' => '1', 'posts' => '22,23'));

		$this->assertStringStartsWith('302 /topic/5/slug-topic-five-?a=1&b=2 ', $done);
		$this->assertSame(array('count replies 22,23 in 5 but 21', 'delete posts 22,23', 'strip 22,23', 'sync topic 5', 'sync forum 1'), $this->moderation->log);
		$this->assertSame(array('Posts deleted.'), $this->kit->flash->info);
	}

	public function testPostsAreSplitOffIntoATopicOfTheirOwn(): void {
		$form = $this->page(array('fid' => '1', 'tid' => '5'), array('split_posts' => '1', 'posts' => array('23', '22')));

		$this->assertSame(array('dialogue', 'split_posts'), array($this->kit->chromes->opened[0]->id, $this->kit->chromes->opened[0]->view));
		$this->assertStringContainsString('<input type="text" id="fld1" name="new_subject" size="10" maxlength="10" required />', $form);
		$this->assertStringContainsString("<input type=\"checkbox\" id=\"fld2\" name=\"req_confirm\" value=\"1\" checked=\"checked\" /></span>\n\t\t\t\t\t\t<label for=\"fld3\">", $form, 'the label is numbered past its checkbox, as it was');

		$this->assertStringContainsString('<p>Topics must contain a subject.</p>', $this->page(array('fid' => '1', 'tid' => '5'), array('split_posts_comply' => '1', 'req_confirm' => '1', 'posts' => '22', 'new_subject' => ' ')));
		$this->assertStringContainsString('<p>Subjects cannot be longer than 10 characters.</p>', $this->page(array('fid' => '1', 'tid' => '5'), array('split_posts_comply' => '1', 'req_confirm' => '1', 'posts' => '22', 'new_subject' => 'Eleven long')));

		$this->moderation->log = array();
		$done = $this->page(array('fid' => '1', 'tid' => '5'), array('split_posts_comply' => '1', 'req_confirm' => '1', 'posts' => '23,22', 'new_subject' => ' Split <s> '));

		$this->assertStringStartsWith('302 /topic/77/slug-split-s-?a=1&b=2 ', $done);
		$this->assertSame(array('count replies 23,22 in 5 but 21', 'add topic Split <s> by poster of 22 at 1022 from post 22 in 1', 'move posts 23,22 to 77', 'sync topic 77', 'sync topic 5', 'sync forum 1'), $this->moderation->log);
		$this->assertSame(array('Posts split into a new topic.'), $this->kit->flash->info);
	}

	public function testAForumsTopicsAreListedSelectable(): void {
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '2')), 'an empty forum');

		$this->kit->visitor->topicsPerPage = 2;
		$this->kit->visitor->postsPerPage = 1;
		$this->kit->visitor->lastVisit = 100;
		$this->moderation->listed = array(
			new ListedTopic(8, 'mover', 'Moved <away>', 100, 200, 0, '', 0, 0, false, false, 5, false),
			new ListedTopic(9, 'poster', 'Sticky', 300, 400, 41, 'last <l>', 1, 2, true, true, null, true),
		);

		$this->kit->chromes->opened = array();
		$body = $this->page(array('fid' => '1', 'p' => '2'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('modforum', 2), array($head->id, $head->page));
		$this->assertSame(array('Board & Co', 'Lounge <1>', 'Moderate: Lounge &lt;1&gt;'), $this->crumbs());
		$this->assertSame(array("topics of 1 from 2 at most 2 posted by NULL"), array_values(array_filter($this->moderation->log, static fn (string $line): bool => str_starts_with($line, 'topics'))), 'the board marks no posts');
		$this->assertStringContainsString("<p class=\"item-summary forum-views\"><span><strong class=\"subject-title\">Topics</strong> in this forum with details of <strong class=\"info-views\">views</strong>, <strong class=\"info-replies\">replies</strong>, <strong class=\"info-lastpost\">last post</strong>.</span></p>", $body);
		$this->assertStringContainsString('<div id="topic8" class="main-item odd main-first-item moved">', $body);
		$this->assertStringContainsString('<h3 class="hn"><span class="item-num">3</span> <strong><span class="item-status"><em class="moved">Moved:</em></span> <a href="/topic/5/slug-moved-away-?a=1&amp;b=2">Moved &lt;away&gt;</a></strong></h3>', $body);
		$this->assertStringContainsString("<li class=\"info-views\"><span class=\"label\">No viewing information</span></li>\n\t\t\t\t\t<li class=\"info-replies\"><span class=\"label\">No reply information</span></li>", $body);
		$this->assertStringContainsString('<li class="info-select"><input id="fld1" type="checkbox" name="topics[]" value="8" /> <label for="fld1">Select topic: Moved &lt;away&gt;.</label></li>', $body);
		$this->assertStringContainsString('<div id="topic9" class="main-item even sticky closed new">', $body);
		$this->assertStringContainsString('<h3 class="hn"><span class="item-num">4</span> <span class="item-status"><em class="sticky">Sticky</em>, <em class="closed">Closed</em>:</span> <a href="/topic/9/slug-sticky?a=1&amp;b=2">Sticky</a></h3>', $body);
		$this->assertStringContainsString('<span class="item-nav">( <span>Pages&#160;</span>[pages -1 of 3 at topic by &#160;]&#160;&#160;<em class="item-newposts"><a href="/topic_new_posts/9/slug-sticky?a=1&amp;b=2">New posts</a></em> )</span>', $body);
		$this->assertStringContainsString("<li class=\"info-replies\"><strong>2</strong> <span class=\"label\">Replies</span></li>\n\t\t\t\t\t<li class=\"info-views\"><strong>1</strong> <span class=\"label\">View</span></li>\n\t\t\t\t\t<li class=\"info-lastpost\"><span class=\"label\">Last post</span> <strong><a href=\"/post/41?a=1&amp;b=2\"><time>400</time></a></strong> <cite>by last &lt;l&gt;</cite></li>\n\t\t\t\t\t<li class=\"info-select\"><input id=\"fld2\"", $body);
		$this->assertStringNotContainsString('posted-mark', $body, 'the board marks no posts');
		$this->assertStringContainsString("\t</div>\n\t<div class=\"main-options mod-options gen-content\">\n\t\t<p class=\"options\"><span class=\"submit first-item\"><input type=\"submit\" name=\"move_topics\" value=\"Move\" /></span> <span class=\"submit\"><input type=\"submit\" name=\"delete_topics\" value=\"Delete\" /></span> <span class=\"submit\"><input type=\"submit\" name=\"merge_topics\" value=\"Merge\" /></span> <span class=\"submit\"><input type=\"submit\" name=\"open\" value=\"Open\" /></span> <span class=\"submit\"><input type=\"submit\" name=\"close\" value=\"Close\" /></span></p>", $body);
		$this->assertStringContainsString('ModerateActionRequested', implode(' ', $this->kit->events->dispatched));

		$this->kit->settings->values['o_show_dot'] = '1';
		$this->moderation->log = array();
		$this->assertStringContainsString('<span class="posted-mark">·</span> <span class="item-status">', $this->page(array('fid' => '1')));
		$this->assertSame(array('topics of 1 from 0 at most 2 posted by 2'), $this->moderation->log, 'the member asked about is the visitor');
	}

	public function testTopicsMoveToAnotherForum(): void {
		$this->moderation->targets = array(new TargetForum(1, 'First <c>', 2, 'Archive'), new TargetForum(1, 'First <c>', 4, 'Old'), new TargetForum(3, 'Other', 6, 'Last'));

		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1', 'move_topics' => '9')), 'a topic that is not');
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1', 'move_topics' => '6')), 'a subject that reads false');
		$this->moderation->subjects[7] = array(3, 'Elsewhere');
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1', 'move_topics' => '7')), 'a topic of another forum');
		$this->assertStringContainsString('<p>You must select at least one topic.</p>', $this->page(array('fid' => '1'), array('move_topics' => '1')));

		$this->kit->chromes->opened = array();
		$single = $this->page(array('fid' => '1', 'move_topics' => '5'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('dialogue', 'move_topics', 'Move topic to a new forum'), array($head->id, $head->view, $head->mainTitle?->html));
		$this->assertSame(array('Board & Co', 'Lounge <1>', 'Topic "five"', 'Move topic'), $this->crumbs());
		$this->assertStringContainsString("<input type=\"hidden\" name=\"topics\" value=\"5\" />", $single);
		$this->assertStringContainsString("name=\"move_to_forum\">\n\t\t\t\t<optgroup label=\"First &lt;c&gt;\">\n\t\t\t\t<option value=\"2\">Archive</option>\n\t\t\t\t<option value=\"4\">Old</option>\n\t\t\t\t</optgroup>\n\t\t\t\t<optgroup label=\"Other\">\n\t\t\t\t<option value=\"6\">Last</option>\n\t\t\t\t\t\t</optgroup>", $single);
		$this->assertStringContainsString('name="with_redirect" value="1" checked="checked" /></span>', $single);

		$this->kit->chromes->opened = array();
		$multiple = $this->page(array('fid' => '1'), array('move_topics' => '1', 'topics' => array('5', '8')));
		$this->assertSame(array('Board & Co', 'Lounge <1>', 'Moderate forum', 'Move topics'), $this->crumbs());
		$this->assertStringContainsString('<input type="hidden" name="topics" value="5,8" />', $multiple);
		$this->assertStringContainsString('name="with_redirect" value="1" /></span>', $multiple);

		$targets = $this->moderation->targets;
		$this->moderation->targets = array();
		$this->assertStringContainsString('<p>There are no forums into which you can move topics.</p>', $this->page(array('fid' => '1', 'move_topics' => '5')));
		$this->moderation->targets = $targets;

		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1'), array('move_topics_to' => '1', 'topics' => '5', 'move_to_forum' => '9')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1'), array('move_topics_to' => '1', 'topics' => '5', 'move_to_forum' => '1')), 'the forum moderated');
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1'), array('move_topics_to' => '1', 'topics' => '5', 'move_to_forum' => '3')), 'a forum that is no target');
		$this->moderation->topicCount = 1;
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1'), array('move_topics_to' => '1', 'topics' => '5,8', 'move_to_forum' => '2')));
		$this->assertSame(array(), $this->moderation->log);

		$this->moderation->topicCount = -1;
		$done = $this->page(array('fid' => '1'), array('move_topics_to' => '1', 'topics' => '5,8', 'move_to_forum' => '2', 'with_redirect' => '1'));

		$this->assertStringStartsWith('302 /forum/2/slug-archive?a=1&b=2 ', $done);
		$this->assertSame(array('remove redirects to 5,8 in 2', 'move topics 5,8 to 2', 'redirect to 5 in 1: subject of 5 by poster of 5 105/205', 'redirect to 8 in 1: subject of 8 by poster of 8 108/208', 'sync forum 1', 'sync forum 2'), $this->moderation->log);
		$this->assertSame(array('Topics moved.'), $this->kit->flash->info);
	}

	public function testTopicsMergeIntoTheOldestOfThem(): void {
		$this->assertStringContainsString('<p>You should select more than 1 topic to merge.</p>', $this->page(array('fid' => '1'), array('merge_topics' => '1', 'topics' => array('5'))));

		$this->kit->chromes->opened = array();
		$form = $this->page(array('fid' => '1'), array('merge_topics' => '1', 'topics' => array('8', '5')));
		$this->assertSame(array('Board & Co', 'Lounge <1>', 'Moderate forum', 'Merge topics'), $this->crumbs());
		$this->assertStringContainsString('<input type="hidden" name="topics" value="8,5" />', $form);
		$this->assertStringContainsString('<input type="checkbox" id="fld1" name="with_redirect" value="1" /></span>', $form);

		$this->moderation->mergeTarget = new MergeTarget(1, 5);
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1'), array('merge_topics_comply' => '1', 'topics' => '8,5')));

		$this->moderation->mergeTarget = null;
		$this->page(array('fid' => '1'), array('merge_topics_comply' => '1', 'topics' => '8,5'));
		$this->page(array('fid' => '1'), array('merge_topics_comply' => '1', 'topics' => '8,5', 'with_redirect' => '1'));

		$this->assertSame(array(
			'redirect 8,5 to 5', 'merge posts of 8,5 into 5', 'remove subscriptions to 8,5 but 5', 'remove topics 8,5 but 5', 'sync topic 5', 'sync forum 1',
			'redirect 8,5 to 5 leaving redirects', 'merge posts of 8,5 into 5', 'remove subscriptions to 8,5 but 5', 'sync topic 5', 'sync forum 1',
		), $this->moderation->log);
	}

	public function testTopicsAreDeletedOnceConfirmed(): void {
		$form = $this->page(array('fid' => '1'), array('delete_topics' => '1', 'topics' => array('5', '8')));
		$this->assertSame(array('Board & Co', 'Lounge <1>', 'Moderate forum', 'Delete topics'), $this->crumbs());
		$this->assertStringContainsString('<span>Please confirm:</span> Are you sure you want to delete all the selected topics?</label>', $form);

		$this->assertStringStartsWith('302 /forum/1/slug-lounge-1-?a=1&b=2 ', $this->page(array('fid' => '1'), array('delete_topics_comply' => '1', 'topics' => '5')), 'no confirmation');

		$this->page(array('fid' => '1'), array('delete_topics_comply' => '1', 'req_confirm' => '1', 'topics' => '5'));

		$this->assertSame(array('remove topics 5', 'remove subscriptions to 5', 'strip 11,12', 'remove posts of 5', 'sync forum 1', 'sync forum 4'), $this->moderation->log);
		$this->assertSame(array('Topic deleted.'), $this->kit->flash->info);
	}

	public function testTopicsOpenAndCloseFromTheListOrTheirOwnLink(): void {
		$this->assertStringContainsString('<p>You must select at least one topic.</p>', $this->page(array('fid' => '1'), array('close' => '1')));

		$this->assertStringStartsWith('302 /moderate_forum/1?a=1&b=2 ', $this->page(array('fid' => '1'), array('close' => '1', 'topics' => array('5', '8'))));
		$this->assertStringContainsString('name="confirm_cancel"', $this->page(array('fid' => '1', 'open' => '5', 'csrf_token' => 'forged')), 'a link without its token is confirmed first');
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1', 'open' => '9', 'csrf_token' => $this->kit->tokens->token('open92'))));

		$link = $this->page(array('fid' => '1', 'open' => '5', 'csrf_token' => $this->kit->tokens->token('open52')));

		$this->assertStringStartsWith('302 /topic/5/slug-topic-five-?a=1&b=2 ', $link);
		$this->assertSame(array('close 5,8 in 1', 'open 5 in 1'), $this->moderation->log);
		$this->assertSame(array('Topics closed.', 'Topic opened.'), $this->kit->flash->info);
	}

	public function testATopicsLinkSticksAndUnsticksIt(): void {
		$this->assertStringContainsString('name="confirm_cancel"', $this->page(array('fid' => '1', 'stick' => '5', 'csrf_token' => $this->kit->tokens->token('unstick52'))));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('fid' => '1', 'unstick' => '0')));

		$this->assertStringStartsWith('302 /topic/5/slug-topic-five-?a=1&b=2 ', $this->page(array('fid' => '1', 'stick' => '5', 'csrf_token' => $this->kit->tokens->token('stick52'))));
		$this->page(array('fid' => '1', 'unstick' => '5', 'csrf_token' => $this->kit->tokens->token('unstick52')));

		$this->assertSame(array('stick 5 in 1', 'unstick 5 in 1'), $this->moderation->log);
		$this->assertSame(array('Topic is now sticky.', 'Topic no longer sticky.'), $this->kit->flash->info);
	}
}
