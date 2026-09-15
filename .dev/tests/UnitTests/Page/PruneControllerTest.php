<?php
/**
 * admin/prune.php as a module, built with no forum: who may prune, the form
 * listing the forums by category and numbering its fields as observers add
 * some, the confirmation naming what a prune takes, and the prune itself.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Prune\Api\PrunableTopicsInterface;
use PunBB\Module\Prune\Controller\PruneController;
use PunBB\Module\Prune\Event\ForumOptionRendering;
use PunBB\Module\Prune\Event\PruneRendering;
use PunBB\Module\Prune\Event\PruneRequested;
use PunBB\Module\Prune\Event\PruningStep;
use PunBB\Module\Prune\Model\PrunableForum;
use PunBB\Module\Prune\Pruning\TopicPruningInterface;

require_once __DIR__.'/PageFakes.php';

final class FakePrunableTopics implements PrunableTopicsInterface {
	public int $count = 3;

	/** @param list<string> $log */
	public function __construct(private array &$log) {}

	public function forums(): array {
		$this->log[] = 'forums';

		return array(new PrunableForum(1, 'Cat <1>', 1, 'Forum & 1'), new PrunableForum(1, 'Cat <1>', 2, 'Forum 2'), new PrunableForum(5, 'Cat 5', 7, 'Forum 7'));
	}

	public function forumIds(): array {
		return array(1, 2, 7);
	}

	public function forumName(int $forumId): ?string {
		return $forumId === 2 ? 'Forum <2>' : null;
	}

	public function count(?int $forumId, int $lastPostBefore, bool $sticky): int {
		$this->log[] = 'count '.var_export($forumId, true).' '.(time() - $lastPostBefore >= 86400 * 30 - 5 ? '30 days' : (time() - $lastPostBefore).'s').' '.(int) $sticky;

		return $this->count;
	}
}

final class FakeTopicPruning implements TopicPruningInterface {
	/** @param list<string> $log */
	public function __construct(private array &$log) {}

	public function prune(int $forumId, bool $sticky, ?int $lastPostBefore): void {
		$this->log[] = 'prune '.$forumId.' '.(int) $sticky.' '.($lastPostBefore === null ? 'any age' : 'dated');
	}

	public function removeOrphans(): void {
		$this->log[] = 'orphans';
	}
}

class PruneControllerTest extends TestCase {
	private PageKit $kit;

	private FakePrunableTopics $topics;

	/** @var list<string> */
	private array $log = array();

	protected function setUp(): void {
		$this->kit = new PageKit(array(PruneRequested::class, PruningStep::class, PruneRendering::class, ForumOptionRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_prune', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->visitor->administrator = true;
		$this->topics = new FakePrunableTopics($this->log);
	}

	private function page(array $query = array(), array $post = array()): string {
		$controller = new PruneController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $this->topics,
			new FakeTopicPruning($this->log), $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/prune.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testOnlyAnAdministratorMayPrune(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->moderating = true;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array(), array('prune_comply' => '1', 'prune_from' => 'all')));
		$this->assertSame(array(), $this->log);
	}

	public function testTheFormListsTheForumsByCategory(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-prune', 'management', null), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Management', 'Prune topics'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringStartsWith("200  [admin-prune]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Prune topics according to age of latest post and forum</span></h2>", $body);
		$this->assertStringContainsString("<form class=\"frm-form\" method=\"post\" accept-charset=\"utf-8\" action=\"/admin_prune?a=1&amp;b=2?action=foo\">\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_prune?a=1&amp;b=2?action=foo')."\" />", $body);
		$this->assertStringContainsString("<span class=\"fld-input\"><select id=\"fld1\" name=\"prune_from\">\n\t\t\t\t\t\t\t<option value=\"all\">All forums</option>\n".
			"\t\t\t\t\t\t\t\t<optgroup label=\"Cat &lt;1&gt;\">\n\t\t\t\t\t\t\t\t\t<option value=\"1\">Forum &amp; 1</option>\n\t\t\t\t\t\t\t\t\t<option value=\"2\">Forum 2</option>\n".
			"\t\t\t\t\t\t\t\t</optgroup>\n\t\t\t\t\t\t\t\t<optgroup label=\"Cat 5\">\n\t\t\t\t\t\t\t\t\t<option value=\"7\">Forum 7</option>\n\t\t\t\t\t\t</optgroup>\n\t\t\t\t\t\t</select></span>", $body);
		$this->assertStringContainsString('<input type="number" id="fld2" name="req_prune_days" size="4" maxlength="4" required />', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="prune_sticky" value="1" checked="checked" />', $body);
		$this->assertStringEndsWith("</form>\n\t</div>", $body);
		$this->assertSame(array('PruneRequested', 'PruneRendering:main_output_start', 'PruneRendering:pre_prune_fieldset', 'PruneRendering:pre_prune_from',
			'ForumOptionRendering:start', 'ForumOptionRendering:end', 'ForumOptionRendering:start', 'ForumOptionRendering:end', 'ForumOptionRendering:start', 'ForumOptionRendering:end',
			'PruneRendering:pre_prune_days', 'PruneRendering:pre_prune_sticky', 'PruneRendering:pre_prune_fieldset_end', 'PruneRendering:prune_fieldset_end', 'PruneRendering:end'), $this->kit->events->dispatched);
	}

	public function testObserversAddMarkupAroundTheOptionsAndFieldsTheFormCounts(): void {
		$this->kit->events->observe(ForumOptionRendering::class, function (ForumOptionRendering $event): void {
			$event->append('<!-- '.$event->position().' '.$event->forum()->id().' -->'."\n");
		});
		$this->kit->events->observe(PruneRendering::class, function (PruneRendering $event): void {
			if ($event->position() !== PruneRendering::PRE_PRUNE_DAYS)
				return;

			$event->append('<div class="sf-set set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>'."\n");
			$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
		});

		$body = $this->page();

		$this->assertStringContainsString("<option value=\"all\">All forums</option>\n<!-- start 1 -->\n\t\t\t\t\t\t\t\t<optgroup label=\"Cat &lt;1&gt;\">\n\t\t\t\t\t\t\t\t\t<option value=\"1\">Forum &amp; 1</option>\n<!-- end 1 -->\n<!-- start 2 -->", $body);
		$this->assertStringContainsString("<!-- end 7 -->\n\t\t\t\t\t\t</optgroup>", $body);
		$this->assertStringContainsString("<div class=\"sf-set set2\"><input id=\"fld2\" /></div>\n\t\t\t\t<div class=\"sf-set set3\">", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld4" name="prune_sticky"', $body);
	}

	public function testTheConfirmationNamesTheForumAndHowManyTopicsGo(): void {
		$body = $this->page(array('action' => 'foo'), array('prune' => '1', 'req_prune_days' => '30', 'prune_from' => '2', 'prune_sticky' => '1'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-prune', 'confirm'), array($head->id, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Management', 'Prune topics', 'Confirm prune topics'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertNull($head->crumbs[4]->link);

		$this->assertStringStartsWith("200  [admin-prune]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Confirm prune topics from: Forum &lt;2&gt;</span></h2>", $body);
		$this->assertStringContainsString("<input type=\"hidden\" name=\"prune_days\" value=\"30\" />\n\t\t\t\t<input type=\"hidden\" name=\"prune_sticky\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"prune_from\" value=\"2\" />", $body);
		$this->assertStringContainsString('<p class="warn"><span><strong>WARNING!</strong> Pruning will permanently delete <em>3</em> topics (including sticky topics).</span></p>', $body);
		$this->assertStringContainsString('<p class="warn"><span>The topics being deleted do not contain posts newer than <em>30</em> days old.</span></p>', $body);
		$this->assertStringContainsString('<input type="submit" name="prune_comply" value="Prune topics" />', $body);
		$this->assertSame(array('count 2 30 days 1'), $this->log);
		$this->assertSame(array('PruneRequested', 'PruneRendering:comply_output_start', 'PruneRendering:comply_pre_buttons', 'PruneRendering:comply_end'), $this->kit->events->dispatched);
	}

	public function testConfirmingEveryForumWithoutStickyTopics(): void {
		$body = $this->page(array('action' => 'foo'), array('req_prune_days' => '0', 'prune_from' => 'all'));

		$this->assertStringContainsString('<span>Confirm prune topics from: All forums</span>', $body);
		$this->assertStringContainsString('<input type="hidden" name="prune_sticky" value="0" />', $body);
		$this->assertStringContainsString('<input type="hidden" name="prune_from" value="all" />', $body);
		$this->assertStringContainsString('<em>3</em> topics.</span>', $body);
		$this->assertMatchesRegularExpression('/^count NULL [0-9]s 0$/', $this->log[0]);
	}

	public function testAConfirmationWithoutItsFieldsOrItsTopicsIsRefused(): void {
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('action' => 'foo')));
		$this->assertStringContainsString('<p>Days to prune must be a positive integer.</p>', $this->page(array(), array('prune' => '1', 'req_prune_days' => '-1', 'prune_from' => 'all')));

		$this->topics->count = 0;
		$this->assertStringContainsString('<p>There are no topics that are as old as you have specified.', $this->page(array(), array('prune' => '1', 'req_prune_days' => '1', 'prune_from' => '9')));
		$this->assertSame(array('message', 'message', 'message'), array_map(static fn ($head): string => $head->id, $this->kit->chromes->opened));
	}

	public function testAConfirmedPruneOfEveryForumPrunesEachThenTheOrphans(): void {
		$steps = array();
		$this->kit->events->observe(PruningStep::class, function (PruningStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->from().' '.$event->days().' '.(int) $event->sticky().' '.($event->lastPostBefore() === null ? 'any age' : 'dated').' after '.count($this->log);
		});

		$response = $this->page(array('action' => 'foo'), array('prune_comply' => '1', 'prune_from' => 'all', 'prune_days' => '0', 'prune_sticky' => '0'));

		$this->assertStringStartsWith('302 /admin_prune?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('prune 1 0 any age', 'prune 2 0 any age', 'prune 7 0 any age', 'orphans'), $this->log);
		$this->assertSame(array('Posts pruned.'), $this->kit->flash->info);
		$this->assertSame(array('submitted all 0 0 any age after 0', 'pruned all 0 0 any age after 4'), $steps);
	}

	public function testAConfirmedPruneOfOneForumPrunesItAlone(): void {
		$this->page(array(), array('prune_comply' => '1', 'prune_from' => '2', 'prune_days' => '10', 'prune_sticky' => '1'));

		$this->assertSame(array('prune 2 1 dated', 'orphans'), $this->log);
	}

	public function testAnOverflowingDayCountIsBoundedInsteadOfFailing(): void {
		$this->page(array(), array('prune' => '1', 'req_prune_days' => '99999999999999999999', 'prune_from' => 'all'));
		$this->page(array(), array('prune_comply' => '1', 'prune_from' => '2', 'prune_days' => '-99999999999999999999'));

		$this->assertSame(array('count NULL 30 days 0', 'prune 2 0 dated', 'orphans'), $this->log);
	}

	/** No default: 'all' would prune every forum on a truncated POST. */
	public function testAPruneWithoutItsForumPrunesNothing(): void {
		foreach (array(array('prune_comply' => '1'), array('prune_comply' => '1', 'prune_from' => array('all'))) as $post)
			$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array(), $post));

		$this->assertSame(array(), $this->log);
	}
}
