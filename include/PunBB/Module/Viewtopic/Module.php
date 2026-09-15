<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic;

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
use PunBB\Module\Viewtopic\Api\TopicPostsInterface;
use PunBB\Module\Viewtopic\Controller\TopicController;
use PunBB\Module\Viewtopic\Interceptor\TopicPostsInterceptor;
use PunBB\Module\Viewtopic\Model\TopicPosts;

/**
 * A topic's page: its posts, a page at a time, and the quick reply form.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Viewtopic';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(TopicPostsInterface::class, TopicPostsInterceptor::class, fn (Container $c): object => new TopicPosts($c->get(Connection::class)));

		$wiring->route(array('viewtopic.php'), TopicController::class, fn (Container $c): object => new TopicController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(TopicPostsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class)
		));
	}
}
