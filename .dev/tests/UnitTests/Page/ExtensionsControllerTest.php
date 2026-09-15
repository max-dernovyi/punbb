<?php
/**
 * admin/extensions.php as a module, built with no forum: who may see it, the
 * lists of extensions and hotfixes and what observers change in them, the
 * install checked against its manifest and dependencies and run, the uninstall
 * and the switch.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Extensions\Api\Data\ExtensionRecordInterface;
use PunBB\Module\Extensions\Api\Data\ExtensionStatusInterface;
use PunBB\Module\Extensions\Api\Data\HookRecordInterface;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Extensions\Api\ExtensionsInterface;
use PunBB\Module\Extensions\Cache\ExtensionCacheInterface;
use PunBB\Module\Extensions\Controller\ExtensionsController;
use PunBB\Module\Extensions\Event\ExtensionActionsAssembling;
use PunBB\Module\Extensions\Event\ExtensionListRendering;
use PunBB\Module\Extensions\Event\ExtensionsActionRequested;
use PunBB\Module\Extensions\Event\ExtensionsRequested;
use PunBB\Module\Extensions\Event\FlipStep;
use PunBB\Module\Extensions\Event\InstallFormRendering;
use PunBB\Module\Extensions\Event\InstallStep;
use PunBB\Module\Extensions\Event\NoticesRendering;
use PunBB\Module\Extensions\Event\UninstallFormRendering;
use PunBB\Module\Extensions\Event\UninstallStep;
use PunBB\Module\Extensions\Installation\ExtensionCodeInterface;
use PunBB\Module\Extensions\Manifest\LocalManifest;
use PunBB\Module\Extensions\Manifest\ManifestReading;
use PunBB\Module\Extensions\Manifest\ManifestsInterface;
use PunBB\Module\Extensions\Model\ExtensionVersion;
use PunBB\Module\Extensions\Model\InstalledExtension;
use PunBB\Module\Extensions\Model\Manifest;
use PunBB\Module\Extensions\Model\ManifestDependency;
use PunBB\Module\Extensions\Model\ManifestHook;
use PunBB\Module\Extensions\Model\ManifestNote;
use PunBB\Module\Extensions\Updates\AvailableHotfix;
use PunBB\Module\Extensions\Updates\LatestVersion;
use PunBB\Module\Extensions\Updates\UpdatesInterface;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Message\Page\ConfirmPage;

require_once __DIR__.'/PageFakes.php';

/** The installed extensions, the manifests under extensions/, the code, the caches and the update services, over plain properties. */
final class FakeExtensionServices implements ExtensionsInterface, ManifestsInterface, ExtensionCodeInterface, ExtensionCacheInterface, UpdatesInterface {
	/** @var array<string, InstalledExtension> */
	public array $installed = array();

	/** @var array<string, ManifestReading> id => its manifest */
	public array $manifests = array();

	/** @var list<LocalManifest> */
	public array $directory = array();

	/** @var list<AvailableHotfix> */
	public array $hotfixes = array();

	/** @var list<LatestVersion> */
	public array $latest = array();

	/** @var list<string> */
	public array $notices = array();

	/** @var list<string> what was run, written and rebuilt, in order */
	public array $log = array();

	public function all(): array {
		return array_values($this->installed);
	}

	public function enabledVersions(): array {
		$enabled = array();
		foreach ($this->installed as $extension)
			if (!$extension->isDisabled())
				$enabled[] = new ExtensionVersion($extension->id(), $extension->version());

		return $enabled;
	}

	public function installedVersion(string $id): ?string {
		return isset($this->installed[$id]) ? $this->installed[$id]->version() : null;
	}

	public function update(ExtensionRecordInterface ...$extensions): void {
		foreach ($extensions as $extension)
			$this->log[] = 'update '.self::record($extension);
	}

	public function clearHooks(string ...$extensionIds): void {
		$this->log[] = 'clear hooks '.implode(',', $extensionIds);
	}

	public function add(ExtensionRecordInterface ...$extensions): void {
		foreach ($extensions as $extension)
			$this->log[] = 'add '.self::record($extension);
	}

	public function addHooks(HookRecordInterface ...$hooks): void {
		foreach ($hooks as $hook)
			$this->log[] = 'hook '.$hook->id().' of '.$hook->extensionId().' at '.$hook->priority().': '.$hook->code();
	}

	public function find(string $id): ?InstalledExtensionInterface {
		return $this->installed[$id] ?? null;
	}

	public function dependent(string $id): ?string {
		foreach ($this->installed as $extension)
			if (in_array($id, $extension->dependencies(), true))
				return $extension->id();

		return null;
	}

	public function removeHooks(string ...$extensionIds): void {
		$this->log[] = 'remove hooks '.implode(',', $extensionIds);
	}

	public function remove(string ...$ids): void {
		$this->log[] = 'remove '.implode(',', $ids);
	}

	public function isDisabled(string $id): ?bool {
		return isset($this->installed[$id]) ? $this->installed[$id]->isDisabled() : null;
	}

	public function enabledDependent(string $id): ?string {
		foreach ($this->installed as $extension)
			if (!$extension->isDisabled() && in_array($id, $extension->dependencies(), true))
				return $extension->id();

		return null;
	}

	public function dependencies(string $id): array {
		return isset($this->installed[$id]) ? $this->installed[$id]->dependencies() : array();
	}

	public function enabledIds(): array {
		return array_map(static fn (ExtensionVersion $version): string => $version->id(), $this->enabledVersions());
	}

	public function setDisabled(ExtensionStatusInterface ...$statuses): void {
		foreach ($statuses as $status)
			$this->log[] = ($status->isDisabled() ? 'disable ' : 'enable ').$status->id();
	}

	public function local(string $id): ManifestReading {
		return $this->manifests[$id] ?? new ManifestReading(null, array('Missing manifest.xml.'));
	}

	public function hotfix(string $id): ManifestReading {
		return $this->manifests['remote '.$id] ?? new ManifestReading(null, array('Download failed.'));
	}

	public function directory(): array {
		return $this->directory;
	}

	public function supports(ManifestInterface $manifest): bool {
		return $manifest->maxTestedOn() !== '0.1';
	}

	public function outgrows(ManifestInterface $manifest): bool {
		return $manifest->maxTestedOn() === '1.4';
	}

	public function install(string $id, ManifestInterface $manifest, ?string $installedVersion): array {
		$this->log[] = 'install code of '.$id.' over '.var_export($installedVersion, true).': '.$manifest->installCode();

		return $this->notices;
	}

	public function uninstall(string $id, string $code): array {
		$this->log[] = 'uninstall code of '.$id.': '.$code;

		return $this->notices;
	}

	public function clear(): void {
		$this->log[] = 'clear cache';
	}

	public function rebuildHooks(): void {
		$this->log[] = 'rebuild hooks';
	}

	public function hotfixes(): array {
		return $this->hotfixes;
	}

	public function latestVersions(array $installed): array {
		$this->log[] = 'latest versions of '.count($installed);

		return $this->latest;
	}

	private static function record(ExtensionRecordInterface $extension): string {
		return $extension->id().' '.$extension->title().' '.$extension->version().' uninstall "'.$extension->uninstallCode().'" note "'.$extension->uninstallNote().'" needs '.implode(',', $extension->dependencies());
	}
}

class ExtensionsControllerTest extends TestCase {
	private PageKit $kit;

	private FakeExtensionServices $services;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ExtensionsRequested::class, ExtensionsActionRequested::class, InstallStep::class, UninstallStep::class, FlipStep::class, InstallFormRendering::class, UninstallFormRendering::class,
			NoticesRendering::class, ExtensionListRendering::class, ExtensionActionsAssembling::class, MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class, ConfirmFormRequested::class, ConfirmFormRendering::class));
		$this->kit->language->real = array('common', 'admin_common', 'admin_ext');
		$this->kit->settings->values += array('o_redirect_delay' => '0', 'o_check_for_versions' => '1');
		$this->kit->visitor->administrator = true;
		$this->services = new FakeExtensionServices();
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$confirmations = new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens);
		$controller = new ExtensionsController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $confirmations,
			$this->services, $this->services, $this->services, $this->services, $this->services, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/extensions.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	private static function manifest(string $id, string $maxTestedOn = '1.5', array $dependencies = array()): Manifest {
		return new Manifest($id, 'Probe <'.$id.'>', '1.2', 'Does & probes', 'Anna', $maxTestedOn, $dependencies,
			array(new ManifestNote('install', 'Back up <first>'), new ManifestNote('uninstall', ' Data goes ')),
			'$probe = 1;', 'drop();', array(new ManifestHook(array('in_start', 'vt_start'), 'echo 1;', 5), new ManifestHook(array('ft_end'), 'echo 2;', 7)));
	}

	public function testOnlyAnAdministratorGetsThePage(): void {
		$this->kit->visitor->administrator = false;

		$this->assertStringContainsString('You do not have permission', $this->page());
		$this->assertSame(array('ExtensionsRequested', 'MessageShowing', 'MessageRendering:start', 'MessageRendering:end'), $this->kit->events->dispatched);
	}

	public function testTheListShowsWhatCanBeInstalledWhatFailedAndWhatIsInstalled(): void {
		$this->services->installed['old_one'] = new InstalledExtension('old_one', 'Old & gold', '1.0', '', 'Bob', '', '', array(), false);
		$this->services->installed['off_one'] = new InstalledExtension('off_one', 'Off', '2.0', 'Sleeps', 'Carl', '', '', array(), true);
		$this->services->installed['hotfix_x'] = new InstalledExtension('hotfix_x', 'Fix', '1', '', 'PunBB', '', '', array(), false);
		$this->services->directory = array(
			new LocalManifest('probe', self::manifest('probe')),
			new LocalManifest('old_one', self::manifest('old_one')),
			new LocalManifest('Bad-Dir', null, LocalManifest::ILLEGAL_ID),
			new LocalManifest('broken', null, LocalManifest::INVALID, array('One.', 'Two.')),
			new LocalManifest('empty', null, LocalManifest::MISSING),
		);
		$this->services->latest = array(new LatestVersion('old_one', '1.1', 'http://repo.test'));

		$this->kit->events->observe(ExtensionActionsAssembling::class, function (ExtensionActionsAssembling $event): void {
			$event->append('<!-- actions of '.$event->extension()->id().' -->');
			$event->set('probe', '<span>probe</span>');
		});

		$body = $this->page(array('section' => 'manage'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-extensions-manage', 'extensions', null), array($head->id, $head->section, $head->view));
		$this->assertStringStartsWith("200  [admin-extensions-manage]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Extensions available for install</span></h2>", $body);
		$this->assertStringContainsString("\t<div class=\"main-content main-extensions\">\n\t\t<div class=\"ct-box info-box extension available\">\n\t\t\t<h3 class=\"ct-legend hn\">Probe &lt;probe&gt; <em>1.2</em></h3>\n\t\t\t<ul class=\"data-list\">\n\t\t\t\t<li><span>Created by Anna</span></li>\n\t\t\t\t<li><span>Does &amp; probes</span></li>\n\t\t\t</ul>\n\t\t\t<p class=\"options\"><span class=\"first-item\"><a href=\"http://forum.test/admin/extensions.php?install=probe\">Install extension</a></span></p>\n\t\t</div>\n\t\t<div class=\"ct-box info-box extension available\">", $body);
		$this->assertStringContainsString('<a href="http://forum.test/admin/extensions.php?install=old_one">Upgrade extension</a>', $body);
		$this->assertStringContainsString("<div class=\"ext-error databox db2\">\n\t\t\t\t<h3 class=\"legend\"><span>Loading of extension \"Bad-Dir\" failed.</span></h3>\n\t\t\t\t<p>The ID must contain only lowercase", $body);
		$this->assertStringContainsString("<div class=\"ext-error databox db3\">\n\t\t\t\t<h3 class=\"legend\"><span>Loading of extension \"broken\" failed.</span></h3>\n\t\t\t\t<p>One. Two.</p>", $body);
		$this->assertStringContainsString("<h3 class=\"legend\"><span>Loading of extension \"empty\" failed.<span></h3>\n\t\t\t\t<p>Missing manifest.xml.</p>", $body);
		$this->assertStringContainsString("\t<div class=\"main-content main-extensions\">\n<!-- actions of old_one --><!-- actions of off_one -->\t<div class=\"ct-box info-box extension enabled\">\n\t\t<h3 class=\"ct-legend hn\">Old &amp; gold <em>1.0</em></h3>", $body);
		$this->assertStringContainsString('<p class="options"><span class="first-item"><a href="http://forum.test/admin/extensions.php?section=manage&amp;flip=old_one&amp;csrf_token=token-for-'.md5('flipold_one3').'">Disable</a></span> <span><a href="http://forum.test/admin/extensions.php?section=manage&amp;uninstall=old_one">Uninstall</a></span> <span><a href="http://repo.test/old_one/old_one.zip">Download latest version</a></span> <span>probe</span></p>', $body);
		$this->assertStringContainsString("<h3 class=\"ct-legend hn\">Off <em>2.0</em> (Extension disabled)</h3>\n\t\t<ul class=\"data-list\">\n\t\t\t<li><span>Created by Carl</span></li>\n\t\t\t<li><span>Sleeps</span></li>\n\t\t\t</ul>", $body);
		$this->assertStringNotContainsString('Fix', $body, 'a hotfix is not among the extensions');
		$this->assertSame(array('latest versions of 3'), $this->services->log);
	}

	public function testObserversChangeWhatIsAvailableAndHowManyAreShown(): void {
		$this->services->directory = array(new LocalManifest('probe', self::manifest('probe')));

		$this->kit->events->observe(ExtensionListRendering::class, function (ExtensionListRendering $event): void {
			if ($event->position() === ExtensionListRendering::PRE_DISPLAY_AVAILABLE)
			{
				$event->remove(ExtensionListRendering::AVAILABLE, '0');
				$event->count(0, 0);
			}

			$event->append('<!-- '.$event->list().' '.$event->position().' -->');
		});

		$body = $this->page();

		$this->assertStringContainsString("<div class=\"main-content main-extensions\">\n<!-- manage pre_display_available -->\t\t<div class=\"ct-box info-box\">\n\t\t\t<p>There are no extensions available for install or upgrade.</p>", $body);
		$this->assertStringContainsString("</div>\n<!-- manage pre_display_installed -->\t<div class=\"main-subhead\">", $body);
		$this->assertStringEndsWith("<p>There are no installed extensions.</p>\n\t\t</div>\n\t</div>\n<!-- manage end -->", $body);
	}

	public function testTheHotfixListOffersWhatIsNotInstalled(): void {
		$this->services->installed['hotfix_x'] = new InstalledExtension('hotfix_x', 'Fix <x>', '1', 'Mends', 'PunBB', '', '', array(), true);
		$this->services->hotfixes = array(new AvailableHotfix('hotfix_x', 'Fix'), new AvailableHotfix('hotfix_y&z', 'Fix <y>'));

		$body = $this->page(array('section' => 'hotfixes'));

		$this->assertSame('admin-extensions-hotfixes', $this->kit->chromes->opened[0]->id);
		$this->assertStringContainsString("<div class=\"main-content main-hotfixes\">\n\t\t<div class=\"ct-box info-box hotfix available\">\n\t\t\t<h3 class=\"ct-legend hn\">Fix &lt;y&gt;</h3>\n\t\t\t<ul>\n\t\t\t\t<li><span>Created by PunBB</span></li>", $body);
		$this->assertStringContainsString('<a href="http://forum.test/admin/extensions.php?install_hotfix=hotfix_y%26z">Install hotfix</a>', $body);
		$this->assertSame(1, substr_count($body, 'hotfix available'));
		$this->assertStringContainsString("\t\t<div class=\"ct-box info-box hotfix disabled\">\n\t\t\t<h3 class=\"ct-legend hn\"><span>Fix &lt;x&gt; ( <span>Extension disabled</span> )</span></h3>\n\t\t\t<ul class=\"data-list\">\n\t\t\t\t<li><span>Hotfix</span></li>\n\t\t\t\t<li><span>Created by PunBB</span></li>\n\t\t\t\t\t\t\t\t\t<li><span>Mends</span></li>\n\t\t\t\t\t\t\t</ul>", $body);
		$this->assertStringContainsString('?section=hotfixes&amp;flip=hotfix_x&amp;csrf_token=', $body);
		$this->assertStringContainsString('?section=hotfixese&amp;uninstall=hotfix_x">Uninstall</a>', $body);
		$this->assertSame(array(), $this->services->log, 'the hotfix list checks no extension versions');
	}

	public function testAnInstallIsCheckedAgainstItsManifestTheForumAndItsDependencies(): void {
		$this->assertStringContainsString('Bad request', $this->page(array('install' => '../probe')));
		$this->assertStringContainsString('Download and install of a hotfix extension failed.', $this->page(array('install_hotfix' => 'hotfix_1')));

		$this->services->manifests['old'] = new ManifestReading(self::manifest('old', '0.1'));
		$this->assertStringContainsString('This extension is not compatible with your PunBB version.', $this->page(array('install' => 'old')));

		$this->services->installed['base'] = new InstalledExtension('base', 'Base', '1.0', '', 'Bob', '', '', array(), false);
		$this->services->manifests['probe'] = new ManifestReading(self::manifest('probe', '1.4', array(new ManifestDependency('base', '2.0'), new ManifestDependency('gone<'))));

		$this->kit->events->observe(InstallFormRendering::class, function (InstallFormRendering $event): void {
			if ($event->position() === InstallFormRendering::PRE_ERRORS)
				$event->set('probe', '<li>probe</li>');
		});

		$body = $this->page(array('install' => 'probe'), array('install_comply' => '1'));

		$head = $this->kit->chromes->opened[count($this->kit->chromes->opened) - 1];
		$this->assertSame(array('admin-extensions-manage', 'install'), array($head->id, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Extensions', 'Manage extensions', 'Install extension'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertStringStartsWith("200  [admin-extensions-manage]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Install extension \"Probe &lt;probe&gt;\"</span></h2>", $body);
		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>Extension \"base\" must be version 2.0 or higher</span></li>\n\t\t\t\t<li class=\"warn\"><span>This extension cannot be installed unless \"gone&lt;\" is installed and enabled</span></li>\n\t\t\t\t<li>probe</li>\n\t\t\t</ul>\n\t\t</div>\n\t\n\t\t<form class=\"frm-form\" method=\"post\" accept-charset=\"utf-8\" action=\"http://forum.test/admin/extensions.php?install=probe\">", $body);
		$this->assertStringContainsString('<input type="hidden" name="csrf_token" value="token-for-'.md5('http://forum.test/admin/extensions.php?install=probe').'" />', $body);
		$this->assertStringContainsString("\t\t\t\t<ol class=\"info-list\">\n<li>Back up &lt;first&gt;</li>\n\t\t\t\t\t<li>This extension has not been explicitly tested", $body);
		$this->assertSame(array(), $this->services->log, 'nothing is installed while a dependency is missing');
	}

	public function testAConfirmedInstallRunsTheCodeStoresTheExtensionAndItsHooks(): void {
		$this->services->manifests['probe'] = new ManifestReading(self::manifest('probe'));

		$steps = array();
		$this->kit->events->observe(InstallStep::class, function (InstallStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->id();
		});

		$this->assertStringStartsWith('302 /admin_extensions_manage?a=1&b=2 [redirect]', $this->page(array('install' => 'probe'), array('install_comply' => '1')));
		$this->assertSame(array(
			'install code of probe over NULL: $probe = 1;',
			'add probe Probe <probe> 1.2 uninstall "drop();" note "Data goes" needs ',
			'hook in_start of probe at 5: echo 1;',
			'hook vt_start of probe at 5: echo 1;',
			'hook ft_end of probe at 7: echo 2;',
			'clear cache',
			'rebuild hooks',
		), $this->services->log);
		$this->assertSame(array('selected ', 'submitted probe', 'installed probe'), $steps);
		$this->assertSame(array('Extension installed.'), $this->kit->flash->info);
	}

	public function testAnUpgradeReplacesTheHooksAndNoticesAreShown(): void {
		$this->services->installed['probe'] = new InstalledExtension('probe', 'Probe', '1.0', '', 'Anna', '', '', array(), false);
		$this->services->manifests['probe'] = new ManifestReading(self::manifest('probe'));
		$this->services->notices = array('Run <b>this</b>');

		$body = $this->page(array('install' => 'probe'), array('install_comply' => '1'));

		$this->assertSame(array('install code of probe over \'1.0\': $probe = 1;', 'update probe Probe <probe> 1.2 uninstall "drop();" note "Data goes" needs ', 'clear hooks probe'), array_slice($this->services->log, 0, 3));
		$this->assertSame(array('admin-extensions-manage', 'install-notices'), array($this->kit->chromes->opened[0]->id, $this->kit->chromes->opened[0]->view));
		$this->assertStringContainsString("<p>The extension was successfully installed, but reported the following notices.</p>\n\t\t\t<ul class=\"data-list\">\n\t\t\t\t<li><span>Run <b>this</b></span></li>\n\t\t\t</ul>\n\t\t\t<p><a href=\"/admin_extensions_manage?a=1&amp;b=2\">Manage extensions</a></p>", $body);
	}

	public function testAnUninstallIsConfirmedAndLeavesNoExtensionWithoutWhatItNeeds(): void {
		$this->services->installed['base'] = new InstalledExtension('base', 'Base <b>', '1.0', 'The base', 'Bob', 'drop();', 'All goes', array(), false);
		$this->services->installed['top'] = new InstalledExtension('top', 'Top', '1.0', '', 'Bob', '', '', array('base'), false);

		$this->assertStringContainsString('This extension cannot be uninstall while "top" is installed.', $this->page(array('uninstall' => 'base')));
		$this->assertStringContainsString('Bad request', $this->page(array('uninstall' => array('top'))));

		unset($this->services->installed['top']);
		$body = $this->page(array('section' => 'manage', 'uninstall' => 'base'));

		$head = $this->kit->chromes->opened[count($this->kit->chromes->opened) - 1];
		$this->assertSame(array('admin-extensions-manage', 'uninstall'), array($head->id, $head->view));
		$this->assertStringContainsString('action="http://forum.test/admin/extensions.php?section=manage&amp;uninstall=base">', $body);
		$this->assertStringContainsString("\t\t\t</div>\n\t\t\t<div class=\"ct-box warn-box\">\n\t\t\t\t<p class=\"important\"><strong>Please read before uninstalling</strong></p>\n\t\t\t\t<p>All goes</p>\n\t\t\t</div>\n\t\t\t<div class=\"ct-box warn-box\">\n\t\t\t\t<p class=\"warn\"><strong>WARNING!</strong>", $body);
		$this->assertStringContainsString("\t\t\t</div>\n\t\t\t\t<div class=\"frm-buttons\">", $body);

		$this->assertStringStartsWith('302 /admin_extensions_manage?a=1&b=2 [redirect]', $this->page(array('uninstall' => 'base'), array('uninstall_comply' => '1')));
		$this->assertSame(array('uninstall code of base: drop();', 'remove hooks base', 'remove base', 'clear cache', 'rebuild hooks'), $this->services->log);
	}

	public function testASwitchNeedsItsTokenAndLeavesNoExtensionWithoutWhatItNeeds(): void {
		$this->services->installed['base'] = new InstalledExtension('base', 'Base', '1.0', '', 'Bob', '', '', array(), false);
		$this->services->installed['top'] = new InstalledExtension('top', 'Top', '1.0', '', 'Bob', '', '', array('base'), false);
		$this->services->installed['off'] = new InstalledExtension('off', 'Off', '1.0', '', 'Bob', '', '', array('gone'), true);

		$this->assertStringContainsString('name="prev_url"', $this->page(array('flip' => 'base', 'csrf_token' => 'x')));
		$this->assertStringContainsString('This extension cannot be disabled while "top" is enabled.', $this->page(array('flip' => 'base', 'csrf_token' => 'token-for-'.md5('flipbase3'))));
		$this->assertStringContainsString('This extension cannot be enabled while "gone" is disabled.', $this->page(array('flip' => 'off', 'csrf_token' => 'token-for-'.md5('flipoff3'))));
		$this->assertStringContainsString('Bad request', $this->page(array('flip' => 'nope', 'csrf_token' => 'token-for-'.md5('flipnope3'))));
		$this->assertSame(array(), $this->services->log);

		$this->assertStringStartsWith('302 /admin_extensions_hotfixes?a=1&b=2 [redirect]', $this->page(array('section' => 'hotfixes', 'flip' => 'top', 'csrf_token' => 'token-for-'.md5('fliptop3'))));
		$this->assertSame(array('disable top', 'rebuild hooks'), $this->services->log);
		$this->assertSame(array('Hotfix disabled.'), $this->kit->flash->info);
	}
}
