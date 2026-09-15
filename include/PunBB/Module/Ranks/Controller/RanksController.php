<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Controller;

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
use PunBB\Module\Ranks\Api\Data\RankInterface;
use PunBB\Module\Ranks\Api\RanksInterface;
use PunBB\Module\Ranks\Cache\RankCacheInterface;
use PunBB\Module\Ranks\Event\RankChangeStep;
use PunBB\Module\Ranks\Event\RankRendering;
use PunBB\Module\Ranks\Event\RanksRendering;
use PunBB\Module\Ranks\Event\RanksRequested;
use PunBB\Module\Ranks\Model\Rank;
use PunBB\Module\Ranks\View\RanksView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/ranks.php: the form adding a rank, the form editing and removing the
 * stored ones, and each change, for administrators.
 */
final class RanksController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/ranks.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly RanksInterface $ranks,
		private readonly RankCacheInterface $cache,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new RanksRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_ranks');

		if (isset($request->post['add_rank']))
		{
			$rank = new Rank(0, self::text($request->post['new_rank'] ?? null), self::integer($request->post['new_min_posts'] ?? 0));

			return $this->change($request, $rank, RankChangeStep::ADDING, RankChangeStep::ADDED, 'Rank added', $strings);
		}

		if (isset($request->post['update']))
		{
			if (!is_array($request->post['update']))
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

			$id = intval(key($request->post['update']));
			$rank = new Rank($id, self::text(self::entry($request->post['rank'] ?? null, $id)), self::integer(self::entry($request->post['min_posts'] ?? null, $id) ?? 0));

			return $this->change($request, $rank, RankChangeStep::UPDATING, RankChangeStep::UPDATED, 'Rank updated', $strings);
		}

		if (isset($request->post['remove']))
		{
			if (!is_array($request->post['remove']))
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

			$rank = new Rank(intval(key($request->post['remove'])), '', 0);

			return $this->change($request, $rank, RankChangeStep::REMOVING, RankChangeStep::REMOVED, 'Rank removed', $strings);
		}

		return $this->page($common, $strings);
	}

	/**
	 * Adds, updates or removes $rank, as $submitted names it, and sends the browser back to the list.
	 *
	 * @param array<string, Html> $strings
	 */
	private function change(Request $request, RankInterface $rank, string $submitted, string $done, string $message, array $strings): Response {
		if ($submitted !== RankChangeStep::REMOVING)
		{
			if ($rank->title() === '')
				return $this->messages->respond(self::string($strings, 'Title message'), json: $request->xhr);

			if ($rank->minPosts() < 0)
				return $this->messages->respond(self::string($strings, 'Min posts message'), json: $request->xhr);
		}

		$this->events->dispatch(new RankChangeStep($submitted, $rank));

		if ($submitted !== RankChangeStep::REMOVING && $this->ranks->minPostsTaken($rank->minPosts(), $submitted === RankChangeStep::UPDATING ? $rank->id() : null))
			return $this->messages->respond(Html::format(self::string($strings, 'Min posts occupied message'), $rank->minPosts()), json: $request->xhr);

		match ($submitted) {
			RankChangeStep::ADDING		=> $this->ranks->add($rank),
			RankChangeStep::UPDATING	=> $this->ranks->update($rank),
			default						=> $this->ranks->remove($rank->id()),
		};

		$this->cache->rebuild();
		$this->flash->info(self::string($strings, $message));

		$this->events->dispatch(new RankChangeStep($done, $rank));

		return $this->redirects->respond($this->urls->link('admin_ranks')->html, self::string($strings, $message), $request->xhr);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function page(array $common, array $strings): Response {
		$ranks = $this->ranks->all();
		$action = $this->urls->link('admin_ranks');

		$head = new PageHead('admin-ranks', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Users')->html, $this->urls->link('admin_users')),
			new Crumb(self::string($common, 'Ranks')->html, $action),
		), section: 'users');

		$view = new RanksView(array(
			'ark'		=> $strings,
			'action'	=> $action,
			'intro'		=> Html::format(self::string($strings, 'Add rank intro'),
				Html::format('<a class="nowrap" href="%s">%s &rarr; %s</a>', $this->urls->link('admin_settings_features'), self::string($common, 'Settings'), self::string($common, 'Features'))),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->main($view, $ranks, $action)));
	}

	/** @param list<RankInterface> $ranks */
	private function main(RanksView $view, array $ranks, Html $action): Html {
		$this->at($view, RanksRendering::MAIN_OUTPUT_START);

		$view->show('addToken', $this->tokens->token($action->html.'?action=foo'));
		$view->numberGroup('add_group');

		$this->at($view, RanksRendering::PRE_ADD_RANK_FIELDSET);
		$view->numberItem('add_item');

		$this->at($view, RanksRendering::PRE_ADD_RANK_TITLE);
		$view->numberField('add_title');

		$this->at($view, RanksRendering::PRE_ADD_RANK_MIN_POSTS);
		$view->numberField('add_min_posts');

		$this->at($view, RanksRendering::PRE_ADD_RANK_SUBMIT);
		$this->at($view, RanksRendering::PRE_ADD_RANK_FIELDSET_END);
		$this->at($view, RanksRendering::ADD_RANK_FIELDSET_END);

		$listed = array();
		if ($ranks !== array())
		{
			$view->restartGroupsAndItems();
			$view->show('editToken', $this->tokens->token($action->html.'?action=foo'));
			$view->numberGroup('edit_group');

			foreach ($ranks as $key => $rank)
				$listed[] = $this->rank($view, $rank, $key + 1);
		}

		$view->show('ranks', $listed);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(RanksRendering::END);
		$this->events->dispatch($end);

		return (new Html($view->markup(RanksRendering::MAIN_OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	/** @return array<string, mixed> what the template shows of the rank's fieldset */
	private function rank(RanksView $view, RankInterface $rank, int $number): array {
		$at = function (string $position) use ($view, $rank, $number): Html {
			$event = $view->rankRendering($position, $rank, $number);
			$this->events->dispatch($event);

			return $view->placeRank($event);
		};

		$listed = array('id' => $rank->id(), 'title' => $rank->title(), 'minPosts' => $rank->minPosts());

		$listed['preFieldset'] = $at(RankRendering::PRE_EDIT_CUR_RANK_FIELDSET);
		$listed['item'] = $view->numberItem('rank_item');

		$listed['preTitle'] = $at(RankRendering::PRE_EDIT_CUR_RANK_TITLE);
		$listed['titleField'] = $view->numberField('rank_title');

		$listed['preMinPosts'] = $at(RankRendering::PRE_EDIT_CUR_RANK_MIN_POSTS);
		$listed['minPostsField'] = $view->numberField('rank_min_posts');

		$listed['preSubmit'] = $at(RankRendering::PRE_EDIT_CUR_RANK_SUBMIT);
		$listed['preFieldsetEnd'] = $at(RankRendering::PRE_EDIT_CUR_RANK_FIELDSET_END);
		$listed['fieldsetEnd'] = $at(RankRendering::EDIT_CUR_RANK_FIELDSET_END);

		return $listed;
	}

	private function at(RanksView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** A value the request carries, as intval() took it. */
	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** The element $key of what the request carries as an array; null when it carries none. */
	private static function entry(mixed $values, int $key): mixed {
		return is_array($values) ? $values[$key] ?? null : null;
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
