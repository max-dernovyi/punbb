<?php
/**
 * The message page, built with no forum: its head and markup, what an observer
 * changes about the message, the markup added at its positions, the message
 * inside a page whose header is built, and the JSON a script gets.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Message\Event\MessageJsonSending;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;

require_once __DIR__.'/PageFakes.php';

class MessagePageTest extends TestCase {
	private PageKit $kit;

	protected function setUp(): void {
		$this->kit = new PageKit(array(MessageShowing::class, MessageJsonSending::class, MessageRendering::class));
	}

	public function testAMessageIsAPageOfItsOwnUnderTheDefaultHeading(): void {
		$response = $this->kit->messages()->respond(new Html('No <b>way</b>.'));

		$this->assertSame(200, $response->status);
		$this->assertSame('text/html; charset=utf-8', $response->headers['Content-type']);
		$this->assertSame("[message]\t<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>[Forum message]</span></h2>\n\t</div>\n\n\t<div class=\"main-content main-message\">\n\t\t<p>No <b>way</b>.</p>\n\t</div>", $response->body);

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('message', $head->id);
		$this->assertSame('Board & Co', $head->crumbs[0]->text);
		$this->assertSame('/index?a=1&amp;b=2', $head->crumbs[0]->link?->html);
		$this->assertSame('[Forum message]', $head->crumbs[1]->text);
		$this->assertNull($head->crumbs[1]->link);
		$this->assertSame(array('MessageShowing', 'MessageRendering:start', 'MessageRendering:end'), $this->kit->events->dispatched);
	}

	public function testTheHeadingTheLinkAndTheOptionsAreShownWhenGiven(): void {
		$response = $this->kit->messages()->respond(new Html('Done.'), new Html('<a href="/x">Back</a>'), new Html('Heading'), array(new Html('<span>one</span>'), new Html('<span>two</span>')));

		$this->assertSame("[message]\t<div class=\"main-head\">\n\n\t\t<p class=\"options\"><span>one</span> <span>two</span></p>\t\t<h2 class=\"hn\"><span>Heading</span></h2>\n\t</div>\n\n\t<div class=\"main-content main-message\">\n\t\t<p>Done. <span><a href=\"/x\">Back</a></span></p>\n\t</div>", $response->body);
	}

	public function testObserversChangeTheMessageAndAddMarkupAroundIt(): void {
		$this->kit->events->observe(MessageShowing::class, fn (MessageShowing $event) => $event->change($event->message().' Changed.', '', 'New heading'));
		$this->kit->events->observe(MessageRendering::class, fn (MessageRendering $event) => $event->append('<i>'.$event->position().'</i>'));

		$body = $this->kit->messages()->respond(new Html('Said.'))->body;

		$this->assertStringStartsWith("[message]\t<i>start</i>\t<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>New heading</span></h2>", $body);
		$this->assertStringEndsWith("<p>Said. Changed.</p>\n\t</div>\n<i>end</i>", $body);
	}

	public function testAScriptGetsTheMessageAsJson(): void {
		$this->kit->events->observe(MessageJsonSending::class, fn (MessageJsonSending $event) => $event->change($event->code() - 1, $event->message().'!'));

		$response = $this->kit->messages()->respond(new Html('Nope'), json: true);

		$this->assertSame('{"code":-2,"message":"Nope!"}', $response->body);
		$this->assertSame('application/json; charset=utf-8', $response->headers['Content-type']);
		$this->assertSame(array(), $this->kit->chromes->opened);
		$this->assertSame(array('MessageShowing', 'MessageJsonSending'), $this->kit->events->dispatched);
	}

	public function testInsideABuiltHeaderTheMessageIsTheMainRegionWithoutADefaultHeading(): void {
		$main = $this->kit->messages()->inside(new Html('Inline.'));

		$this->assertSame("\t<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span></span></h2>\n\t</div>\n\n\t<div class=\"main-content main-message\">\n\t\t<p>Inline.</p>\n\t</div>", $main->html);
		$this->assertSame(array(), $this->kit->chromes->opened);
	}

	public function testAPositionTheMessageDoesNotHaveIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		new MessageRendering('middle');
	}
}
