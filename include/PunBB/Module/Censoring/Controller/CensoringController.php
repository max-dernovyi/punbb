<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Controller;

use PunBB\Module\Censoring\Api\CensorsInterface;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\Censoring\Cache\CensorCacheInterface;
use PunBB\Module\Censoring\Event\CensorChangeStep;
use PunBB\Module\Censoring\Event\CensoredWordRendering;
use PunBB\Module\Censoring\Event\CensoringRendering;
use PunBB\Module\Censoring\Event\CensoringRequested;
use PunBB\Module\Censoring\Model\Censor;
use PunBB\Module\Censoring\View\CensoringView;
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
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/censoring.php: the form adding a censored word, the form editing and
 * removing the stored ones, and each change, for administrators and moderators.
 */
final class CensoringController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/censoring.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly CensorsInterface $censors,
		private readonly CensorCacheInterface $cache,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new CensoringRequested());

		if (!$this->visitor->isModerating())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_censoring');

		if (isset($request->post['add_word']))
		{
			$censor = new Censor(0, self::text($request->post['new_search_for'] ?? null), self::text($request->post['new_replace_with'] ?? null));

			return $this->change($request, $censor, CensorChangeStep::ADDING, CensorChangeStep::ADDED, 'Censor word added', $strings);
		}

		if (isset($request->post['update']))
		{
			if (!is_array($request->post['update']))
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

			$id = intval(key($request->post['update']));
			$censor = new Censor($id, self::text(self::entry($request->post['search_for'] ?? null, $id)), self::text(self::entry($request->post['replace_with'] ?? null, $id)));

			return $this->change($request, $censor, CensorChangeStep::UPDATING, CensorChangeStep::UPDATED, 'Censor word updated', $strings);
		}

		if (isset($request->post['remove']))
		{
			if (!is_array($request->post['remove']))
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

			$censor = new Censor(intval(key($request->post['remove'])), '', '');

			return $this->change($request, $censor, CensorChangeStep::REMOVING, CensorChangeStep::REMOVED, 'Censor word removed', $strings);
		}

		return $this->page($common, $strings);
	}

	/**
	 * Adds, updates or removes $censor, as $submitted names it, and sends the browser back to the list.
	 *
	 * @param array<string, Html> $strings
	 */
	private function change(Request $request, CensorInterface $censor, string $submitted, string $done, string $message, array $strings): Response {
		if ($submitted !== CensorChangeStep::REMOVING && ($censor->searchFor() === '' || $censor->replaceWith() === ''))
			return $this->messages->respond(self::string($strings, 'Must enter text message'), json: $request->xhr);

		$this->events->dispatch(new CensorChangeStep($submitted, $censor));

		match ($submitted) {
			CensorChangeStep::ADDING	=> $this->censors->add($censor),
			CensorChangeStep::UPDATING	=> $this->censors->update($censor),
			default						=> $this->censors->remove($censor->id()),
		};

		$this->cache->rebuild();
		$this->flash->info(self::string($strings, $message));

		$this->events->dispatch(new CensorChangeStep($done, $censor));

		return $this->redirects->respond($this->urls->link('admin_censoring')->html, self::string($strings, $message), $request->xhr);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function page(array $common, array $strings): Response {
		$censors = $this->censors->all();
		$action = $this->urls->link('admin_censoring');

		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
		);
		if ($this->visitor->isAdministrator())
			$crumbs[] = new Crumb(self::string($common, 'Settings')->html, $this->urls->link('admin_settings_setup'));
		$crumbs[] = new Crumb(self::string($common, 'Censoring')->html, $action);

		$intro = self::string($strings, 'Add censored word intro');
		if ($this->visitor->isAdministrator())
			$intro = new Html($intro->html.Html::format(' '.self::string($strings, 'Add censored word extra')->html,
				Html::format('<a class="nowrap" href="%s">%s &rarr; %s</a>', $this->urls->link('admin_settings_features'), self::string($common, 'Settings'), self::string($common, 'Features')))->html);

		$view = new CensoringView(array(
			'acs'		=> $strings,
			'common'	=> $common,
			'action'	=> $action,
			'intro'		=> $intro,
		));

		return $this->pages->respond(new PageHead('admin-censoring', $crumbs, section: 'settings'), fn (): array => array('main' => $this->main($view, $censors, $action)));
	}

	/** @param list<CensorInterface> $censors */
	private function main(CensoringView $view, array $censors, Html $action): Html {
		$this->at($view, CensoringRendering::MAIN_OUTPUT_START);

		$view->show('addToken', $this->tokens->token($action->html.'?action=foo'));
		$view->numberGroup('add_group');

		$this->at($view, CensoringRendering::PRE_ADD_WORD_FIELDSET);
		$view->numberItem('add_item');

		$this->at($view, CensoringRendering::PRE_ADD_SEARCH_FOR);
		$view->numberField('add_search_for');

		$this->at($view, CensoringRendering::PRE_ADD_REPLACE_WITH);
		$view->numberField('add_replace_with');

		$this->at($view, CensoringRendering::PRE_ADD_SUBMIT);
		$this->at($view, CensoringRendering::PRE_ADD_WORD_FIELDSET_END);
		$this->at($view, CensoringRendering::ADD_WORD_FIELDSET_END);

		$words = array();
		if ($censors !== array())
		{
			$view->restartGroupsAndItems();
			$view->show('editToken', $this->tokens->token($action->html.'?action=foo'));
			$view->numberGroup('edit_group');

			foreach ($censors as $key => $censor)
				$words[] = $this->word($view, $censor, $key + 1);
		}

		$view->show('words', $words);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(CensoringRendering::END);
		$this->events->dispatch($end);

		return (new Html($view->markup(CensoringRendering::MAIN_OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	/** @return array<string, mixed> what the template shows of the word's fieldset */
	private function word(CensoringView $view, CensorInterface $censor, int $number): array {
		$at = function (string $position) use ($view, $censor, $number): Html {
			$event = $view->wordRendering($position, $censor, $number);
			$this->events->dispatch($event);

			return $view->placeWord($event);
		};

		$word = array('id' => $censor->id(), 'searchFor' => $censor->searchFor(), 'replaceWith' => $censor->replaceWith());

		$word['preFieldset'] = $at(CensoredWordRendering::PRE_EDIT_WORD_FIELDSET);
		$word['item'] = $view->numberItem('word_item');

		$word['preSearchFor'] = $at(CensoredWordRendering::PRE_EDIT_SEARCH_FOR);
		$word['searchForField'] = $view->numberField('word_search_for');

		$word['preReplaceWith'] = $at(CensoredWordRendering::PRE_EDIT_REPLACE_WITH);
		$word['replaceWithField'] = $view->numberField('word_replace_with');

		$word['preSubmit'] = $at(CensoredWordRendering::PRE_EDIT_SUBMIT);
		$word['preFieldsetEnd'] = $at(CensoredWordRendering::PRE_EDIT_WORD_FIELDSET_END);
		$word['fieldsetEnd'] = $at(CensoredWordRendering::EDIT_WORD_FIELDSET_END);

		return $word;
	}

	private function at(CensoringView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
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
