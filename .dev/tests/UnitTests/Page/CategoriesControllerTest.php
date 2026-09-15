<?php
/**
 * admin/categories.php as a module, built with no forum: who may see it, the
 * three forms numbered as observers add fields, the confirmation a deletion
 * asks for, and adding, deleting and updating categories.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Categories\Api\CategoriesInterface;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Categories\Controller\CategoriesController;
use PunBB\Module\Categories\Event\CategoriesRendering;
use PunBB\Module\Categories\Event\CategoriesRequested;
use PunBB\Module\Categories\Event\CategoryChangeStep;
use PunBB\Module\Categories\Event\CategoryDeletionRendering;
use PunBB\Module\Categories\Event\CategoryRendering;
use PunBB\Module\Categories\Model\Category;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Removal\ForumContentsInterface;

require_once __DIR__.'/PageFakes.php';

final class FakeCategories implements CategoriesInterface, ForumContentsInterface, QuickjumpCacheInterface {
	/** @var list<Category> by position */
	public array $categories = array();

	/** @var array<int, list<int>> category id => its forums */
	public array $forums = array();

	/** @var list<string> */
	public array $log = array();

	public function all(): array {
		return $this->categories;
	}

	public function allById(): array {
		$categories = $this->categories;
		usort($categories, static fn (Category $a, Category $b): int => $a->id() <=> $b->id());

		return $categories;
	}

	public function name(int $id): ?string {
		foreach ($this->categories as $category)
			if ($category->id() === $id)
				return $category->name();

		return null;
	}

	public function forumIds(int $id): array {
		return $this->forums[$id] ?? array();
	}

	public function add(CategoryInterface ...$categories): void {
		foreach ($categories as $category)
			$this->log[] = 'add '.$category->name().'@'.$category->position();
	}

	public function update(CategoryInterface ...$categories): void {
		foreach ($categories as $category)
			$this->log[] = 'update '.$category->id().' '.$category->name().'@'.$category->position();
	}

	public function removeForums(int ...$forumIds): void {
		$this->log[] = 'remove forums '.implode(',', $forumIds);
	}

	public function removeForumSubscriptions(int ...$forumIds): void {
		$this->log[] = 'remove subscriptions '.implode(',', $forumIds);
	}

	public function remove(int ...$ids): void {
		$this->log[] = 'remove '.implode(',', $ids);
	}

	public function empty(int $forumId): void {
		$this->log[] = 'empty '.$forumId;
	}

	public function removeOrphans(): void {
		$this->log[] = 'orphans';
	}

	public function rebuild(): void {
		$this->log[] = 'quickjump';
	}

	public function clear(): void {
		$this->log[] = 'quickjump cleared';
	}
}

class CategoriesControllerTest extends TestCase {
	private PageKit $kit;

	private FakeCategories $categories;

	protected function setUp(): void {
		$this->kit = new PageKit(array(CategoriesRequested::class, CategoryChangeStep::class, CategoriesRendering::class, CategoryRendering::class, CategoryDeletionRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_categories', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->visitor->administrator = true;
		$this->categories = new FakeCategories();
		$this->categories->categories = array(new Category(7, 'Talk & <chat>', 1), new Category(3, 'News', 2));
	}

	/** @param array<string, mixed> $post */
	private function page(array $post = array()): string {
		$controller = new CategoriesController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			$this->categories, $this->categories, $this->categories, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/categories.php', array(), $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testOnlyAnAdministratorGetsThePage(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->moderating = true;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('del_cat_comply' => '1', 'cat_to_delete' => '7')));
		$this->assertSame(array(), $this->categories->log);
		$this->assertSame(array('CategoriesRequested'), array_slice($this->kit->events->dispatched, 0, 1));
	}

	public function testTheFormsAddDeleteAndEditTheCategories(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-categories', 'start', null), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Start', 'Categories'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$token = 'token-for-'.md5('/admin_categories?a=1&amp;b=2?action=foo');
		$this->assertStringStartsWith("200  [admin-categories]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Add category (create a new category at the specified position)</span></h2>", $body);
		$this->assertSame(3, substr_count($body, "<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"".$token."\" />\n\t\t\t</div>"));
		$this->assertStringContainsString('go to the <a href="/admin_forums?a=1&amp;b=2">forums</a> page.</p>', $body);
		$this->assertStringContainsString('<input type="number" id="fld2" name="position" size="3" maxlength="3" />', $body);
		$this->assertStringContainsString("<select id=\"fld3\" name=\"cat_to_delete\">\n\t\t\t\t\t\t\t<option value=\"7\">Talk &amp; &lt;chat&gt;</option>\n\t\t\t\t\t\t\t<option value=\"3\">News</option>\n\t\t\t\t\t\t</select>", $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group2\">\n\t\t\t\t<legend class=\"group-legend\"><span>Edit category</span></legend>\n\t\t\t\t<div class=\"sf-set set1\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld4" name="cat_name[7]" value="Talk &amp; &lt;chat&gt;" size="35" maxlength="80" required />', $body);
		$this->assertStringContainsString('<input type="number" id="fld7" name="cat_order[3]" value="2" size="3" maxlength="3" />', $body);
		$this->assertStringEndsWith("<input type=\"submit\" name=\"update\" value=\"Update all categories\" /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $body);
	}

	public function testWithoutCategoriesOnlyTheFormAddingOneIsThere(): void {
		$this->categories->categories = array();

		$body = $this->page();

		$this->assertSame(1, substr_count($body, '<form'));
		$this->assertStringNotContainsString(CategoriesRendering::PRE_DEL_CAT_FIELDSET, implode(' ', $this->kit->events->dispatched));
	}

	public function testObserversAddFieldsAndHiddenFieldsAndTheFormsNumberOn(): void {
		$seen = array();
		$this->kit->events->observe(CategoriesRendering::class, function (CategoriesRendering $event) use (&$seen): void {
			if ($event->position() === CategoriesRendering::MAIN_OUTPUT_START)
				$event->set('probe', '<input type="hidden" name="probe" />');

			if ($event->position() === CategoriesRendering::PRE_NEW_CATEGORY_POSITION)
			{
				$event->append('<div class="set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}

			if ($event->position() === CategoriesRendering::POST_ADD_CAT_FORM || $event->position() === CategoriesRendering::EDIT_CAT_FIELDSETS_START)
				$seen[] = $event->position().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();
		});
		$this->kit->events->observe(CategoryRendering::class, function (CategoryRendering $event) use (&$seen): void {
			if ($event->position() === CategoryRendering::PRE_EDIT_CUR_CAT_FIELDSET)
				$seen[] = $event->category()->name().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();

			if ($event->position() === CategoryRendering::PRE_EDIT_CAT_POSITION && $event->category()->id() === 7)
				$event->append('<!-- before position of 7 -->');
		});

		$body = $this->page();

		$this->assertSame(3, substr_count($body, "\t\t\t\t<input type=\"hidden\" name=\"probe\" />\n\t\t\t</div>"));
		$this->assertStringContainsString('<div class="set2"><input id="fld2" /></div>'."\t\t\t\t<div class=\"sf-set set3\">", $body);
		$this->assertStringContainsString('name="position" size="3"', $body);
		$this->assertStringContainsString('<input type="number" id="fld3" name="position"', $body);
		$this->assertStringContainsString("<!-- before position of 7 -->\t\t\t\t\t<div class=\"sf-box text\">\n\t\t\t\t\t\t<label for=\"fld6\">", $body);
		$this->assertSame(array('post_add_cat_form at 1/3/3', 'edit_cat_fieldsets_start at 0/0/4', 'Talk & <chat> at 0/0/4', 'News at 1/0/6'), $seen);
	}

	public function testACategoryIsAddedAndTheVisitorSentBack(): void {
		$steps = array();
		$this->kit->events->observe(CategoryChangeStep::class, function (CategoryChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->categories()[0]->name().' '.implode(',', $this->categories->log);
		});

		$this->assertStringStartsWith('302 /admin_categories?a=1&b=2 [redirect]', $this->page(array('add_cat' => '1', 'new_cat_name' => ' Games ', 'position' => '5x')));
		$this->assertSame(array('add Games@5'), $this->categories->log);
		$this->assertSame(array('adding Games ', 'added Games add Games@5'), $steps);
		$this->assertSame(array('Category added.'), $this->kit->flash->info);
	}

	public function testACategoryWithoutANameIsRefused(): void {
		$this->assertStringContainsString('<p>You must enter a name for the category</p>', $this->page(array('add_cat' => '1', 'new_cat_name' => array('Games'))));
		$this->assertSame(array(), $this->categories->log);
	}

	public function testADeletionIsConfirmedFirst(): void {
		$seen = array();
		$this->kit->events->observe(CategoryChangeStep::class, function (CategoryChangeStep $event) use (&$seen): void {
			$seen[] = $event->step().' '.$event->categories()[0]->id().' '.($event->confirmed() ? 'confirmed' : 'unconfirmed');
		});
		$this->kit->events->observe(CategoryDeletionRendering::class, function (CategoryDeletionRendering $event): void {
			if ($event->position() === CategoryDeletionRendering::OUTPUT_START)
				$event->set('probe', '<input type="hidden" name="probe" value="'.$event->category()->name().'" />');
			else
				$event->append('<!-- end -->');
		});

		$body = $this->page(array('del_cat' => '1', 'cat_to_delete' => '7'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-categories', 'start', 'delete'), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Start', 'Categories', 'Delete category'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertStringStartsWith("200  [admin-categories]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>You are deleting the category \"Talk &amp; &lt;chat&gt;\"</span></h2>", $body);
		$this->assertStringContainsString("<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_categories?a=1&amp;b=2')."\" />\n\t\t\t\t<input type=\"hidden\" name=\"cat_to_delete\" value=\"7\" />\n\t\t\t\t<input type=\"hidden\" name=\"probe\" value=\"Talk & <chat>\" />", $body);
		$this->assertStringEndsWith("</form>\n\t</div>\n<!-- end -->", $body);
		$this->assertSame(array('deleting 7 unconfirmed'), $seen);
		$this->assertSame(array(), $this->categories->log);
	}

	public function testAConfirmedDeletionEmptiesAndRemovesTheForumsFirst(): void {
		$this->categories->forums = array(7 => array(1, 4));

		$this->assertStringStartsWith('302 /admin_categories?a=1&b=2 [redirect]', $this->page(array('del_cat_comply' => '1', 'cat_to_delete' => '7')));
		$this->assertSame(array('empty 1', 'remove forums 1', 'remove subscriptions 1', 'empty 4', 'remove forums 4', 'remove subscriptions 4', 'orphans', 'remove 7', 'quickjump'), $this->categories->log);
		$this->assertSame(array('Category deleted.'), $this->kit->flash->info);
	}

	public function testADeletionOfNothingOrCancelledOrOfAnUnknownCategoryDeletesNothing(): void {
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('del_cat' => '1', 'cat_to_delete' => '0')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('del_cat' => '1', 'cat_to_delete' => '99')));
		$this->assertStringStartsWith('302 /admin_categories?a=1&b=2 [redirect]', $this->page(array('del_cat_comply' => '1', 'del_cat_cancel' => '1', 'cat_to_delete' => '7')));

		$this->assertSame(array(), $this->categories->log);
	}

	public function testOnlyTheChangedCategoriesAreUpdated(): void {
		$steps = array();
		$this->kit->events->observe(CategoryChangeStep::class, function (CategoryChangeStep $event) use (&$steps): void {
			$names = array();
			foreach ($event->categories() as $category)
				$names[] = $category->id().'='.$category->name().'@'.$category->position();

			$steps[] = $event->step().' '.implode(' ', $names);
		});

		$this->page(array('update' => '1', 'cat_name' => array('7' => 'Talk & <chat>', '3' => ' Old news ', '12' => 'Added since'), 'cat_order' => array('7' => '1', '3' => '9', '12' => '4')));

		$this->assertSame(array('update 3 Old news@9', 'quickjump'), $this->categories->log);
		$this->assertSame(array('updating 7=Talk & <chat>@1 3=Old news@9 12=Added since@4', 'updated 7=Talk & <chat>@1 3=Old news@9 12=Added since@4'), $steps);
		$this->assertSame(array('Categories updated.'), $this->kit->flash->info);
	}

	public function testAnUpdateWithAnEmptyNameOrANegativePositionStoresNothing(): void {
		$this->assertStringContainsString('<p>You must enter a name for the category</p>', $this->page(array('update' => '1', 'cat_name' => array('3' => 'Changed', '7' => ' '), 'cat_order' => array('3' => '5', '7' => '1'))));
		$this->assertStringContainsString('<p>Position must be a positive integer value</p>', $this->page(array('update' => '1', 'cat_name' => array('3' => 'News'), 'cat_order' => array('3' => '-1'))));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('update' => '1', 'cat_name' => 'News', 'cat_order' => array('3' => '1'))));

		$this->assertSame(array(), $this->categories->log);
	}
}
