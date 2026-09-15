<?php
/**
 * admin/index.php as a module, built with no forum: who may see it, the boxes
 * an administrator and a moderator get and their numbers as observers add
 * some, the alerts the header raised, the update check, the database figures
 * and phpinfo().
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\AdminIndex\Api\BoardInformationInterface;
use PunBB\Module\AdminIndex\Api\Data\DatabaseInterface;
use PunBB\Module\AdminIndex\Controller\InformationController;
use PunBB\Module\AdminIndex\Event\InformationRendering;
use PunBB\Module\AdminIndex\Event\InformationRequested;
use PunBB\Module\AdminIndex\Event\PhpInfoShowing;
use PunBB\Module\AdminIndex\Model\Database;
use PunBB\Module\AdminIndex\Model\ServerEnvironment;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;

require_once __DIR__.'/PageFakes.php';

final class FakeBoardInformation implements BoardInformationInterface {
	/** @var list<string> */
	public array $asked = array();

	public DatabaseInterface $database;

	public function __construct() {
		$this->database = new Database('SQLite3', '3.46.1', null, null);
	}

	public function onlineCount(): int {
		$this->asked[] = 'onlineCount';

		return 7;
	}

	public function hotfixes(): array {
		$this->asked[] = 'hotfixes';

		return array('hotfix_a b', 'hotfix_2');
	}

	public function database(): DatabaseInterface {
		$this->asked[] = 'database';

		return $this->database;
	}
}

final class FakeServerEnvironment extends ServerEnvironment {
	public bool $phpInfoAllowed = true;

	public function loadAverages(): ?array { return array(0.123, 1.0, 2.5); }

	public function accelerator(): ?array { return array('Some <Cache>', 'http://cache.example/'); }

	public function operatingSystem(): string { return 'Linux'; }

	public function phpVersion(): string { return '8.4.0'; }

	public function allowsPhpInfo(): bool { return $this->phpInfoAllowed; }

	public function phpInfo(): string { return '<html>phpinfo</html>'; }
}

class InformationControllerTest extends TestCase {
	private PageKit $kit;

	private FakeBoardInformation $information;

	private FakeServerEnvironment $environment;

	protected function setUp(): void {
		$this->kit = new PageKit(array(InformationRequested::class, PhpInfoShowing::class, InformationRendering::class, MessageShowing::class, MessageRendering::class));
		$this->kit->language->real = array('admin_common', 'admin_index');
		$this->kit->settings->values += array('o_cur_version' => '1.5.0', 'o_check_for_updates' => '0');
		$this->information = new FakeBoardInformation();
		$this->environment = new FakeServerEnvironment();
	}

	private function administrator(): void {
		$this->kit->visitor->administrator = true;
		$this->kit->visitor->moderating = true;
	}

	private function page(array $query = array()): string {
		$controller = new InformationController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->information, $this->environment,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter);

		return $controller->handle(new Request('GET', '/', 'admin/index.php', $query))->body;
	}

	public function testAVisitorWhoModeratesNothingGetsAMessage(): void {
		$this->assertStringContainsString('<p>[No permission]</p>', $this->page());
		$this->assertSame(array('InformationRequested', 'MessageShowing', 'MessageRendering:start', 'MessageRendering:end'), $this->kit->events->dispatched);
		$this->assertSame(array(), $this->information->asked);
	}

	public function testAnAdministratorGetsEveryBoxInOrder(): void {
		$this->administrator();

		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('admin-information', $head->id);
		$this->assertSame('start', $head->section);
		$this->assertSame(array('Board & Co', 'Administration', 'Start', 'Information'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringStartsWith("[admin-information]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Welcome to PunBB administration control panel</span></h2>\n\t</div>\n\t<div class=\"main-content main-frm\">\n\t\t<div class=\"ct-group\">\n\t\t\t<div class=\"ct-set group-item1\">", $body);
		$this->assertStringContainsString('<li><span>PunBB 1.5.0</span></li>', $body);
		$this->assertStringContainsString('<li><span><a href="https://punbb.informer.com/update/?version=1.5.0&amp;hotfixes=hotfix_a+b,hotfix_2">Check for updates</a></span></li>', $body);
		$this->assertStringContainsString('<div class="ct-set group-item3">', $body);
		$this->assertStringContainsString('<p><span>0.12 1.00 2.50 (7 users online)</span></p>', $body);
		$this->assertStringContainsString("<li><span>Operating system: Linux</span></li>\n\t\t\t\t\t\t<li><span>PHP: 8.4.0 - <a href=\"/admin_index?a=1&amp;b=2?action=phpinfo\">Show info</a></span></li>\n\t\t\t\t\t\t<li><span>Accelerator: <a href=\"http://cache.example/\">Some &lt;Cache&gt;</a></span></li>", $body);
		$this->assertStringContainsString("<div class=\"ct-set group-item5\">\n\t\t\t\t<div class=\"ct-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\"><span>Database</span></h3>\n\t\t\t\t\t<ul class=\"data-list\">\n\t\t\t\t\t\t<li><span>SQLite3 3.46.1</span></li>\n\t\t\t\t\t</ul>", $body);
		$this->assertStringEndsWith("</div>\n\t\t</div>\n\t</div>", $body);
		$this->assertSame(array('hotfixes', 'onlineCount', 'database'), $this->information->asked);
		$this->assertSame(array('InformationRequested', 'InformationRendering:main_output_start', 'InformationRendering:pre_version', 'InformationRendering:pre_community', 'InformationRendering:pre_server_load',
			'InformationRendering:pre_environment', 'InformationRendering:pre_database', 'InformationRendering:items_end', 'InformationRendering:end'), $this->kit->events->dispatched);
	}

	public function testAModeratorGetsNeitherTheEnvironmentNorTheUpdateCheck(): void {
		$this->kit->visitor->moderating = true;

		$body = $this->page(array('action' => 'phpinfo'));

		$this->assertSame(array('Board & Co', 'Administration', 'Information'), array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs));
		$this->assertStringContainsString('<div class="ct-set group-item3">', $body);
		$this->assertStringNotContainsString('group-item4', $body);
		$this->assertStringNotContainsString('Environment', $body);
		$this->assertStringNotContainsString('update', $body);
		$this->assertSame(array('onlineCount'), $this->information->asked);
		$this->assertNotContains('InformationRendering:pre_database', $this->kit->events->dispatched);
		$this->assertContains('InformationRendering:pre_environment', $this->kit->events->dispatched);
	}

	public function testTheBoxesAreNumberedOnFromWhereObserversLeaveTheCount(): void {
		$this->administrator();
		$this->kit->events->observe(InformationRendering::class, function (InformationRendering $event): void {
			$event->append('<!--'.$event->position().' '.$event->itemCount().'-->');

			if ($event->position() === InformationRendering::PRE_COMMUNITY)
				$event->count($event->itemCount() + 1);
		});

		$body = $this->page();

		$this->assertStringStartsWith("[admin-information]<!--main_output_start 0-->\t<div class=\"main-subhead\">", $body);
		$this->assertStringContainsString("<!--pre_community 1-->\t\t\t<div class=\"ct-set group-item3\">", $body);
		$this->assertStringContainsString("<!--pre_database 5-->\t\t\t<div class=\"ct-set group-item6\">", $body);
		$this->assertStringContainsString("</div>\n<!--items_end 6-->\t\t</div>\n\t</div>\n<!--end 6-->", $body);
	}

	public function testTheAlertsTheHeaderRaisedAreListed(): void {
		$this->administrator();
		$this->kit->chromes->alerts = array('maintenance' => new Html('<p id="maint-alert">On</p>'), 'probe' => new Html('<p>Probe</p>'));

		$this->assertStringContainsString("<div id=\"admin-alerts\" class=\"ct-set warn-set\">\n\t\t\t<div class=\"ct-box warn-box\">\n\t\t\t\t<h3 class=\"ct-legend hn warn\"><span>Administrator Alerts</span></h3>\n\t\t\t\t<p id=\"maint-alert\">On</p> <p>Probe</p>\n\t\t\t</div>\n\t\t</div>", $this->page());
	}

	public function testAnAutomaticUpdateCheckIsSaidSo(): void {
		$this->administrator();
		$this->kit->settings->values['o_check_for_updates'] = '1';

		$this->assertStringContainsString('<li><span>This board is setup to automatically check for updates', $this->page());
		$this->assertNotContains('hotfixes', $this->information->asked);
	}

	public function testMySqlReportsItsRowsAndItsSize(): void {
		$this->administrator();
		$this->information->database = new Database('MySQL', '8.4.3', 1234567, 5 * 1024 * 1024);

		$body = $this->page();

		$this->assertStringContainsString("<li><span>MySQL 8.4.3</span></li>\n\t\t\t\t\t\t<li><span>Rows: 1&#160;234&#160;567</span></li>\n\t\t\t\t\t\t<li><span>Size: 5.00 MB</span></li>", $body);

		$this->information->database = new Database('MySQL', '8.4.3', 3, 2048);
		$this->assertStringContainsString('<li><span>Size: 2.00 KB</span></li>', $this->page());
	}

	public function testAnAdministratorGetsPhpInfoUnlessTheServerForbidsIt(): void {
		$this->administrator();

		$this->assertSame('<html>phpinfo</html>', $this->page(array('action' => 'phpinfo')));
		$this->assertSame(array('InformationRequested', 'PhpInfoShowing'), $this->kit->events->dispatched);
		$this->assertSame(array(), $this->kit->chromes->opened);

		$this->environment->phpInfoAllowed = false;
		$this->assertStringContainsString('<p>The PHP function phpinfo() has been disabled on this server.</p>', $this->page(array('action' => 'phpinfo')));
	}
}
