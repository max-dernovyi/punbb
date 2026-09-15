<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;
use PunBB\Module\Userlist\Api\MemberDirectoryInterface;
use PunBB\Module\Userlist\Controller\UserListController;
use PunBB\Module\Userlist\Interceptor\MemberDirectoryInterceptor;
use PunBB\Module\Userlist\Model\MemberDirectory;

/**
 * The member list: the registered members, and the directory it reads them from.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Userlist';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(MemberDirectoryInterface::class, MemberDirectoryInterceptor::class, fn (Container $c): object => new MemberDirectory($c->get(Connection::class)));

		$wiring->route(array('userlist.php'), UserListController::class, fn (Container $c): object => new UserListController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(MemberDirectoryInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class)
		));
	}
}
