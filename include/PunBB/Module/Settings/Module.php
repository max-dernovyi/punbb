<?php

declare(strict_types=1);

namespace PunBB\Module\Settings;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Settings\Api\ConfigurationInterface;
use PunBB\Module\Settings\Controller\SettingsController;
use PunBB\Module\Settings\Interceptor\ConfigurationInterceptor;
use PunBB\Module\Settings\Model\Configuration;
use PunBB\Module\Site\Cache\ConfigCacheInterface;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\PacksInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The board's settings, section by section. The caches a change rebuilds and
 * the packs a setting chooses from are ConfigCacheInterface,
 * QuickjumpCacheInterface and PacksInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Settings';
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
		$wiring->contract(ConfigurationInterface::class, ConfigurationInterceptor::class, fn (Container $c): object => new Configuration($c->get(Connection::class)));

		$wiring->route(array('admin/settings.php'), SettingsController::class, fn (Container $c): object => new SettingsController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfigurationInterface::class),
			$c->get(ConfigCacheInterface::class),
			$c->get(QuickjumpCacheInterface::class),
			$c->get(PacksInterface::class),
			$c->get(EmailAddressesInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));
	}

	public function tables(Platform $platform): array {
		return array(
			new Table('config', array(
				new Column('conf_name', 'VARCHAR(255)', false, ''),
				new Column('conf_value', 'TEXT', true),
			), array('conf_name')),
		);
	}
}
