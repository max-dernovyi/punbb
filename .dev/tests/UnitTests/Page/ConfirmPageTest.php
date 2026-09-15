<?php
/**
 * The confirmation of a form whose token did not match, built with no forum:
 * the fields it posts again, the token and the page it returns to, the
 * redirect a cancel gets, an observer letting the request through, and the
 * JSON a script gets.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectJsonSending;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Message\Page\ConfirmPage;

require_once __DIR__.'/PageFakes.php';

class ConfirmPageTest extends TestCase {
	private PageKit $kit;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ConfirmFormRequested::class, ConfirmFormRendering::class, RedirectShowing::class, RedirectJsonSending::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('common');
	}

	private function page(): ConfirmPage {
		return new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens);
	}

	public function testTheFormPostsEveryFieldAgainWithAFreshToken(): void {
		$response = $this->page()->respond(array('csrf_token' => 'stale', 'prev_url' => 'posted', 'form' => array('title' => 'A <b>', 'tags' => array('x', 'y')), 'save' => '1'));

		$this->assertNotNull($response);
		$this->assertSame(200, $response->status);

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('dialogue', $head->id);
		$this->assertSame(array('Board & Co', 'Confirm action'), array($head->crumbs[0]->text, $head->crumbs[1]->text));

		$this->assertStringStartsWith("[dialogue]<div id=\"brd-main\" class=\"main\">\n\t<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Please confirm or cancel your last action</span></h2>", $response->body);
		$this->assertStringContainsString('<form class="frm-form" method="post" accept-charset="utf-8" action="http://forum.test/current.php?x=1&amp;y=2">', $response->body);
		$this->assertStringContainsString("<div class=\"hidden\">\n\t\t\t\t".
			'<input type="hidden" name="csrf_token" value="token-for-'.md5('http://forum.test/current.php?x=1&y=2').'" />'."\n\t\t\t\t".
			'<input type="hidden" name="prev_url" value="http://forum.test/before?a=1&amp;b=&quot;2&quot;" />'."\n\t\t\t\t".
			'<input type="hidden" name="form[title]" value="A &lt;b&gt;" />'."\n\t\t\t\t".
			'<input type="hidden" name="form[tags][0]" value="x" />'."\n\t\t\t\t".
			'<input type="hidden" name="form[tags][1]" value="y" />'."\n\t\t\t\t".
			'<input type="hidden" name="save" value="1" />'."\n\t\t\t</div>", $response->body);
		$this->assertStringEndsWith("</form>\n\t</div>\n</div>", $response->body);
		$this->assertSame(array('ConfirmFormRequested', 'ConfirmFormRendering:start', 'ConfirmFormRendering:end'), $this->kit->events->dispatched);
	}

	public function testObserversChangeTheHiddenFieldsAndAddMarkupAroundTheForm(): void {
		$this->kit->events->observe(ConfirmFormRendering::class, function (ConfirmFormRendering $event): void {
			$event->append('<!--'.$event->position().' '.$event->action().'-->');

			if ($event->position() === ConfirmFormRendering::START)
			{
				$event->remove('save');
				$event->set('probe', '<input type="hidden" name="probe" />');
			}
		});

		$body = (string) $this->page()->respond(array('save' => '1'))?->body;

		$this->assertStringStartsWith('[dialogue]<!--start http://forum.test/current.php?x=1&y=2--><div id="brd-main"', $body);
		$this->assertStringContainsString('value="http://forum.test/before?a=1&amp;b=&quot;2&quot;" />'."\n\t\t\t\t".'<input type="hidden" name="probe" />'."\n\t\t\t</div>", $body);
		$this->assertStringEndsWith("</div>\n<!--end http://forum.test/current.php?x=1&y=2-->", $body);
	}

	public function testACancelIsSentBackWhereTheVisitorCameFrom(): void {
		$body = (string) $this->page()->respond(array('confirm_cancel' => '1', 'prev_url' => 'http://forum.test/topic?a=1&b=2'))?->body;

		$this->assertStringContainsString('URL=http://forum.test/topic?a=1&amp;b=2" />', $body);
		$this->assertStringContainsString('<span>Operation cancelled. Redirecting…</span>', $body);
		$this->assertSame(array('RedirectShowing', 'RedirectHeadAssembling'), $this->kit->events->dispatched);
	}

	public function testAnObserverLetsTheRequestThroughUnconfirmed(): void {
		$this->kit->events->observe(ConfirmFormRequested::class, fn (ConfirmFormRequested $event) => $event->letThrough());

		$this->assertNull($this->page()->respond(array('save' => '1')));
		$this->assertSame(array(), $this->kit->chromes->opened);
	}

	/** FORUM_DISABLE_CSRF_CONFIRM: nothing is asked, not even a cancel is answered. */
	public function testABoardThatConfirmsNothingLetsEveryRequestThrough(): void {
		$this->kit->tokens->confirms = false;

		$this->assertNull($this->page()->respond(array('save' => '1')));
		$this->assertNull($this->page()->respond(array('confirm_cancel' => '1', 'prev_url' => 'http://forum.test/')));
		$this->assertSame(array(), $this->kit->events->dispatched);
	}

	public function testAScriptGetsTheConfirmationAsJson(): void {
		$this->kit->events->observe(RedirectJsonSending::class, fn (RedirectJsonSending $event) => $event->set('added', 'yes'));

		$response = $this->page()->respond(array('csrf_token' => 'stale', 'form' => array('title' => 'A "b"')), true);

		$this->assertNotNull($response);
		$this->assertSame(array(
			'code'			=> -3,
			'message'		=> 'Unable to confirm security token. A likely cause for this is that some time passed between when you first entered the page and when you submitted a form or clicked a link. If that is the case and you would like to continue with your action, please click the Confirm button. Otherwise, you should click the Cancel button to return to where you were.',
			'csrf_token'	=> 'token-for-'.md5('http://forum.test/current.php?x=1&y=2'),
			'prev_url'		=> 'http://forum.test/before?a=1&amp;b=&quot;2&quot;',
			'post_data'		=> array('form[title]' => 'A &quot;b&quot;', 'added' => 'yes'),
		), json_decode($response->body, true));
	}
}
