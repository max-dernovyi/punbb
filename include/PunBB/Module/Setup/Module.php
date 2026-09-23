<?php

declare(strict_types=1);

namespace PunBB\Module\Setup;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Page\SetupPage;

/**
 * What the installer and the updater share: they run before a usable
 * configuration exists. The PHP installation, the board's files, its database
 * and config.php are declared here and wired by the bootstrap's side.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Setup';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(SetupPage::class, fn (Container $c): object => new SetupPage($c->get(TemplateRenderer::class)));
	}
}
