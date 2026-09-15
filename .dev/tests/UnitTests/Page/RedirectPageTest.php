<?php
/**
 * The redirect page, built with no forum: its head and message, the delay and
 * the Location header, the destination made safe after observers change it,
 * the head observers assemble, and the JSON a script gets.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectJsonSending;
use PunBB\Module\Message\Event\RedirectShowing;

require_once __DIR__.'/PageFakes.php';

class RedirectPageTest extends TestCase {
	private PageKit $kit;

	protected function setUp(): void {
		$this->kit = new PageKit(array(RedirectShowing::class, RedirectJsonSending::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('common');
		$this->kit->chromes->log = array();
	}

	public function testADelayShowsAPageThatForwardsTheBrowserAfterIt(): void {
		$this->kit->settings->values['o_redirect_delay'] = '1';

		$response = $this->kit->redirects()->respond('viewtopic.php?id=1&amp;p=2', new Html('Post <em>saved</em>.'));

		$this->assertSame(200, $response->status);
		$this->assertArrayNotHasKey('Location', $response->headers);
		$this->assertSame('text/html; charset=utf-8', $response->headers['Content-type']);
		$this->assertSame(array('bare redirect'), $this->kit->chromes->log);
		$this->assertSame('[redirect][head]<meta http-equiv="refresh" content="1;URL=http://forum.test/viewtopic.php?id=1&amp;p=2" />'."\n".
			'<title> Redirecting… — Board &amp; Co</title>'."\n".'<!-- theme --><link rel="stylesheet" />'."\n".
			"[main]\t<div id=\"brd-main\" class=\"main basic\">\n\n\t<div class=\"main-head\">\n\t\t<h1 class=\"hn\"><span>Post <em>saved</em>. Redirecting…</span></h1>\n\t</div>\n\n".
			"\t<div class=\"main-content main-message\">\n\t\t<p>You should automatically be forwarded to a new page in 1 second.<span> <a href=\"http://forum.test/viewtopic.php?id=1&amp;p=2\">Click here if you do not want to wait any longer (or if your browser does not automatically forward you)</a></span></p>\n\t</div>\n\n</div>", $response->body);
		$this->assertSame(array('RedirectShowing', 'RedirectHeadAssembling'), $this->kit->events->dispatched);
	}

	public function testNoDelaySendsTheBrowserOnAtOnce(): void {
		$this->kit->settings->values['o_redirect_delay'] = '0';

		$response = $this->kit->redirects()->respond('search.php?action=show_new&amp;x=1', new Html('Done.'));

		$this->assertSame(302, $response->status);
		$this->assertSame(array('Location', 'Expires', 'Last-Modified', 'Cache-Control', 'Pragma', 'Content-type'), array_keys($response->headers));
		$this->assertSame('http://forum.test/search.php?action=show_new&x=1', $response->headers['Location']);
		$this->assertStringContainsString('forwarded to a new page in 0 seconds.', $response->body);
	}

	public function testTheDestinationAnObserverLeavesIsMadeSafe(): void {
		$this->kit->events->observe(RedirectShowing::class, fn (RedirectShowing $event) => $event->change('//evil.example/"><script>', $event->message().' Changed.'));

		$body = $this->kit->redirects()->respond('index.php', new Html('Done.'))->body;

		$this->assertStringContainsString('content=";URL=http://forum.test/" />', $body);
		$this->assertStringContainsString('<span>Done. Changed. Redirecting…</span>', $body);
	}

	public function testTheLinkEncodesWhatWouldEndItsAttribute(): void {
		$body = $this->kit->redirects()->respond('/a"b<c>', new Html('Done.'))->body;

		$this->assertStringContainsString('URL=/a&quot;b&lt;c&gt;" />', $body);
		$this->assertStringContainsString('<a href="/a&quot;b&lt;c&gt;">', $body);
	}

	public function testTheHeadIsWhatObserversLeaveOfItsEntries(): void {
		$this->kit->events->observe(RedirectHeadAssembling::class, function (RedirectHeadAssembling $event): void {
			$this->assertSame(array('refresh', 'title', 'style0'), $event->names());
			$event->remove('title');
			$event->set('probe', '<meta name="probe" />');
		});

		$body = $this->kit->redirects()->respond('index.php', new Html('Done.'))->body;

		$this->assertStringContainsString('URL=http://forum.test/index.php" />'."\n".'<!-- theme -->'."\n".'<meta name="probe" /><link rel="stylesheet" />', $body);
		$this->assertStringNotContainsString('<title>', $body);
	}

	public function testAScriptGetsTheRedirectAsJson(): void {
		$this->kit->events->observe(RedirectJsonSending::class, function (RedirectJsonSending $event): void {
			$event->change($event->code(), $event->message().'!');
			$event->changeDestination($event->destination().'#top');
		});

		$response = $this->kit->redirects()->respond('index.php', new Html('Done.'), true);

		$this->assertSame('{"code":-2,"message":"Done.!","destination_url":"http:\/\/forum.test\/index.php#top"}', $response->body);
		$this->assertSame('application/json; charset=utf-8', $response->headers['Content-type']);
		$this->assertSame(array(), $this->kit->chromes->log);
	}
}
