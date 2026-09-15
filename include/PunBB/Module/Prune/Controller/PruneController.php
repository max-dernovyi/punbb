<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Prune\Api\PrunableTopicsInterface;
use PunBB\Module\Prune\Event\ForumOptionRendering;
use PunBB\Module\Prune\Event\PruneRendering;
use PunBB\Module\Prune\Event\PruneRequested;
use PunBB\Module\Prune\Event\PruningStep;
use PunBB\Module\Prune\Pruning\TopicPruningInterface;
use PunBB\Module\Prune\View\PruneView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/prune.php: the form choosing the forum and the age of the topics to
 * prune, the confirmation naming how many topics that takes, and the prune.
 */
final class PruneController implements ControllerInterface {
	private const DAY = 86400;

	private const FORM = __DIR__.'/../templates/prune.phtml';

	private const CONFIRM = __DIR__.'/../templates/confirm.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly PrunableTopicsInterface $topics,
		private readonly TopicPruningInterface $pruning,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new PruneRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_prune');

		if (isset($request->post['prune_comply']))
			return $this->prune($request, $strings);

		if (isset($request->query['action']) || isset($request->post['prune']))
			return $this->confirm($request, $common, $strings);

		return $this->form($common, $strings);
	}

	/** @param array<string, Html> $strings */
	private function prune(Request $request, array $strings): Response {
		// No default here: 'all' would prune every forum on a truncated POST
		if (!isset($request->post['prune_from']) || !is_string($request->post['prune_from']))
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$from = $request->post['prune_from'];
		$days = self::days($request->post['prune_days'] ?? 0);
		$sticky = !empty($request->post['prune_sticky']);
		$before = $days !== 0 ? time() - $days * self::DAY : null;

		$this->events->dispatch(new PruningStep(PruningStep::SUBMITTED, $from, $days, $sticky, $before));

		if (function_exists('set_time_limit'))
			set_time_limit(0);

		foreach ($from === 'all' ? $this->topics->forumIds() : array(intval($from)) as $forumId)
			$this->pruning->prune($forumId, $sticky, $before);

		$this->pruning->removeOrphans();
		$this->flash->info(self::string($strings, 'Prune done'));

		$this->events->dispatch(new PruningStep(PruningStep::PRUNED, $from, $days, $sticky, $before));

		return $this->redirects->respond($this->urls->link('admin_prune')->html, self::string($strings, 'Prune done'), $request->xhr);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function confirm(Request $request, array $common, array $strings): Response {
		if (!isset($request->post['req_prune_days'], $request->post['prune_from']))
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$days = self::days($request->post['req_prune_days']);
		if ($days < 0)
			return $this->messages->respond(self::string($strings, 'Days to prune message'), json: $request->xhr);

		$all = $request->post['prune_from'] === 'all';
		$forumId = $all ? null : self::integer($request->post['prune_from']);
		$sticky = isset($request->post['prune_sticky']);

		$forumName = $forumId !== null ? $this->topics->forumName($forumId) ?? '' : null;
		$count = $this->topics->count($forumId, time() - $days * self::DAY, $sticky);

		if ($count === 0)
			return $this->messages->respond(self::string($strings, 'No days old message'), json: $request->xhr);

		$action = $this->urls->link('admin_prune');

		$head = new PageHead('admin-prune', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Management')->html, $this->urls->link('admin_reports')),
			new Crumb(self::string($strings, 'Prune topics')->html, $action),
			new Crumb(self::string($strings, 'Confirm prune heading')->html),
		), section: 'management', view: 'confirm');

		$view = new PruneView(array(
			'apr'		=> $strings,
			'details'	=> Html::format(self::string($strings, 'Prune details head'), $forumName ?? self::string($strings, 'All forums')),
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html.'?action=foo'),
			'days'		=> $days,
			'sticky'	=> self::integer($request->post['prune_sticky'] ?? 0),
			'from'		=> $forumId ?? 'all',
			'count'		=> Html::format(self::string($strings, 'Prune topics info 1'), $count, $sticky ? Html::format(' (%s)', self::string($strings, 'Include sticky')) : ''),
			'age'		=> Html::format(self::string($strings, 'Prune topics info 2'), $days),
		));

		return $this->pages->respond($head, function () use ($view): array {
			$this->at($view, PruneRendering::COMPLY_OUTPUT_START);
			$this->at($view, PruneRendering::COMPLY_PRE_BUTTONS);

			return array('main' => $this->compose($view, self::CONFIRM, PruneRendering::COMPLY_OUTPUT_START, PruneRendering::COMPLY_END));
		});
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function form(array $common, array $strings): Response {
		$action = $this->urls->link('admin_prune');

		$head = new PageHead('admin-prune', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Management')->html, $this->urls->link('admin_reports')),
			new Crumb(self::string($common, 'Prune topics')->html, $action),
		), section: 'management');

		$view = new PruneView(array(
			'apr'		=> $strings,
			'required'	=> self::string($common, 'Required warn'),
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html.'?action=foo'),
		));

		return $this->pages->respond($head, function () use ($view): array {
			$this->at($view, PruneRendering::MAIN_OUTPUT_START);

			$this->at($view, PruneRendering::PRE_PRUNE_FIELDSET);
			$view->numberGroup('group');

			$this->at($view, PruneRendering::PRE_PRUNE_FROM);
			$view->numberItem('from_item');
			$view->numberField('from_field');
			$view->show('forums', $this->forumOptions());

			$this->at($view, PruneRendering::PRE_PRUNE_DAYS);
			$view->numberItem('days_item');
			$view->numberField('days_field');

			$this->at($view, PruneRendering::PRE_PRUNE_STICKY);
			$view->numberItem('sticky_item');
			$view->numberField('sticky_field');

			$this->at($view, PruneRendering::PRE_PRUNE_FIELDSET_END);
			$this->at($view, PruneRendering::PRUNE_FIELDSET_END);

			return array('main' => $this->compose($view, self::FORM, PruneRendering::MAIN_OUTPUT_START, PruneRendering::END));
		});
	}

	/** @return list<array<string, mixed>> each forum's option: the markup around it, and the category group it closes or opens */
	private function forumOptions(): array {
		$options = array();
		$category = 0;

		foreach ($this->topics->forums() as $forum)
		{
			$start = new ForumOptionRendering(ForumOptionRendering::START, $forum);
			$this->events->dispatch($start);

			$opens = $forum->categoryId() !== $category;
			$option = array(
				'start'		=> new Html($start->markup()),
				'closes'	=> $opens && $category !== 0,
				'opens'		=> $opens ? $forum->categoryName() : null,
				'id'		=> $forum->id(),
				'name'		=> $forum->name(),
			);

			if ($opens)
				$category = $forum->categoryId();

			$end = new ForumOptionRendering(ForumOptionRendering::END, $forum);
			$this->events->dispatch($end);

			$options[] = $option + array('end' => new Html($end->markup()));
		}

		return $options;
	}

	/** The page's main region: the markup at its start, its template, and the markup at its end. */
	private function compose(PruneView $view, string $template, string $start, string $endPosition): Html {
		$body = $this->templates->render($template, $view->variables());

		$end = $view->rendering($endPosition);
		$this->events->dispatch($end);

		return (new Html($view->markup($start)->html.$body.$end->markup()))->trim();
	}

	private function at(PruneView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/** A value the request carries, as intval() took it. */
	// Bounded so that $days * DAY cannot overflow into a float
	private static function days(mixed $value): int {
		$limit = intdiv(time(), self::DAY);

		return max(-$limit, min($limit, self::integer($value)));
	}

	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
