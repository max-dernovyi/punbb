<?php

declare(strict_types=1);

namespace PunBB\Module\Users;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;
use PunBB\Module\Users\Api\UsersInterface;
use PunBB\Module\Users\Controller\UsersController;
use PunBB\Module\Users\Interceptor\UsersInterceptor;
use PunBB\Module\Users\Model\Users;
use PunBB\Module\Site\Removal\UserRemovalInterface;

/**
 * The user administration: searching the users by their details and by the
 * addresses they posted from, and deleting, banning or moving into another
 * group those selected. Taking a user off the board is UserRemovalInterface,
 * which the bootstrap's side wires, with the ban cache and the moderator lists
 * of Site.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Users';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(UsersInterface::class, UsersInterceptor::class, fn (Container $c): object => new Users($c->get(Connection::class)));

		$wiring->route(array('admin/users.php'), UsersController::class, fn (Container $c): object => new UsersController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(UsersInterface::class),
			$c->get(UserRemovalInterface::class),
			$c->get(BanCacheInterface::class),
			$c->get(ModeratorListsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));
	}
}
