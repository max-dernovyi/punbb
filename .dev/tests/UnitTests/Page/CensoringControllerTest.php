<?php
/**
 * admin/censoring.php as a module, built with no forum: who may see it, the
 * form adding a word and the stored words numbered as observers add fields,
 * the note that there are none, and adding, updating and removing a word.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Censoring\Api\CensorsInterface;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\Censoring\Cache\CensorCacheInterface;
use PunBB\Module\Censoring\Controller\CensoringController;
use PunBB\Module\Censoring\Event\CensorChangeStep;
use PunBB\Module\Censoring\Event\CensoredWordRendering;
use PunBB\Module\Censoring\Event\CensoringRendering;
use PunBB\Module\Censoring\Event\CensoringRequested;
use PunBB\Module\Censoring\Model\Censor;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;

require_once __DIR__.'/PageFakes.php';

final class FakeCensors implements CensorsInterface, CensorCacheInterface {
	/** @var list<Censor> */
	public array $words = array();

	/** @var list<string> */
	public array $log = array();

	public function all(): array {
		$this->log[] = 'all';

		return $this->words;
	}

	public function add(CensorInterface ...$censors): void {
		foreach ($censors as $censor)
			$this->log[] = 'add '.$censor->searchFor().'='.$censor->replaceWith();
	}

	public function update(CensorInterface ...$censors): void {
		foreach ($censors as $censor)
			$this->log[] = 'update '.$censor->id().' '.$censor->searchFor().'='.$censor->replaceWith();
	}

	public function remove(int ...$ids): void {
		$this->log[] = 'remove '.implode(',', $ids);
	}

	public function rebuild(): void {
		$this->log[] = 'rebuild';
	}
}

class CensoringControllerTest extends TestCase {
	private PageKit $kit;

	private FakeCensors $censors;

	protected function setUp(): void {
		$this->kit = new PageKit(array(CensoringRequested::class, CensorChangeStep::class, CensoringRendering::class, CensoredWordRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_censoring', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->visitor->moderating = true;
		$this->censors = new FakeCensors();
		$this->censors->words = array(new Censor(4, 'd<a>rn', 'd*rn'), new Censor(9, 'zap', '"z"'));
	}

	private function page(array $post = array()): string {
		$controller = new CensoringController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			$this->censors, $this->censors, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/censoring.php', array(), $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testAVisitorWhoModeratesNothingGetsAMessage(): void {
		$this->kit->visitor->moderating = false;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('remove' => array('4' => '1'))));
		$this->assertSame(array(), $this->censors->log);
	}

	public function testTheFormAddsAWordAndTheStoredWordsAreEditedBelow(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-censoring', 'settings'), array($head->id, $head->section));
		$this->assertSame(array('Board & Co', 'Administration', 'Censoring'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$token = 'token-for-'.md5('/admin_censoring?a=1&amp;b=2?action=foo');
		$this->assertStringStartsWith("200  [admin-censoring]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Add, edit or remove censored words</span></h2>", $body);
		$this->assertStringContainsString('<p>Enter a word that you want to censor', $body);
		$this->assertStringNotContainsString('Settings &rarr; Features', $body, 'only an administrator is sent to the setting');
		$this->assertSame(2, substr_count($body, '<input type="hidden" name="csrf_token" value="'.$token.'" />'));
		$this->assertStringContainsString("<fieldset class=\"frm-group frm-hdgroup group1\">\n\t\t\t\t<legend class=\"group-legend\"><span>Add word</span></legend>\n\t\t\t\t<fieldset class=\"mf-set set1 mf-head\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld2" name="new_replace_with" size="24" maxlength="60" required />', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><span>Edit or remove existing censored word</span></legend>\n\t\t\t\t<fieldset class=\"mf-set mf-extra set1 mf-head\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld3" name="search_for[4]" value="d&lt;a&gt;rn" size="24" maxlength="60" required />', $body);
		$this->assertStringContainsString('<fieldset class="mf-set mf-extra set2 mf-extra">', $body);
		$this->assertStringContainsString('<input type="text" id="fld6" name="replace_with[9]" value="&quot;z&quot;" size="24" maxlength="60" required />', $body);
		$this->assertStringContainsString('<input type="submit" name="update[9]" value="Update" /> <input type="submit" name="remove[9]" value="Remove" formnovalidate />', $body);
		$this->assertStringEndsWith("</form>\n\t</div>", $body);
		$this->assertSame(array('all'), $this->censors->log);
	}

	public function testAnAdministratorIsSentToTheSettingAndThroughTheSettingsCrumb(): void {
		$this->kit->visitor->administrator = true;
		$body = $this->page();

		$this->assertSame(array('Board & Co', 'Administration', 'Settings', 'Censoring'), array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs));
		$this->assertStringContainsString('The search is case insensitive. For this to have any effect "<strong>Censoring</strong>" must be enabled in <a class="nowrap" href="/admin_settings_features?a=1&amp;b=2">Settings &rarr; Features</a>.</p>', $body);
	}

	public function testWithoutWordsThePageSaysSo(): void {
		$this->censors->words = array();

		$body = $this->page();

		$this->assertStringContainsString("</form>\n\t\t<div class=\"frm-form\">\n\t\t\t<div class=\"ct-box\">\n\t\t\t\t<p>No censor words in list.</p>", $body);
		$this->assertSame(1, substr_count($body, '<form'));
	}

	public function testObserversAddFieldsAndTheFormsNumberOn(): void {
		$words = array();
		$this->kit->events->observe(CensoringRendering::class, function (CensoringRendering $event): void {
			if ($event->position() === CensoringRendering::PRE_ADD_REPLACE_WITH)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
			}

			if ($event->position() === CensoringRendering::END)
				$event->append('<!--end-->');
		});
		$this->kit->events->observe(CensoredWordRendering::class, function (CensoredWordRendering $event) use (&$words): void {
			if ($event->position() === CensoredWordRendering::PRE_EDIT_WORD_FIELDSET)
				$words[] = $event->number().' '.$event->censor()->searchFor().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();

			if ($event->position() === CensoredWordRendering::EDIT_WORD_FIELDSET_END && $event->number() === 1)
			{
				$event->append('<fieldset class="set'.($event->itemCount() + 1).'"></fieldset>');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount());
			}
		});

		$body = $this->page();

		$this->assertStringContainsString('<input id="fld2" />', $body);
		$this->assertStringContainsString('name="new_replace_with"', $body);
		$this->assertStringContainsString('<input type="text" id="fld3" name="new_replace_with"', $body);
		$this->assertStringContainsString('<fieldset class="set2"></fieldset>', $body);
		$this->assertStringContainsString('<fieldset class="mf-set mf-extra set3 mf-extra">', $body);
		$this->assertStringEndsWith('<!--end-->', $body);
		$this->assertSame(array('1 d<a>rn at 1/0/3', '2 zap at 1/2/5'), $words);
	}

	public function testAWordIsAddedTheListRebuiltAndTheVisitorSentBack(): void {
		$steps = array();
		$this->kit->events->observe(CensorChangeStep::class, function (CensorChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->censor()->searchFor().' '.implode(',', $this->censors->log);
		});

		$this->assertStringStartsWith('302 /admin_censoring?a=1&b=2 [redirect]', $this->page(array('add_word' => '1', 'new_search_for' => ' heck ', 'new_replace_with' => 'h*ck')));
		$this->assertSame(array('add heck=h*ck', 'rebuild'), $this->censors->log);
		$this->assertSame(array('adding heck ', 'added heck add heck=h*ck,rebuild'), $steps);
		$this->assertSame(array('Censor word added.'), $this->kit->flash->info);
	}

	public function testAWordIsUpdatedAndRemovedByTheButtonsId(): void {
		$this->page(array('update' => array('9' => 'Update'), 'search_for' => array('4' => 'x', '9' => 'zip'), 'replace_with' => array('9' => 'z*p')));
		$this->page(array('remove' => array('4' => 'Remove')));

		$this->assertSame(array('update 9 zip=z*p', 'rebuild', 'remove 4', 'rebuild'), $this->censors->log);
		$this->assertSame(array('Censor word updated.', 'Censor word removed.'), $this->kit->flash->info);
	}

	public function testAWordWithoutBothTextsOrAButtonThatIsNoListIsRefused(): void {
		$this->assertStringContainsString('<p>You must enter both text to search for and text to replace with.</p>', $this->page(array('add_word' => '1', 'new_search_for' => 'heck', 'new_replace_with' => array('x'))));
		$this->assertStringContainsString('<p>You must enter both text to search for and text to replace with.</p>', $this->page(array('update' => array('9' => '1'), 'search_for' => 'zip')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('remove' => '4')));

		$this->assertSame(array(), $this->censors->log);
	}
}
