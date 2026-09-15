<?php

declare(strict_types=1);

namespace PunBB\Module\Install;

use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Install\Api\BoardInstallationInterface;
use PunBB\Module\Install\Controller\InstallController;
use PunBB\Module\Install\Controller\Installation;
use PunBB\Module\Install\Indexing\PostIndexInterface;
use PunBB\Module\Install\Interceptor\BoardInstallationInterceptor;
use PunBB\Module\Install\Language\InstallerLanguageInterface;
use PunBB\Module\Install\Manifest\BundledExtensionsInterface;
use PunBB\Module\Install\Model\BoardInstallation;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;

/**
 * Installing a board: the form, the schema and the rows it starts with, and
 * its config.php. The language packs, the search index and the extension the
 * forum ships are declared here and wired by the bootstrap's side.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Install';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Setup');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(BoardInstallationInterface::class, BoardInstallationInterceptor::class, fn (Container $c): object => new BoardInstallation($c->get(Connection::class)));

		$wiring->route(array('admin/install.php'), InstallController::class, fn (Container $c): object => new InstallController(
			$c->get(EnvironmentInterface::class),
			$c->get(BoardFilesInterface::class),
			$c->get(DatabaseInterface::class),
			$c->get(InstallerLanguageInterface::class),
			$c->get(BundledExtensionsInterface::class),
			$c->get(EmailAddressesInterface::class),
			$c->get(RandomKeysInterface::class),
			$c->get(SetupPage::class),
			$c->get(TemplateRenderer::class),
			// The board's connection is the one the form names, so it is reached once the controller opened it
			static fn (): Installation => new Installation(
				$c->get(BoardInstallationInterface::class),
				$c->get(SchemaInterface::class),
				$c->get(DatabaseInterface::class),
				$c->get(EnvironmentInterface::class),
				$c->get(PasswordsInterface::class),
				$c->get(RandomKeysInterface::class),
				$c->get(PostIndexInterface::class),
				$c->get(BundledExtensionsInterface::class)
			)
		), setup: true);
	}
}
