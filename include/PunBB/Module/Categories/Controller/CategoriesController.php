<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Controller;

use PunBB\Module\Categories\Api\CategoriesInterface;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Categories\Event\CategoriesRendering;
use PunBB\Module\Categories\Event\CategoriesRequested;
use PunBB\Module\Categories\Event\CategoryChangeStep;
use PunBB\Module\Categories\Event\CategoryDeletionRendering;
use PunBB\Module\Categories\Event\CategoryRendering;
use PunBB\Module\Categories\Model\Category;
use PunBB\Module\Categories\View\CategoriesView;
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
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Removal\ForumContentsInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/categories.php: the forms adding, deleting and editing the categories,
 * the confirmation a deletion asks for, and each change, for administrators.
 * A category is deleted with its forums and everything posted in them.
 */
final class CategoriesController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/categories.phtml';

	private const DELETE_TEMPLATE = __DIR__.'/../templates/delete.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly CategoriesInterface $categories,
		private readonly ForumContentsInterface $contents,
		private readonly QuickjumpCacheInterface $quickjump,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new CategoriesRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_categories');

		if (isset($request->post['add_cat']))
			return $this->add($request, $strings);

		if (isset($request->post['del_cat']) || isset($request->post['del_cat_comply']))
			return $this->delete($request, $common, $strings);

		if (isset($request->post['update']))
			return $this->update($request, $strings);

		return $this->page($common, $strings);
	}

	/** @param array<string, Html> $strings */
	private function add(Request $request, array $strings): Response {
		$name = self::text($request->post['new_cat_name'] ?? null);
		if ($name === '')
			return $this->messages->respond(self::string($strings, 'Must name category'), json: $request->xhr);

		$category = new Category(0, $name, self::integer($request->post['position'] ?? 0));

		$this->events->dispatch(new CategoryChangeStep(CategoryChangeStep::ADDING, array($category)));

		$this->categories->add($category);

		return $this->done($request, CategoryChangeStep::ADDED, array($category), self::string($strings, 'Category added'));
	}

	/**
	 * A category deleted once the deletion is confirmed, with its forums and their topics; the confirmation until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function delete(Request $request, array $common, array $strings): Response {
		$id = self::integer($request->post['cat_to_delete'] ?? 0);
		if ($id < 1)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		if (isset($request->post['del_cat_cancel']))
			return $this->redirects->respond($this->urls->link('admin_categories')->html, self::string($common, 'Cancel redirect'), $request->xhr);

		$confirmed = isset($request->post['del_cat_comply']);
		$category = new Category($id, '', 0);

		$this->events->dispatch(new CategoryChangeStep(CategoryChangeStep::DELETING, array($category), $confirmed));

		if (!$confirmed)
			return $this->confirm($request, $id, $common, $strings);

		if (function_exists('set_time_limit'))
			set_time_limit(0);

		foreach ($this->categories->forumIds($id) as $forumId)
		{
			$this->contents->empty($forumId);
			$this->categories->removeForums($forumId);
			$this->categories->removeForumSubscriptions($forumId);
		}

		$this->contents->removeOrphans();
		$this->categories->remove($id);
		$this->quickjump->rebuild();

		return $this->done($request, CategoryChangeStep::DELETED, array($category), self::string($strings, 'Category deleted'));
	}

	/**
	 * The categories as submitted, each stored when it changed. A category added
	 * since the form was built is left alone; a name left empty or a negative
	 * position stops the update before anything is stored.
	 *
	 * @param array<string, Html> $strings
	 */
	private function update(Request $request, array $strings): Response {
		$names = $request->post['cat_name'] ?? null;
		$orders = $request->post['cat_order'] ?? null;

		if (!is_array($orders) || !is_array($names))
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$submitted = array();
		foreach ($names as $id => $name)
			if (isset($orders[$id]))
				$submitted[(int) $id] = new Category((int) $id, is_string($name) ? trim($name) : '', self::integer($orders[$id]));

		$this->events->dispatch(new CategoryChangeStep(CategoryChangeStep::UPDATING, array_values($submitted)));

		$changed = array();
		foreach ($this->categories->allById() as $stored)
		{
			$category = $submitted[$stored->id()] ?? null;
			if ($category === null)
				continue;

			if ($category->name() === '')
				return $this->messages->respond(self::string($strings, 'Must name category'), json: $request->xhr);

			if ($category->position() < 0)
				return $this->messages->respond(self::string($strings, 'Must be integer'), json: $request->xhr);

			if ($stored->name() !== $category->name() || $stored->position() !== $category->position())
				$changed[] = $category;
		}

		if ($changed !== array())
			$this->categories->update(...$changed);

		$this->quickjump->rebuild();

		return $this->done($request, CategoryChangeStep::UPDATED, array_values($submitted), self::string($strings, 'Categories updated'));
	}

	/**
	 * Tells the next page what changed, and sends the browser back to the list.
	 *
	 * @param list<CategoryInterface> $categories
	 */
	private function done(Request $request, string $step, array $categories, Html $message): Response {
		$this->flash->info($message);

		$this->events->dispatch(new CategoryChangeStep($step, $categories, $step === CategoryChangeStep::DELETED));

		return $this->redirects->respond($this->urls->link('admin_categories')->html, $message, $request->xhr);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function confirm(Request $request, int $id, array $common, array $strings): Response {
		$name = $this->categories->name($id);
		if ($name === null)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$category = new Category($id, $name, 0);
		$action = $this->urls->link('admin_categories');

		$crumbs = $this->crumbs($common);
		$crumbs[] = new Crumb(self::string($strings, 'Delete category')->html);

		return $this->pages->respond(new PageHead('admin-categories', $crumbs, section: 'start', view: 'delete'), fn (): array => array('main' => $this->confirmation($category, $action, $common, $strings)));
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function confirmation(CategoryInterface $category, Html $action, array $common, array $strings): Html {
		$start = new CategoryDeletionRendering(CategoryDeletionRendering::OUTPUT_START, $category, $action->html, array(
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
			'cat_to_delete'	=> Html::format('<input type="hidden" name="cat_to_delete" value="%s" />', $category->id())->html,
		));
		$this->events->dispatch($start);

		$body = $this->templates->render(self::DELETE_TEMPLATE, array(
			'acg'		=> $strings,
			'common'	=> $common,
			'heading'	=> Html::format(self::string($strings, 'Confirm delete cat'), $category->name()),
			'action'	=> $action,
			'hidden'	=> self::joined($start, "\n\t\t\t\t"),
		));

		$end = new CategoryDeletionRendering(CategoryDeletionRendering::END, $category, $action->html);
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function page(array $common, array $strings): Response {
		$categories = $this->categories->all();
		$action = new Html($this->urls->link('admin_categories')->html.'?action=foo');

		$view = new CategoriesView($action->html, array(
			'acg'		=> $strings,
			'common'	=> $common,
			'action'	=> $action,
			'addInfo'	=> Html::format(self::string($strings, 'Add category info'), Html::format('<a href="%s">%s</a>', $this->urls->link('admin_forums'), self::string($strings, 'Add category info link text'))),
		));

		return $this->pages->respond(new PageHead('admin-categories', $this->crumbs($common), section: 'start'), fn (): array => array('main' => $this->main($view, $categories, $action)));
	}

	/** @param list<CategoryInterface> $categories */
	private function main(CategoriesView $view, array $categories, Html $action): Html {
		$start = $view->rendering(CategoriesRendering::MAIN_OUTPUT_START, array(
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		));
		$this->events->dispatch($start);
		$view->place($start);
		$view->show('hidden', self::joined($start, "\n\t\t\t\t"));

		$this->at($view, CategoriesRendering::PRE_ADD_CAT_FIELDSET);
		$view->numberGroup('add_group');

		$this->at($view, CategoriesRendering::PRE_NEW_CATEGORY_NAME);
		$view->numberItem('name_item');
		$view->numberField('name_field');

		$this->at($view, CategoriesRendering::PRE_NEW_CATEGORY_POSITION);
		$view->numberItem('position_item');
		$view->numberField('position_field');

		$this->at($view, CategoriesRendering::PRE_ADD_CAT_FIELDSET_END);
		$this->at($view, CategoriesRendering::ADD_CAT_FIELDSET_END);
		$this->at($view, CategoriesRendering::POST_ADD_CAT_FORM);
		$view->restartGroupsAndItems();

		$listed = array();
		if ($categories !== array())
		{
			$this->at($view, CategoriesRendering::PRE_DEL_CAT_FIELDSET);
			$view->numberGroup('delete_group');

			$this->at($view, CategoriesRendering::PRE_DEL_CATEGORY_SELECT);
			$view->numberItem('select_item');
			$view->numberField('select_field');

			$this->at($view, CategoriesRendering::PRE_DEL_CAT_FIELDSET_END);
			$this->at($view, CategoriesRendering::DEL_CAT_FIELDSET_END);
			$this->at($view, CategoriesRendering::POST_DEL_CAT_FORM);
			$view->restartGroupsAndItems();

			$this->at($view, CategoriesRendering::EDIT_CAT_FIELDSETS_START);

			foreach ($categories as $category)
				$listed[] = $this->category($view, $category);

			$this->at($view, CategoriesRendering::EDIT_CAT_FIELDSETS_END);
			$this->at($view, CategoriesRendering::POST_EDIT_CAT_FORM);
		}

		$view->show('categories', $listed);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(CategoriesRendering::END);
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/** @return array<string, mixed> what the template shows of the category's fieldset */
	private function category(CategoriesView $view, CategoryInterface $category): array {
		$at = function (string $position) use ($view, $category): Html {
			$event = $view->categoryRendering($position, $category);
			$this->events->dispatch($event);

			return $view->placeCategory($event);
		};

		$view->restartItems();

		$listed = array('id' => $category->id(), 'name' => $category->name(), 'position' => $category->position());

		$listed['preFieldset'] = $at(CategoryRendering::PRE_EDIT_CUR_CAT_FIELDSET);
		$listed['group'] = $view->numberGroup('category_group');
		$listed['item'] = $view->numberItem('category_item');

		$listed['preName'] = $at(CategoryRendering::PRE_EDIT_CAT_NAME);
		$listed['nameField'] = $view->numberField('category_name');

		$listed['prePosition'] = $at(CategoryRendering::PRE_EDIT_CAT_POSITION);
		$listed['positionField'] = $view->numberField('category_position');

		$listed['preFieldsetEnd'] = $at(CategoryRendering::PRE_EDIT_CUR_CAT_FIELDSET_END);
		$listed['fieldsetEnd'] = $at(CategoryRendering::EDIT_CUR_CAT_FIELDSET_END);

		return $listed;
	}

	private function at(CategoriesView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/**
	 * @param array<string, Html> $common
	 * @return list<Crumb>
	 */
	private function crumbs(array $common): array {
		return array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Start')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Categories')->html, $this->urls->link('admin_categories')),
		);
	}

	/** The hidden fields $event carries, joined with $glue. */
	private static function joined(CategoriesRendering|CategoryDeletionRendering $event, string $glue): Html {
		$fields = array();
		foreach ($event->names() as $name)
			$fields[] = (string) $event->entry($name);

		return new Html(implode($glue, $fields));
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** A value the request carries, as intval() took it. */
	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
