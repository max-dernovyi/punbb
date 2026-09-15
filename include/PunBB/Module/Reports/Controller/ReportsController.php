<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\ChromeInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Reports\Api\Data\ReportInterface;
use PunBB\Module\Reports\Api\ReportsInterface;
use PunBB\Module\Reports\Event\ReportAssembling;
use PunBB\Module\Reports\Event\ReportMarkingStep;
use PunBB\Module\Reports\Event\ReportsRendering;
use PunBB\Module\Reports\Event\ReportsRequested;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/reports.php: the reports nobody marked read, with a form to mark them,
 * and the ten marked read last, for administrators and moderators.
 */
final class ReportsController implements ControllerInterface {
	/** How many of the reports marked read the page lists. */
	public const READ_LISTED = 10;

	private const TEMPLATE = __DIR__.'/../templates/reports.phtml';

	/** Toggles every checkbox of the unread reports' form. */
	private const SELECT_ALL_SCRIPT = 'PUNBB.common.addDOMReadyEvent(PUNBB.common.initToggleCheckboxes);';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ReportsInterface $reports,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ReportsRequested());

		if (!$this->visitor->isModerating())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_reports');

		if (isset($request->post['mark_as_read']))
			return $this->markRead($request, $strings);

		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
		);
		if ($this->visitor->isAdministrator())
			$crumbs[] = new Crumb(self::string($common, 'Management')->html, $this->urls->link('admin_reports'));
		$crumbs[] = new Crumb(self::string($common, 'Reports')->html, $this->urls->link('admin_reports'));

		return $this->pages->respond(new PageHead('admin-reports', $crumbs, section: 'management'),
			fn (ChromeInterface $chrome): array => array('main' => $this->main($chrome, $common, $strings)));
	}

	/** @param array<string, Html> $strings */
	private function markRead(Request $request, array $strings): Response {
		$selected = $request->post['reports'] ?? null;
		if (!is_array($selected) || $selected === array())
			return $this->messages->respond(self::string($strings, 'No reports selected'), json: $request->xhr);

		$ids = array_map(intval(...), array_keys($selected));

		$this->events->dispatch(new ReportMarkingStep(ReportMarkingStep::SUBMITTED, $ids));

		$this->reports->markRead($ids, $this->visitor->id(), time());
		$this->flash->info(self::string($strings, 'Reports marked read'));

		$this->events->dispatch(new ReportMarkingStep(ReportMarkingStep::MARKED, $ids));

		return $this->redirects->respond($this->urls->link('admin_reports')->html, self::string($strings, 'Reports marked read'), $request->xhr);
	}

	/**
	 * The unread reports, those read last, or the note that there are none.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function main(ChromeInterface $chrome, array $common, array $strings): Html {
		$start = new ReportsRendering(ReportsRendering::MAIN_OUTPUT_START);
		$this->events->dispatch($start);

		$itemCount = 0;
		$fieldCount = 0;

		$unread = array();
		foreach ($this->reports->unread() as $number => $report)
			$unread[] = $this->block($report, $number + 1, $strings, $itemCount, $fieldCount);

		$read = $this->reports->recentlyRead(self::READ_LISTED);
		$readBlocks = array();
		if ($read !== array())
		{
			// The list of reports marked read numbers its blocks from one again
			$itemCount = 0;
			foreach ($read as $number => $report)
				$readBlocks[] = $this->block($report, $number + 1, $strings, $itemCount, $fieldCount);
		}

		$action = $this->urls->link('admin_reports');

		$body = $this->templates->render(self::TEMPLATE, array(
			'arp'		=> $strings,
			'selectAll'	=> self::string($common, 'Select all'),
			'unread'	=> $unread,
			'read'		=> $readBlocks,
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html.'?action=zap'),
		));

		$chrome->inlineScript(self::SELECT_ALL_SCRIPT);

		$end = new ReportsRendering(ReportsRendering::END, $unread !== array(), $readBlocks !== array());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * A report's block: its parts and its numbers as observers left them.
	 *
	 * @param array<string, Html> $strings
	 * @return array<string, mixed> what the template shows of the block
	 */
	private function block(ReportInterface $report, int $number, array $strings, int &$itemCount, int &$fieldCount): array {
		$parts = array(
			'reporter'	=> $report->reporter() !== null && $report->reporter() !== ''
				? Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($report->reporterId())), $report->reporter())->html
				: self::string($strings, 'Deleted user')->html,
			'forum'		=> $report->forumName() !== null && $report->forumName() !== ''
				? Html::format('<a href="%s">%s</a>', $this->urls->link('forum', array($report->forumId(), $this->urls->slug($report->forumName()))), $report->forumName())->html
				: self::string($strings, 'Deleted forum')->html,
			'topic'		=> $report->subject() !== null && $report->subject() !== ''
				? Html::format('<a href="%s">%s</a>', $this->urls->link('topic', array($report->topicId(), $this->urls->slug($report->subject()))), $report->subject())->html
				: self::string($strings, 'Deleted topic')->html,
			'message'	=> str_replace("\n", '<br />', Html::escape($report->message())->html),
			'post'		=> $report->postId() !== null
				? Html::format('<a href="%s">%s</a>', $this->urls->link('post', array($report->postId())), Html::format(self::string($strings, 'Post'), $report->postId()))->html
				: self::string($strings, 'Deleted post')->html,
		);

		if ($report->zapped() !== null)
			$parts['zapped_by'] = $report->zappedBy() !== null && $report->zappedBy() !== ''
				? Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($report->zappedById() ?? 0)), $report->zappedBy())->html
				: self::string($strings, 'Deleted user')->html;

		$assembling = new ReportAssembling(ReportAssembling::PARTS, $report, $number, $parts, $itemCount, $fieldCount);
		$this->events->dispatch($assembling);

		$itemCount = $assembling->itemCount() + 1;
		$block = array(
			'before'	=> new Html($assembling->markup()),
			'id'		=> $report->id(),
			'item'		=> $itemCount,
			'number'	=> $number,
			'by'		=> Html::format(self::string($strings, 'Reported by'), self::part($assembling, 'reporter')),
			'created'	=> $this->formatter->time($report->created(), TimeFormat::DateTime),
			'forum'		=> self::part($assembling, 'forum'),
			'topic'		=> self::part($assembling, 'topic'),
			'post'		=> self::part($assembling, 'post'),
			'message'	=> self::part($assembling, 'message'),
		);

		if ($report->zapped() !== null)
			$block['marked'] = Html::format(self::string($strings, 'Marked read by'), $this->formatter->time($report->zapped(), TimeFormat::DateTime), self::part($assembling, 'zapped_by'));
		else
			$block['field'] = $fieldCount = $assembling->fieldCount() + 1;

		$end = new ReportAssembling(ReportAssembling::BLOCK_END, $report, $number, array(), $itemCount, $fieldCount);
		$this->events->dispatch($end);

		$itemCount = $end->itemCount();
		$fieldCount = $end->fieldCount();
		$block['end'] = new Html($end->markup());

		return $block;
	}

	private static function part(ReportAssembling $event, string $name): Html {
		return new Html($event->entry($name) ?? '');
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
