<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Extensions\Api\ExtensionsInterface;
use PunBB\Module\Extensions\Cache\ExtensionCacheInterface;
use PunBB\Module\Extensions\Controller\ExtensionsController;
use PunBB\Module\Extensions\Installation\ExtensionCodeInterface;
use PunBB\Module\Extensions\Interceptor\ExtensionsInterceptor;
use PunBB\Module\Extensions\Manifest\ManifestsInterface;
use PunBB\Module\Extensions\Model\Extensions;
use PunBB\Module\Extensions\Updates\UpdatesInterface;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The administration of extensions and hotfixes. The manifests, the code an
 * extension installs itself with, the caches it shapes and the update services
 * are served by whoever holds them.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Extensions';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '1.4.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(ExtensionsInterface::class, ExtensionsInterceptor::class, fn (Container $c): object => new Extensions($c->get(Connection::class)));

		$wiring->route(array('admin/extensions.php'), ExtensionsController::class, fn (Container $c): object => new ExtensionsController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfirmPage::class),
			$c->get(ExtensionsInterface::class),
			$c->get(ManifestsInterface::class),
			$c->get(ExtensionCodeInterface::class),
			$c->get(ExtensionCacheInterface::class),
			$c->get(UpdatesInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));
	}

	public function tables(Platform $platform): array {
		return array(
			new Table('extensions', array(
				new Column('id', 'VARCHAR(150)', false, ''),
				new Column('title', 'VARCHAR(255)', false, ''),
				new Column('version', 'VARCHAR(25)', false, ''),
				new Column('description', 'TEXT', true),
				new Column('author', 'VARCHAR(50)', false, ''),
				new Column('uninstall', 'TEXT', true),
				new Column('uninstall_note', 'TEXT', true),
				new Column('disabled', 'TINYINT(1)', false, 0),
				new Column('dependencies', 'VARCHAR(255)', false, ''),
			), array('id'), removedColumns: array('uninstall_notes')),

			new Table('extension_hooks', array(
				new Column('id', 'VARCHAR(150)', false, ''),
				new Column('extension_id', 'VARCHAR(50)', false, ''),
				new Column('code', 'TEXT', true),
				new Column('installed', 'INT(10) UNSIGNED', false, 0),
				new Column('priority', 'TINYINT(1) UNSIGNED', false, 5),
			), array('id', 'extension_id')),
		);
	}
}
