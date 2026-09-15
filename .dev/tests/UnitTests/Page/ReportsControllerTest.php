<?php
/**
 * admin/reports.php as a module, built with no forum: who may see it, the
 * unread reports in their form and the reports read last, what is left of a
 * report whose post, topic, forum or members were deleted, the numbers of the
 * blocks and checkboxes as observers add some, and marking reports read.
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
use PunBB\Module\Reports\Api\ReportsInterface;
use PunBB\Module\Reports\Controller\ReportsController;
use PunBB\Module\Reports\Event\ReportAssembling;
use PunBB\Module\Reports\Event\ReportMarkingStep;
use PunBB\Module\Reports\Event\ReportsRendering;
use PunBB\Module\Reports\Event\ReportsRequested;
use PunBB\Module\Reports\Model\Report;

require_once __DIR__.'/PageFakes.php';

final class FakeReports implements ReportsInterface {
	/** @var list<Report> */
	public array $unread = array();

	/** @var list<Report> */
	public array $read = array();

	/** @var list<string> */
	public array $log = array();

	public function unread(): array {
		$this->log[] = 'unread';

		return $this->unread;
	}

	public function recentlyRead(int $limit): array {
		$this->log[] = 'read '.$limit;

		return $this->read;
	}

	public function markRead(array $reportIds, int $userId, int $now): void {
		$this->log[] = 'mark '.implode(',', $reportIds).' by '.$userId;
	}
}

class ReportsControllerTest extends TestCase {
	private PageKit $kit;

	private FakeReports $reports;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ReportsRequested::class, ReportMarkingStep::class, ReportsRendering::class, ReportAssembling::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_reports', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->visitor->moderating = true;
		$this->reports = new FakeReports();

		$this->reports->unread = array(
			new Report(7, 30, 3, 'Topic <3>', 1, 'Forum & 1', 5, 'reporter<5>', 2000, "Spam\n<b>here</b>"),
			new Report(6, null, 4, null, 2, null, 9, null, 1500, 'Gone'),
		);
		$this->reports->read = array(new Report(2, 10, 3, 'Topic <3>', 1, 'Forum & 1', 5, 'reporter<5>', 1000, 'Old', 1100, 2, 'admin'));
	}

	private function page(array $post = array()): string {
		$controller = new ReportsController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $this->reports,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/reports.php', array(), $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testAVisitorWhoModeratesNothingGetsAMessage(): void {
		$this->kit->visitor->moderating = false;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page());
		$this->assertSame(array(), $this->reports->log);
	}

	public function testTheUnreadReportsAreListedInAFormAndTheReadOnesBelow(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('admin-reports', $head->id);
		$this->assertSame('management', $head->section);
		$this->assertSame(array('Board & Co', 'Administration', 'Reports'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringStartsWith("200  [admin-reports]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>New reports (select and mark as read once dealt with)</span></h2>", $body);
		$this->assertStringContainsString('<form id="arp-new-report-form" class="frm-form" method="post" accept-charset="utf-8" action="/admin_reports?a=1&amp;b=2?action=zap">', $body);
		$this->assertStringContainsString('<input type="hidden" name="csrf_token" value="token-for-'.md5('/admin_reports?a=1&amp;b=2?action=zap').'" />', $body);
		$this->assertStringContainsString("<div class=\"ct-set warn-set report set1\">\n\t\t\t\t<div class=\"ct-box warn-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\"><strong>1</strong> <cite class=\"username\">By <a href=\"/user/5?a=1&amp;b=2\">reporter&lt;5&gt;</a></cite> <span><time>2000</time></span></h3>", $body);
		$this->assertStringContainsString('<h4 class="hn"><a href="/forum/1/slug-forum-1?a=1&amp;b=2">Forum &amp; 1</a> &rarr; <a href="/topic/3/slug-topic-3-?a=1&amp;b=2">Topic &lt;3&gt;</a> &rarr; <a href="/post/30?a=1&amp;b=2">Post #30</a></h4>', $body);
		$this->assertStringContainsString('<p>Spam<br />&lt;b&gt;here&lt;/b&gt;</p>', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld1" name="reports[7]" value="1" /> <label for="fld1">Select report</label>', $body);
		$this->assertStringContainsString('<strong>2</strong> <cite class="username">By Deleted user</cite>', $body);
		$this->assertStringContainsString('<h4 class="hn">Deleted forum &rarr; Deleted topic &rarr; Deleted post</h4>', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld2" name="reports[6]"', $body);

		$this->assertStringContainsString("<h2 class=\"hn\"><span>Last 10 reports marked as read</span></h2>\n\t</div>\n\t<div class=\"main-content main-frm\">\n\t\t\t<div class=\"ct-set report data-set set1\">", $body);
		$this->assertStringContainsString('<p>Old <strong>Read <time>1100</time> by <a href="/user/2?a=1&amp;b=2">admin</a></strong></p>', $body);
		$this->assertStringEndsWith("</div>\n\t\t\t</div>\n\t</div>", $body);

		$this->assertSame(array('unread', 'read 10'), $this->reports->log);
		$this->assertSame(array('PUNBB.common.addDOMReadyEvent(PUNBB.common.initToggleCheckboxes);'), $this->kit->chromes->scripts);
		$this->assertSame(array('ReportsRequested', 'ReportsRendering:main_output_start', 'ReportAssembling', 'ReportAssembling', 'ReportAssembling', 'ReportAssembling',
			'ReportAssembling', 'ReportAssembling', 'ReportsRendering:end'), $this->kit->events->dispatched);
	}

	public function testAnAdministratorsCrumbsPassThroughManagement(): void {
		$this->kit->visitor->administrator = true;
		$this->page();

		$this->assertSame(array('Board & Co', 'Administration', 'Management', 'Reports'), array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs));
	}

	public function testWithoutReportsThePageSaysSo(): void {
		$this->reports->unread = $this->reports->read = array();

		$body = $this->page();

		$this->assertStringContainsString('<h2 class="hn"><span>Reports are empty</span></h2>', $body);
		$this->assertStringContainsString('<p>There are no reports either read or unread for you to view.</p>', $body);
		$this->assertStringNotContainsString('<form', $body);
	}

	public function testObserversChangeTheBlocksAndTheirNumbers(): void {
		$ends = array();
		$this->kit->events->observe(ReportAssembling::class, function (ReportAssembling $event) use (&$ends): void {
			if ($event->stage() === ReportAssembling::PARTS && $event->number() === 1 && !$event->isRead())
			{
				$event->set('message', '<em>probed</em>');
				$event->append('<div class="set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>');
				$event->count($event->itemCount() + 1, $event->fieldCount() + 1);
			}

			if ($event->stage() === ReportAssembling::BLOCK_END)
				$ends[] = ($event->isRead() ? 'read ' : 'unread ').$event->report()->id().' at '.$event->itemCount().'/'.$event->fieldCount();
		});
		$this->kit->events->observe(ReportsRendering::class, function (ReportsRendering $event): void {
			$event->append('<!--'.$event->position().($event->position() === ReportsRendering::END ? ' '.(int) $event->listsUnread().(int) $event->listsRead() : '').'-->');
		});

		$body = $this->page();

		$this->assertStringStartsWith('200  [admin-reports]<!--main_output_start-->', $body);
		$this->assertStringContainsString("<div class=\"set1\"><input id=\"fld1\" /></div>\t\t\t<div class=\"ct-set warn-set report set2\">", $body);
		$this->assertStringContainsString('<p><em>probed</em></p>', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld2" name="reports[7]"', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="reports[6]"', $body);
		$this->assertStringEndsWith('<!--end 11-->', $body);
		$this->assertSame(array('unread 7 at 2/2', 'unread 6 at 3/3', 'read 2 at 1/3'), $ends);
	}

	public function testReportsAreMarkedReadAndTheVisitorSentBackToTheList(): void {
		$steps = array();
		$this->kit->events->observe(ReportMarkingStep::class, function (ReportMarkingStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.implode(',', $event->reportIds()).' '.implode(',', $this->reports->log);
		});

		$response = $this->page(array('mark_as_read' => '1', 'reports' => array('7' => '1', '6x' => '1')));

		$this->assertStringStartsWith('302 /admin_reports?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('mark 7,6 by 3'), $this->reports->log);
		$this->assertSame(array('Reports marked as read.'), $this->kit->flash->info);
		$this->assertSame(array('submitted 7,6 ', 'marked 7,6 mark 7,6 by 3'), $steps);
	}

	public function testMarkingWithoutASelectionMarksNothing(): void {
		foreach (array(array('mark_as_read' => '1'), array('mark_as_read' => '1', 'reports' => '7'), array('mark_as_read' => '1', 'reports' => array())) as $post)
			$this->assertStringContainsString('<p>No reports were selected to be marked as read.</p>', $this->page($post));

		$this->assertSame(array(), $this->reports->log);
	}
}
