<?php
/**
 * The maintenance message, built with no forum: the administrator's markup
 * under its heading, sent as 503, and an observer letting the visitor in.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MaintenanceShowing;
use PunBB\Module\Message\Page\MaintenancePage;

require_once __DIR__.'/PageFakes.php';

class MaintenancePageTest extends TestCase {
	private PageKit $kit;

	protected function setUp(): void {
		$this->kit = new PageKit(array(MaintenanceShowing::class));
		$this->kit->language->real = array('common');
		$this->kit->chromes->log = array();
	}

	private function page(): MaintenancePage {
		return new MaintenancePage($this->kit->dispatcher, $this->kit->chromes, new TemplateRenderer(), $this->kit->language, $this->kit->settings);
	}

	public function testTheAdministratorsMessageIsSentAs503(): void {
		$this->kit->settings->values['o_maintenance_message'] = "Back\t\tsoon.  <b>Really</b>";

		$response = $this->page()->respond();

		$this->assertNotNull($response);
		$this->assertSame(503, $response->status);
		$this->assertSame(array('Content-type' => 'text/html; charset=utf-8'), $response->headers);
		$this->assertSame(array('bare maintenance'), $this->kit->chromes->log);
		$this->assertSame('[maintenance][head]<!-- theme --><link rel="stylesheet" />'.
			"[main]\t<div class=\"main-head\">\n\t\t<h1 class=\"hn\"><span>Maintenance Mode</span></h1>\n\t</div>\n\t<div class=\"main-content main-message\">\n\t\t<div class=\"ct-box user-box\">\n\t\t\tBack&#160; &#160; soon.&#160; <b>Really</b>\n\t\t</div>\n\t</div>", $response->body);
	}

	public function testAnObserverLetsTheVisitorIn(): void {
		$this->kit->events->observe(MaintenanceShowing::class, fn (MaintenanceShowing $event) => $event->letIn());

		$this->assertNull($this->page()->respond());
		$this->assertSame(array(), $this->kit->chromes->log);
	}
}
