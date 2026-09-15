<?php
/**
 * delete.php as a module, built with no forum: who may delete which post, the
 * page confirming it and the numbers of its form's fields as observers add
 * some, the redirect a cancel or an unconfirmed submission gets, and what a
 * confirmed deletion takes off the board and where it sends the visitor.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Delete\Api\DeletablePostsInterface;
use PunBB\Module\Delete\Controller\DeleteController;
use PunBB\Module\Delete\Event\DeletionPermissionChecking;
use PunBB\Module\Delete\Event\DeletionRendering;
use PunBB\Module\Delete\Event\DeletionRequested;
use PunBB\Module\Delete\Event\PostDeletionStep;
use PunBB\Module\Delete\Event\PostIdentAssembling;
use PunBB\Module\Delete\Model\DeletablePost;
use PunBB\Module\Delete\Model\Moderator;
use PunBB\Module\Delete\Removal\PostRemovalInterface;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

final class FakeDeletablePosts implements DeletablePostsInterface {
	/** @var array<int, DeletablePostInterface> */
	public array $posts = array();

	public ?int $previous = null;

	public function __construct(private array &$log) {}

	public function find(int $postId, int $groupId): ?DeletablePostInterface {
		$this->log[] = 'find '.$postId.' '.$groupId;

		return $this->posts[$postId] ?? null;
	}

	public function previousPostId(int $topicId, int $postId): ?int {
		$this->log[] = 'previous '.$topicId.' '.$postId;

		return $this->previous;
	}
}

final class FakePostRemoval implements PostRemovalInterface {
	public function __construct(private array &$log) {}

	public function removeTopic(int $topicId, int $forumId): void {
		$this->log[] = 'remove topic '.$topicId.' '.$forumId;
	}

	public function removePost(int $postId, int $topicId, int $forumId): void {
		$this->log[] = 'remove post '.$postId.' '.$topicId.' '.$forumId;
	}
}

class DeleteControllerTest extends TestCase {
	private PageKit $kit;

	private FakeDeletablePosts $posts;

	/** @var list<string> */
	private array $log = array();

	protected function setUp(): void {
		$this->kit = new PageKit(array(DeletionRequested::class, DeletionPermissionChecking::class, PostDeletionStep::class, PostIdentAssembling::class, DeletionRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('delete', 'common');
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::DeletePosts, GroupPermission::DeleteTopics);
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->posts = new FakeDeletablePosts($this->log);

		// A reply by the visitor, and the post opening the topic, by someone else
		$this->posts->posts[5] = self::post(5, 3, 1);
		$this->posts->posts[1] = self::post(1, 2, 1);
	}

	private static function post(int $id, int $posterId, int $firstPostId, bool $closed = false, array $moderators = array()): DeletablePost {
		return new DeletablePost($id, 4, 'Forum <4>', $moderators, 9, 'Topic "9"', $firstPostId, $closed, 'poster<'.$posterId.'>', $posterId, 'Text of '.$id, true, 1234);
	}

	private function delete(array $query, array $post = array()): string {
		$controller = new DeleteController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $this->posts,
			new FakePostRemoval($this->log), $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'delete.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testAVisitorWhoMayNotReadTheBoardGetsAMessage(): void {
		$this->kit->visitor->permissions = array();

		$this->assertStringContainsString('<p>You do not have permission to view these forums.</p>', $this->delete(array('id' => '5')));
		$this->assertSame(array(), $this->log);
	}

	public function testAPostThatIsNotThereOrNotReadableIsABadRequest(): void {
		foreach (array(array(), array('id' => '0'), array('id' => 'x'), array('id' => array('5')), array('id' => '99')) as $query)
			$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->delete($query));

		$this->assertSame(array('find 99 3'), $this->log);
	}

	public function testThePosterMayDeleteTheirPostWhileTheTopicIsOpenAndTheirGroupAllows(): void {
		$this->assertStringContainsString('[postdelete]', $this->delete(array('id' => '5')));

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->delete(array('id' => '1')), 'another poster\'s topic');

		$this->posts->posts[5] = self::post(5, 3, 1, true);
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->delete(array('id' => '5')), 'a closed topic');

		$this->posts->posts[5] = self::post(5, 3, 1);
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::DeleteTopics);
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->delete(array('id' => '5')), 'a group that may not delete posts');
	}

	public function testAModeratorOfTheForumOrAnAdministratorMayDeleteAnyPost(): void {
		$this->posts->posts[1] = self::post(1, 2, 1, true, array(new Moderator(3, 'member')));
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->delete(array('id' => '1')), 'moderating in a group that may not moderate');

		$this->kit->visitor->permissions[] = GroupPermission::Moderate;
		$this->assertStringContainsString('[postdelete]', $this->delete(array('id' => '1')));

		$this->posts->posts[1] = self::post(1, 2, 1, true, array(new Moderator(8, 'someone')));
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->delete(array('id' => '1')), 'moderating another forum');

		$this->kit->visitor->administrator = true;
		$this->assertStringContainsString('[postdelete]', $this->delete(array('id' => '1')));
	}

	public function testAnObserverDecidesWhetherTheVisitorCountsAsAModerator(): void {
		$this->kit->events->observe(DeletionPermissionChecking::class, function (DeletionPermissionChecking $event): void {
			$this->assertFalse($event->moderating());
			$event->treatAsModerating(true);
		});

		$this->assertStringContainsString('[postdelete]', $this->delete(array('id' => '1')));
	}

	public function testThePageShowsThePostAndTheFormConfirmingItsDeletion(): void {
		$body = $this->delete(array('id' => '5'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('postdelete', $head->id);
		$this->assertSame(array('Board & Co', 'Forum <4>', 'Topic "9"', 'Delete post'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertSame('/forum/4/slug-forum-4-?a=1&amp;b=2', $head->crumbs[1]->link?->html);

		$this->assertStringStartsWith("200  [postdelete]<div class=\"main-content main-frm\">\n\t\t<div class=\"ct-box info-box\">\n\t\t\t<ul class=\"info-list\">\n\t\t\t\t<li><span>Forum:<strong> Forum &lt;4&gt;</strong></span></li>\n\t\t\t\t<li><span>Topic:<strong> Topic &quot;9&quot;</strong></span></li>\n\t\t\t</ul>", $body);
		$this->assertStringContainsString('<h3 class="hn post-ident"><span class="post-byline"><span>Reply by </span><strong>poster&lt;3&gt;</strong></span> <span class="post-link"><a class="permalink" href="/post/9?a=1&amp;b=2"><time>1234</time></a></span></h3>', $body);
		$this->assertStringContainsString("<h4 class=\"entry-title hn\">Reply to: Topic &quot;9&quot;</h4>\n\t\t\t\t\t<div class=\"entry-content\">\n\t\t\t\t\t\t<p>Text of 5 (no smilies)</p>\n\t\t\t\t\t</div>", $body);
		$this->assertStringContainsString("<form class=\"frm-form\" method=\"post\" accept-charset=\"utf-8\" action=\"/delete/5?a=1&amp;b=2\">\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/delete/5?a=1&amp;b=2')."\" />\n\t\t\t</div>", $body);
		$this->assertStringContainsString('<label for="fld1"><span>Please confirm:</span> Delete post by poster&lt;3&gt; posted <time>1234</time></label>', $body);
		$this->assertStringContainsString('<input type="submit" name="delete" value="Delete post" />', $body);
		$this->assertSame(array('DeletionRequested', 'DeletionPermissionChecking', 'PostDeletionStep', 'PostIdentAssembling', 'DeletionRendering:main_output_start', 'DeletionRendering:pre_post_display',
			'DeletionRendering:new_post_head_option', 'DeletionRendering:new_post_entry_data', 'DeletionRendering:pre_confirm_delete_fieldset', 'DeletionRendering:pre_confirm_delete_checkbox',
			'DeletionRendering:pre_confirm_delete_fieldset_end', 'DeletionRendering:confirm_delete_fieldset_end', 'DeletionRendering:end'), $this->kit->events->dispatched);
	}

	public function testATopicsFirstPostIsDeletedAsTheTopic(): void {
		$this->kit->visitor->administrator = true;

		$body = $this->delete(array('id' => '1'));

		$this->assertStringContainsString('<span>Topic by </span><strong>poster&lt;2&gt;</strong>', $body);
		$this->assertStringContainsString('<h4 class="entry-title hn">Topic: Topic &quot;9&quot;</h4>', $body);
		$this->assertStringContainsString('Delete topic by poster&lt;2&gt; (including replies) created <time>1234</time>', $body);
		$this->assertSame('Delete topic', $this->kit->chromes->opened[0]->crumbs[3]->text);
	}

	public function testObserversChangeTheHeadingAndNumberTheFieldsTheyAdd(): void {
		$this->kit->events->observe(PostIdentAssembling::class, function (PostIdentAssembling $event): void {
			$event->remove('link');
			$event->set('probe', '<span>probed</span>');
		});
		$this->kit->events->observe(DeletionRendering::class, function (DeletionRendering $event): void {
			if ($event->position() !== DeletionRendering::PRE_CONFIRM_DELETE_CHECKBOX)
				return;

			$event->append('<div class="sf-set set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>'."\n");
			$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
		});

		$body = $this->delete(array('id' => '5'));

		$this->assertStringContainsString('<strong>poster&lt;3&gt;</strong></span> <span>probed</span></h3>', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Delete post</strong></legend>\n<div class=\"sf-set set1\"><input id=\"fld1\" /></div>\n\t\t\t\t<div class=\"sf-set set2\">", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld2" name="req_confirm"', $body);
	}

	public function testACancelSendsTheVisitorBackToThePost(): void {
		$response = $this->delete(array('id' => '5'), array('cancel' => '1'));

		$this->assertStringStartsWith('302 /post/5?a=1&b=2 [redirect]', $response);
		$this->assertStringContainsString('<span>Operation cancelled. Redirecting…</span>', $response);
		$this->assertNotContains('remove post 5 9 4', $this->log);
	}

	public function testASubmissionWithoutConfirmationDeletesNothing(): void {
		$response = $this->delete(array('id' => '5'), array('delete' => '1'));

		$this->assertStringContainsString('No confirmation provided. Operation cancelled.', $response);
		$this->assertSame(array('find 5 3'), $this->log);
		$this->assertContains('PostDeletionStep', $this->kit->events->dispatched);
	}

	public function testAConfirmedTopicDeletionSendsTheVisitorToTheForum(): void {
		$this->kit->visitor->administrator = true;
		$steps = array();
		$this->kit->events->observe(PostDeletionStep::class, function (PostDeletionStep $event) use (&$steps): void {
			$steps[] = $event->step();
		});

		$response = $this->delete(array('id' => '1'), array('delete' => '1', 'req_confirm' => '1'));

		$this->assertStringStartsWith('302 /forum/4/slug-forum-4-?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('find 1 3', 'remove topic 9 4'), $this->log);
		$this->assertSame(array('Topic deleted.'), $this->kit->flash->info);
		$this->assertSame(array('selected', 'submitted', 'topic_deleted'), $steps);
	}

	public function testAConfirmedPostDeletionSendsTheVisitorToThePostBeforeIt(): void {
		$this->posts->previous = 4;
		$previous = null;
		$this->kit->events->observe(PostDeletionStep::class, function (PostDeletionStep $event) use (&$previous): void {
			$previous = $event->previousPostId();
		});

		$response = $this->delete(array('id' => '5'), array('delete' => '1', 'req_confirm' => '1'));

		$this->assertStringStartsWith('302 /post/4?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('find 5 3', 'remove post 5 9 4', 'previous 9 5'), $this->log);
		$this->assertSame(array('Post deleted.'), $this->kit->flash->info);
		$this->assertSame(4, $previous);
	}

	public function testThePostDeletionOfTheLastReplySendsTheVisitorToTheTopic(): void {
		$this->assertStringStartsWith('302 /topic/9/slug-topic-9-?a=1&b=2 [redirect]', $this->delete(array('id' => '5'), array('delete' => '1', 'req_confirm' => '1')));
	}
}
