<?php

declare(strict_types=1);

namespace PunBB\Module\Update;

use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Config\ConfigurationInterface;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Controller\Stages;
use PunBB\Module\Update\Controller\Update;
use PunBB\Module\Update\Controller\UpdateController;
use PunBB\Module\Update\Interceptor\BoardDataInterceptor;
use PunBB\Module\Update\Interceptor\BoardSettingsInterceptor;
use PunBB\Module\Update\Interceptor\ConversionInterceptor;
use PunBB\Module\Update\Model\BoardData;
use PunBB\Module\Update\Model\BoardSettings;
use PunBB\Module\Update\Model\Conversion;
use PunBB\Module\Update\Parsing\PreparserInterface;

/**
 * Updating a board's database to this release, a stage per request. The
 * preparser is declared here and wired by the bootstrap's side.
 *
 * It has no permission check: remove this module's directory once the update
 * has run, and admin/db_update.php is a page not found.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Update';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Setup');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(BoardSettingsInterface::class, BoardSettingsInterceptor::class, fn (Container $c): object => new BoardSettings($c->get(Connection::class)));
		$wiring->contract(BoardDataInterface::class, BoardDataInterceptor::class, fn (Container $c): object => new BoardData($c->get(Connection::class)));
		$wiring->contract(ConversionInterface::class, ConversionInterceptor::class, fn (Container $c): object => new Conversion($c->get(Connection::class)));

		$wiring->route(array('admin/db_update.php'), UpdateController::class, fn (Container $c): object => new UpdateController(
			$c->get(EnvironmentInterface::class),
			$c->get(ConfigurationInterface::class),
			$c->get(DatabaseInterface::class),
			$c->get(SetupPage::class),
			// The board's connection is opened from config.php, so it is reached once the controller opened it
			static fn (): Update => new Update(
				$c->get(BoardSettingsInterface::class),
				$c->get(BoardDataInterface::class),
				$c->get(SchemaInterface::class),
				$c->get(DatabaseInterface::class),
				$c->get(EnvironmentInterface::class),
				$c->get(BoardFilesInterface::class),
				$c->get(SetupPage::class),
				$c->get(TemplateRenderer::class),
				new Stages(
					$c->get(BoardSettingsInterface::class),
					$c->get(BoardDataInterface::class),
					$c->get(ConversionInterface::class),
					$c->get(SchemaInterface::class),
					$c->get(DatabaseInterface::class),
					$c->get(EnvironmentInterface::class),
					$c->get(BoardFilesInterface::class),
					$c->get(PreparserInterface::class)
				)
			)
		), setup: true);
	}
}
