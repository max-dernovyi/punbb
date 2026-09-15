<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Reindex\Api\IndexablePostsInterface;
use PunBB\Module\Reindex\Event\ReindexCycleStep;
use PunBB\Module\Reindex\Event\ReindexRendering;
use PunBB\Module\Reindex\Event\ReindexRequested;
use PunBB\Module\Reindex\Indexing\SearchIndexInterface;
use PunBB\Module\Reindex\View\RebuildFormView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/reindex.php: the form starting a rebuild of the search index, and each
 * cycle of the rebuild, which indexes a batch of posts and sends the browser
 * on to the next. A cycle is a link, so it carries a token of its own.
 */
final class ReindexController implements ControllerInterface {
	private const FORM = __DIR__.'/../templates/reindex.phtml';

	private const CYCLE = __DIR__.'/../templates/cycle.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly ConfirmPage $confirmations,
		private readonly IndexablePostsInterface $posts,
		private readonly SearchIndexInterface $index,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ReindexRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_reindex');

		if (isset($request->query['i_per_page'], $request->query['i_start_at']))
			return $this->cycle($request, $common, $strings);

		$firstId = $this->posts->firstId();

		$head = new PageHead('admin-reindex', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Management')->html, $this->urls->link('admin_reports')),
			new Crumb(self::string($common, 'Rebuild index')->html, $this->urls->link('admin_reindex')),
		), section: 'management');

		$view = new RebuildFormView(array(
			'ari'		=> $strings,
			'action'	=> $this->urls->link('admin_reindex'),
			'token'		=> $this->tokens->token($this->target()),
			'firstId'	=> $firstId ?? 0,
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->form($view)));
	}

	/** What a cycle's link token is built for: the rebuild, by this administrator. */
	private function target(): string {
		return 'reindex'.$this->visitor->id();
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function cycle(Request $request, array $common, array $strings): Response {
		$perCycle = is_scalar($request->query['i_per_page']) ? intval($request->query['i_per_page']) : 0;
		$startAt = is_scalar($request->query['i_start_at']) ? intval($request->query['i_start_at']) : 0;
		if ($perCycle < 1 || $startAt < 1)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		// A token posted with the request was checked on the way in; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, $this->target()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$emptying = isset($request->query['i_empty_index']);
		$start = new ReindexCycleStep(ReindexCycleStep::START, $perCycle, $startAt, $emptying);
		$this->events->dispatch($start);

		if (function_exists('set_time_limit'))
			set_time_limit(0);

		if ($emptying)
			$this->index->clear();

		$lines = array();
		$lastPostId = 0;
		foreach ($this->posts->batch($startAt, $perCycle) as $post)
		{
			$lines[] = Html::format(self::string($strings, 'Processing post'), $post->id(), $post->topicId());
			$this->index->index($post->id(), $post->message(), $post->isTopic() ? $post->subject() : null);
			$lastPostId = $post->id();
		}

		$nextPostId = $this->posts->nextId($lastPostId);
		$next = $nextPostId !== null ? '?i_per_page='.$perCycle.'&i_start_at='.$nextPostId.'&csrf_token='.$this->tokens->token($this->target()) : '';

		$end = new ReindexCycleStep(ReindexCycleStep::END, $perCycle, $startAt, $emptying, $lastPostId, $nextPostId);
		$this->events->dispatch($end);

		$crumbs = array(
			$this->settings->value('o_board_title'),
			self::string($common, 'Forum administration')->html,
			self::string($common, 'Management')->html,
			self::string($strings, 'Rebuilding index title')->html,
		);

		$url = htmlspecialchars_decode($this->urls->link('admin_reindex')->html, ENT_QUOTES).$next;

		return new Response($this->templates->render(self::CYCLE, array(
			'started'		=> new Html($start->markup()),
			'ended'			=> new Html($end->markup()),
			'identifier'	=> $this->language->text('common', 'lang_identifier'),
			'direction'		=> $this->language->text('common', 'lang_direction'),
			'title'			=> Html::join($this->language->text('common', 'Title separator')->html, array_map(Html::escape(...), array_reverse($crumbs))),
			'rebuilding'	=> self::string($strings, 'Rebuilding index'),
			'lines'			=> $lines,
			'script'		=> Html::script($url),
			'url'			=> $url,
			'redirect'		=> self::string($strings, 'Javascript redirect'),
			'continue'		=> self::string($strings, 'Click to continue'),
		)));
	}

	private function form(RebuildFormView $view): Html {
		$this->at($view, ReindexRendering::MAIN_OUTPUT_START);

		$this->at($view, ReindexRendering::PRE_REBUILD_FIELDSET);
		$view->numberGroup('group');

		$this->at($view, ReindexRendering::PRE_REBUILD_PER_PAGE);
		$view->numberItem('per_page_item');
		$view->numberField('per_page_field');

		$this->at($view, ReindexRendering::PRE_REBUILD_START_POST);
		$view->numberItem('start_item');
		$view->numberField('start_field');

		$this->at($view, ReindexRendering::PRE_REBUILD_EMPTY_INDEX);
		$view->numberItem('empty_item');
		$view->numberField('empty_field');

		$this->at($view, ReindexRendering::PRE_REBUILD_FIELDSET_END);
		$this->at($view, ReindexRendering::REBUILD_FIELDSET_END);

		$body = $this->templates->render(self::FORM, $view->variables());

		$end = $view->rendering(ReindexRendering::END);
		$this->events->dispatch($end);

		return (new Html($view->markup(ReindexRendering::MAIN_OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	private function at(RebuildFormView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
