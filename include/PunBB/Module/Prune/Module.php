<?php

declare(strict_types=1);

namespace PunBB\Module\Prune;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Prune\Api\PrunableTopicsInterface;
use PunBB\Module\Prune\Controller\PruneController;
use PunBB\Module\Prune\Interceptor\PrunableTopicsInterceptor;
use PunBB\Module\Prune\Model\PrunableTopics;
use PunBB\Module\Prune\Pruning\TopicPruningInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Pruning old topics: the form choosing what to prune, the confirmation naming
 * how many topics go, and the pruning. Taking the topics off the board is
 * TopicPruningInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Prune';
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
		$wiring->contract(PrunableTopicsInterface::class, PrunableTopicsInterceptor::class, fn (Container $c): object => new PrunableTopics($c->get(Connection::class)));

		$wiring->route(array('admin/prune.php'), PruneController::class, fn (Container $c): object => new PruneController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(PrunableTopicsInterface::class),
			$c->get(TopicPruningInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));
	}
}
