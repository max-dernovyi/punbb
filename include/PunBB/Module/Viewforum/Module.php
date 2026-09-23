<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum;

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
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;
use PunBB\Module\Viewforum\Api\ForumTopicsInterface;
use PunBB\Module\Viewforum\Controller\ForumController;
use PunBB\Module\Viewforum\Interceptor\ForumTopicsInterceptor;
use PunBB\Module\Viewforum\Model\ForumTopics;

/**
 * A forum's page: the topics it lists, a page at a time.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Viewforum';
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
		$wiring->contract(ForumTopicsInterface::class, ForumTopicsInterceptor::class, fn (Container $c): object => new ForumTopics($c->get(Connection::class)));

		$wiring->route(array('viewforum.php'), ForumController::class, fn (Container $c): object => new ForumController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(ForumTopicsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class)
		));
	}
}
