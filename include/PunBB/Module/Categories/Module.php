<?php

declare(strict_types=1);

namespace PunBB\Module\Categories;

use PunBB\Module\Categories\Api\CategoriesInterface;
use PunBB\Module\Categories\Controller\CategoriesController;
use PunBB\Module\Categories\Interceptor\CategoriesInterceptor;
use PunBB\Module\Categories\Model\Categories;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Removal\ForumContentsInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The categories: adding, editing and deleting them. Emptying a deleted
 * category's forums is ForumContentsInterface and the jump list is
 * QuickjumpCacheInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Categories';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(CategoriesInterface::class, CategoriesInterceptor::class, fn (Container $c): object => new Categories($c->get(Connection::class)));

		$wiring->route(array('admin/categories.php'), CategoriesController::class, fn (Container $c): object => new CategoriesController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(CategoriesInterface::class),
			$c->get(ForumContentsInterface::class),
			$c->get(QuickjumpCacheInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));
	}
}
