<?php
/**
 * admin/reindex.php as a module, built with no forum: who may rebuild the
 * search index, the form starting a rebuild and its numbers as observers add
 * fields, and a cycle: its link token, the confirmation a stale link gets,
 * emptying the index, the posts it indexes and where it sends the browser.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Reindex\Api\IndexablePostsInterface;
use PunBB\Module\Reindex\Controller\ReindexController;
use PunBB\Module\Reindex\Event\ReindexCycleStep;
use PunBB\Module\Reindex\Event\ReindexRendering;
use PunBB\Module\Reindex\Event\ReindexRequested;
use PunBB\Module\Reindex\Indexing\SearchIndexInterface;
use PunBB\Module\Reindex\Model\IndexablePost;

require_once __DIR__.'/PageFakes.php';

final class FakeIndexablePosts implements IndexablePostsInterface {
	/** @var list<IndexablePost> */
	public array $posts = array();

	/** @param list<string> $log */
	public function __construct(private array &$log) {}

	public function firstId(): ?int {
		return $this->posts !== array() ? $this->posts[0]->id() : null;
	}

	public function batch(int $startAt, int $limit): array {
		$this->log[] = 'batch '.$startAt.' '.$limit;

		return array_slice(array_values(array_filter($this->posts, static fn (IndexablePost $post): bool => $post->id() >= $startAt)), 0, $limit);
	}

	public function nextId(int $postId): ?int {
		foreach ($this->posts as $post)
			if ($post->id() > $postId)
				return $post->id();

		return null;
	}
}

final class FakeSearchIndex implements SearchIndexInterface {
	/** @param list<string> $log */
	public function __construct(private array &$log) {}

	public function clear(): void {
		$this->log[] = 'clear';
	}

	public function index(int $postId, string $message, ?string $subject): void {
		$this->log[] = 'index '.$postId.' '.$message.($subject !== null ? ' / '.$subject : '');
	}
}

class ReindexControllerTest extends TestCase {
	private PageKit $kit;

	private FakeIndexablePosts $posts;

	/** @var list<string> */
	private array $log = array();

	protected function setUp(): void {
		$this->kit = new PageKit(array(ReindexRequested::class, ReindexCycleStep::class, ReindexRendering::class, MessageShowing::class, MessageRendering::class, ConfirmFormRequested::class, ConfirmFormRendering::class));
		$this->kit->language->real = array('admin_common', 'admin_reindex', 'common');
		$this->kit->visitor->administrator = true;
		$this->posts = new FakeIndexablePosts($this->log);
		$this->posts->posts = array(new IndexablePost(4, 'first <b>', 1, 'Topic', 4), new IndexablePost(5, 'reply', 1, 'Topic', 4), new IndexablePost(9, 'other', 2, 'Other & co', 9));
	}

	private function page(array $query = array(), array $post = array()): string {
		$confirmations = new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens);
		$controller = new ReindexController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $confirmations, $this->posts,
			new FakeSearchIndex($this->log), $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/reindex.php', $query, $post));

		return $response->status.' '.implode(',', array_keys($response->headers)).' '.$response->body;
	}

	private function token(): string {
		return $this->kit->tokens->token('reindex3');
	}

	public function testOnlyAnAdministratorMayRebuild(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->moderating = true;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('i_per_page' => '1', 'i_start_at' => '1', 'csrf_token' => $this->token())));
		$this->assertSame(array(), $this->log);
	}

	public function testTheFormStartsAtTheFirstPostWithTheAdministratorsToken(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-reindex', 'management'), array($head->id, $head->section));
		$this->assertSame(array('Board & Co', 'Administration', 'Management', 'Rebuild index'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertSame('/admin_reports?a=1&amp;b=2', $head->crumbs[2]->link?->html);

		$this->assertStringStartsWith("200 Expires,Last-Modified,Cache-Control,Pragma,Content-type [admin-reindex]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Rebuild search index to restore search performance</span></h2>", $body);
		$this->assertStringContainsString("<form class=\"frm-form\" method=\"get\" accept-charset=\"utf-8\" action=\"/admin_reindex?a=1&amp;b=2\">\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"".$this->token().'" />', $body);
		$this->assertStringContainsString('<fieldset class="frm-group group1">', $body);
		$this->assertStringContainsString('<input type="number" id="fld2" name="i_start_at" size="7" maxlength="7" value="4" />', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="i_empty_index" value="1" checked="checked" />', $body);
		$this->assertStringEndsWith("</form>\n\t</div>", $body);
		$this->assertSame(array('ReindexRequested', 'ReindexRendering:main_output_start', 'ReindexRendering:pre_rebuild_fieldset', 'ReindexRendering:pre_rebuild_per_page', 'ReindexRendering:pre_rebuild_start_post',
			'ReindexRendering:pre_rebuild_empty_index', 'ReindexRendering:pre_rebuild_fieldset_end', 'ReindexRendering:rebuild_fieldset_end', 'ReindexRendering:end'), $this->kit->events->dispatched);

		$this->posts->posts = array();
		$this->assertStringContainsString('name="i_start_at" size="7" maxlength="7" value="0" />', $this->page());
	}

	public function testTheFormNumbersOnFromTheFieldsObserversAdd(): void {
		$this->kit->events->observe(ReindexRendering::class, function (ReindexRendering $event): void {
			if ($event->position() !== ReindexRendering::PRE_REBUILD_START_POST)
				return;

			$event->append('<div class="sf-set set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>'."\n");
			$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
		});

		$body = $this->page();

		$this->assertStringContainsString("<div class=\"sf-set set2\"><input id=\"fld2\" /></div>\n\t\t\t\t<div class=\"sf-set set3\">", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld4" name="i_empty_index"', $body);
	}

	public function testACycleEmptiesTheIndexIndexesItsBatchAndSendsTheBrowserToTheNext(): void {
		$steps = array();
		$this->kit->events->observe(ReindexCycleStep::class, function (ReindexCycleStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->perCycle().' '.$event->startAt().' '.(int) $event->emptying().' '.$event->lastPostId().' '.var_export($event->nextPostId(), true).' after '.count($this->log);
			$event->append('<!-- '.$event->step().' -->');
		});

		$body = $this->page(array('i_per_page' => '2', 'i_start_at' => '1', 'i_empty_index' => '1', 'csrf_token' => $this->token()));

		$this->assertSame(array('clear', 'batch 1 2', 'index 4 first <b> / Topic', 'index 5 reply'), $this->log);
		$this->assertSame(array('start 2 1 1 0 NULL after 0', 'end 2 1 1 5 9 after 4'), $steps);
		$this->assertSame(array(), $this->kit->chromes->opened);

		$next = '/admin_reindex?a=1&b=2?i_per_page=2&i_start_at=9&csrf_token='.$this->token();
		$this->assertStringStartsWith("200  <!-- start --><!DOCTYPE html>\n<html lang=\"en\" dir=\"ltr\">\n<head>", $body);
		$this->assertStringContainsString('<title>Rebuilding search index… — Management — Administration — Board &amp; Co</title>', $body);
		$this->assertStringContainsString("<p>Rebuilding index… This might be a good time to put on some coffee :-)</p>\n\n<p>Processing post <strong>4</strong> in topic <strong>1</strong>.<br />\nProcessing post <strong>5</strong> in topic <strong>1</strong>.<br />\n</p>", $body);
		$this->assertStringContainsString('</p><!-- end --><script type="text/javascript">window.location="'.str_replace(array('/', '&'), array('\/', '\u0026'), $next).'"</script>', $body);
		$this->assertStringEndsWith('<br />JavaScript redirect unsuccessful. <a href="'.htmlspecialchars($next).'">Click here to continue</a>.'."\n", $body);
	}

	public function testTheLastCycleSendsTheBrowserBackToTheForm(): void {
		$body = $this->page(array('i_per_page' => '100', 'i_start_at' => '5', 'csrf_token' => $this->token()));

		$this->assertSame(array('batch 5 100', 'index 5 reply', 'index 9 other / Other & co'), $this->log);
		$this->assertStringContainsString('window.location="\/admin_reindex?a=1\u0026b=2"', $body);
	}

	public function testACycleWithoutItsTokenIsConfirmedFirst(): void {
		$body = $this->page(array('i_per_page' => '100', 'i_start_at' => '1', 'csrf_token' => 'stale'));

		$this->assertStringContainsString('[dialogue]', $body);
		$this->assertSame(array(), $this->log);

		$this->kit->events->observe(ConfirmFormRequested::class, fn (ConfirmFormRequested $event) => $event->letThrough());
		$this->page(array('i_per_page' => '100', 'i_start_at' => '9'));
		$this->assertSame(array('batch 9 100', 'index 9 other / Other & co'), $this->log);
	}

	public function testATokenPostedWithTheCycleWasCheckedOnTheWayIn(): void {
		$this->page(array('i_per_page' => '100', 'i_start_at' => '9'), array('csrf_token' => 'checked by the gate'));

		$this->assertSame(array('batch 9 100', 'index 9 other / Other & co'), $this->log);
	}

	public function testACycleOutsideThePostsIsABadRequest(): void {
		foreach (array(array('i_per_page' => '0', 'i_start_at' => '1'), array('i_per_page' => '10', 'i_start_at' => '-1'), array('i_per_page' => array('5'), 'i_start_at' => '1')) as $query)
			$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page($query + array('csrf_token' => $this->token())));

		$this->assertSame(array(), $this->log);
	}
}
