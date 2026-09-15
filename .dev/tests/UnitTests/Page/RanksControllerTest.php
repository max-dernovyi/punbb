<?php
/**
 * admin/ranks.php as a module, built with no forum: only administrators, the
 * form adding a rank and the stored ranks numbered as observers add fields,
 * the note that there are none, and adding, updating and removing a rank with
 * what each refuses.
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
use PunBB\Module\Ranks\Api\Data\RankInterface;
use PunBB\Module\Ranks\Api\RanksInterface;
use PunBB\Module\Ranks\Cache\RankCacheInterface;
use PunBB\Module\Ranks\Controller\RanksController;
use PunBB\Module\Ranks\Event\RankChangeStep;
use PunBB\Module\Ranks\Event\RankRendering;
use PunBB\Module\Ranks\Event\RanksRendering;
use PunBB\Module\Ranks\Event\RanksRequested;
use PunBB\Module\Ranks\Model\Rank;

require_once __DIR__.'/PageFakes.php';

final class FakeRanks implements RanksInterface, RankCacheInterface {
	/** @var list<Rank> */
	public array $ranks = array();

	/** @var list<string> */
	public array $log = array();

	public function all(): array {
		$this->log[] = 'all';

		return $this->ranks;
	}

	public function minPostsTaken(int $minPosts, ?int $exceptId): bool {
		$this->log[] = 'taken '.$minPosts.' '.var_export($exceptId, true);

		foreach ($this->ranks as $rank)
			if ($rank->minPosts() === $minPosts && $rank->id() !== $exceptId)
				return true;

		return false;
	}

	public function add(RankInterface ...$ranks): void {
		foreach ($ranks as $rank)
			$this->log[] = 'add '.$rank->title().' '.$rank->minPosts();
	}

	public function update(RankInterface ...$ranks): void {
		foreach ($ranks as $rank)
			$this->log[] = 'update '.$rank->id().' '.$rank->title().' '.$rank->minPosts();
	}

	public function remove(int ...$ids): void {
		$this->log[] = 'remove '.implode(',', $ids);
	}

	public function rebuild(): void {
		$this->log[] = 'rebuild';
	}
}

class RanksControllerTest extends TestCase {
	private PageKit $kit;

	private FakeRanks $ranks;

	protected function setUp(): void {
		$this->kit = new PageKit(array(RanksRequested::class, RankChangeStep::class, RanksRendering::class, RankRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_ranks', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->visitor->administrator = true;
		$this->ranks = new FakeRanks();
		$this->ranks->ranks = array(new Rank(2, 'New <member>', 0), new Rank(1, 'Member', 10));
	}

	private function page(array $post = array()): string {
		$controller = new RanksController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			$this->ranks, $this->ranks, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/ranks.php', array(), $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testOnlyAnAdministratorManagesRanks(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->moderating = true;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page());
		$this->assertSame(array(), $this->ranks->log);
	}

	public function testTheFormAddsARankAndTheStoredRanksAreEditedBelow(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-ranks', 'users'), array($head->id, $head->section));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Ranks'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringStartsWith("200  [admin-ranks]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Add, edit or remove ranks</span></h2>", $body);
		$this->assertStringContainsString('"<strong>User ranks</strong>" must be enabled in <a class="nowrap" href="/admin_settings_features?a=1&amp;b=2">Settings &rarr; Features</a>.</p>', $body);
		$this->assertStringContainsString("<legend class=\"group-legend\"><strong>New rank details</strong></legend>\n\t\t\t\t<fieldset class=\"mf-set set1 mf-head\">", $body);
		$this->assertStringContainsString('<input type="number" id="fld2" name="new_min_posts" size="7" maxlength="7" required />', $body);
		$this->assertStringContainsString('<fieldset class="mf-set mf-extra set1">', $body);
		$this->assertStringContainsString('<input type="text" id="fld3" name="rank[2]" value="New &lt;member&gt;" size="24" maxlength="50" required />', $body);
		$this->assertStringContainsString('<input type="number" id="fld6" name="min_posts[1]" value="10" size="7" maxlength="7" required />', $body);
		$this->assertStringContainsString('<input type="submit" name="update[1]" value="Update" /> <input type="submit" name="remove[1]" value="Remove" />', $body);
		$this->assertSame(array('all'), $this->ranks->log);
	}

	public function testWithoutRanksThePageSaysSo(): void {
		$this->ranks->ranks = array();

		$this->assertStringContainsString("<p>No ranks in list.</p>", $this->page());
	}

	public function testObserversAddFieldsAndTheFormsNumberOn(): void {
		$this->kit->events->observe(RanksRendering::class, function (RanksRendering $event): void {
			if ($event->position() === RanksRendering::PRE_ADD_RANK_TITLE)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
			}
		});
		$this->kit->events->observe(RankRendering::class, function (RankRendering $event): void {
			if ($event->position() === RankRendering::PRE_EDIT_CUR_RANK_MIN_POSTS)
				$event->append('<!-- '.$event->number().' '.$event->rank()->title().' -->');
		});

		$body = $this->page();

		$this->assertStringContainsString('<input type="text" id="fld2" name="new_rank"', $body);
		$this->assertStringContainsString('<input type="text" id="fld4" name="rank[2]"', $body);
		$this->assertStringContainsString("<!-- 2 Member -->\t\t\t\t\t\t<div class=\"mf-field text\">", $body);
	}

	public function testARankIsAddedWhenItsPostsAreFree(): void {
		$steps = array();
		$this->kit->events->observe(RankChangeStep::class, function (RankChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->rank()->title();
		});

		$this->assertStringStartsWith('302 /admin_ranks?a=1&b=2 [redirect]', $this->page(array('add_rank' => '1', 'new_rank' => ' Veteran ', 'new_min_posts' => '100')));
		$this->assertSame(array('taken 100 NULL', 'add Veteran 100', 'rebuild'), $this->ranks->log);
		$this->assertSame(array('adding Veteran', 'added Veteran'), $steps);
		$this->assertSame(array('Rank added.'), $this->kit->flash->info);
	}

	public function testARankIsUpdatedUnlessAnotherHoldsItsPostsAndRemovedByItsButton(): void {
		$this->assertStringContainsString('<p>There is already a rank with a minimum posts value of 10.</p>', $this->page(array('update' => array('2' => '1'), 'rank' => array('2' => 'Newbie'), 'min_posts' => array('2' => '10'))));
		$this->assertStringStartsWith('302 ', $this->page(array('update' => array('1' => '1'), 'rank' => array('1' => 'Regular'), 'min_posts' => array('1' => '10'))));
		$this->assertStringStartsWith('302 ', $this->page(array('remove' => array('2' => 'Remove'))));

		$this->assertSame(array('taken 10 2', 'taken 10 1', 'update 1 Regular 10', 'rebuild', 'remove 2', 'rebuild'), $this->ranks->log);
	}

	public function testARankWithoutATitleOrWithFewerThanNoPostsIsRefused(): void {
		$this->assertStringContainsString('<p>You must enter a rank title.</p>', $this->page(array('add_rank' => '1', 'new_rank' => '  ', 'new_min_posts' => '5')));
		$this->assertStringContainsString('<p>Minimum posts must be a positive integer value.</p>', $this->page(array('add_rank' => '1', 'new_rank' => 'Odd', 'new_min_posts' => '-1')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('update' => '2')));

		$this->assertSame(array(), $this->ranks->log);
	}
}
