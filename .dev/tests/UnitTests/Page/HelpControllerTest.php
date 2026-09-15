<?php
/**
 * help.php as a module, built with no forum: who may read it, which section it
 * shows, the markup observers add at each position, and the smilies they list.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Help\Controller\HelpController;
use PunBB\Module\Help\Event\HelpRendering;
use PunBB\Module\Help\Event\HelpRequested;
use PunBB\Module\Help\Event\SmiliesListing;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

class HelpControllerTest extends TestCase {
	private PageKit $kit;

	protected function setUp(): void {
		$this->kit = new PageKit(array(HelpRequested::class, HelpRendering::class, SmiliesListing::class, MessageShowing::class, MessageRendering::class));
		$this->kit->language->real = array('help');
	}

	private function help(array $query): string {
		$controller = new HelpController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(),
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter);

		return $controller->handle(new Request('GET', '/', 'help.php', $query))->body;
	}

	public function testAVisitorWhoMayNotReadTheBoardGetsAMessage(): void {
		$this->kit->visitor->permissions = array();

		$this->assertStringContainsString('<p>[No view]</p>', $this->help(array('section' => 'bbcode')));
		$this->assertSame(array('HelpRequested', 'MessageShowing', 'MessageRendering:start', 'MessageRendering:end'), $this->kit->events->dispatched);
	}

	public function testNoSectionIsABadRequest(): void {
		foreach (array(array(), array('section' => ''), array('section' => '0')) as $query)
			$this->assertStringContainsString('<p>[Bad request]</p>', $this->help($query));
	}

	public function testTheBbcodeSectionPlacesTheMarkupOfEachOfItsPositions(): void {
		$this->kit->events->observe(HelpRendering::class, fn (HelpRendering $event) => $event->append('<!--'.$event->position().':'.$event->section().'-->'));

		$body = $this->help(array('section' => 'bbcode'));

		$this->assertStringStartsWith("[help]<!--start:bbcode--><div id=\"brd-main\" class=\"main\">\n\n<div class=\"main-head\">\n\t<h1 class=\"hn\"><span>Help</span></h1>\n</div>\n\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Help with [BBCode]</span></h2>", $body);
		$this->assertStringContainsString("</div>\n<!--text_styles:bbcode-->\t\t</div>", $body);
		$this->assertStringContainsString("[/email]</code> <span>produces</span>\n\t\t\t\t<samp><a href=\"mailto:name@example.com\">My email address</a></samp>\n\t\t\t</div>\n<!--links:bbcode-->\t\t</div>", $body);
		$this->assertStringContainsString('<code>[url=http://forum.test/]Board &amp; Co[/url]</code>', $body);
		$this->assertStringEndsWith("</div>\n<!--bbcode:bbcode-->\t</div>\n<!--section:bbcode-->\n</div>\n<!--end:bbcode-->", $body);
		$this->assertSame(array('HelpRequested', 'HelpRendering:start', 'HelpRendering:text_styles', 'HelpRendering:links', 'HelpRendering:bbcode', 'HelpRendering:section', 'HelpRendering:end'), $this->kit->events->dispatched);

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('help', $head->id);
		$this->assertSame('/help?a=1&amp;b=2', $head->crumbs[0]->link?->html);
		$this->assertSame('Help', $head->crumbs[1]->text);
	}

	public function testTheImageSectionHasItsOwnPosition(): void {
		$this->kit->events->observe(HelpRendering::class, fn (HelpRendering $event) => $event->append('<!--'.$event->position().'-->'));

		$body = $this->help(array('section' => 'img'));

		$this->assertStringContainsString("<img src=\"http://forum.test/img/test.png\" alt=\"PunBB bbcode test\" /></samp>\n\t\t\t</div>\n\t\t</div>\n\t\t<!--images-->\t</div>\n<!--section-->\n</div>", $body);
	}

	public function testTheSmiliesAreListedByImageAsTheObserversLeaveThem(): void {
		$this->kit->events->observe(SmiliesListing::class, function (SmiliesListing $event): void {
			$event->remove('=)');
			$event->set(':<', 'sad.png');
		});

		$body = $this->help(array('section' => 'smilies'));

		$this->assertStringContainsString("<div class=\"entry-content\">\n\t\t\t\t<p>:) <span>produces</span> <img src=\"http://forum.test/img/smilies/smile.png\" width=\"15\" height=\"15\" alt=\":)\" /></p>\n".
			"\t\t\t\t<p>:( [and] :&lt; <span>produces</span> <img src=\"http://forum.test/img/smilies/sad.png\" width=\"15\" height=\"15\" alt=\":(\" /></p>\n\t\t\t</div>", $body);
		$this->assertContains('SmiliesListing', $this->kit->events->dispatched);
	}

	public function testAnUnknownSectionShowsTheHeadingAlone(): void {
		$this->assertSame("[help]<div id=\"brd-main\" class=\"main\">\n\n<div class=\"main-head\">\n\t<h1 class=\"hn\"><span>Help</span></h1>\n</div>\n\n</div>", $this->help(array('section' => 'url')));
		$this->assertSame("[help]<div id=\"brd-main\" class=\"main\">\n\n<div class=\"main-head\">\n\t<h1 class=\"hn\"><span>Help</span></h1>\n</div>\n\n</div>", $this->help(array('section' => array('bbcode'))));
	}
}
