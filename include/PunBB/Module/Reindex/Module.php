<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Reindex\Api\IndexablePostsInterface;
use PunBB\Module\Reindex\Controller\ReindexController;
use PunBB\Module\Reindex\Indexing\SearchIndexInterface;
use PunBB\Module\Reindex\Interceptor\IndexablePostsInterceptor;
use PunBB\Module\Reindex\Model\IndexablePosts;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Rebuilding the search index: the form starting a rebuild, and the cycles
 * indexing the posts a batch at a time. The index itself is
 * SearchIndexInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Reindex';
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
		$wiring->contract(IndexablePostsInterface::class, IndexablePostsInterceptor::class, fn (Container $c): object => new IndexablePosts($c->get(Connection::class)));

		$wiring->route(array('admin/reindex.php'), ReindexController::class, fn (Container $c): object => new ReindexController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(ConfirmPage::class),
			$c->get(IndexablePostsInterface::class),
			$c->get(SearchIndexInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class)
		));
	}
}
