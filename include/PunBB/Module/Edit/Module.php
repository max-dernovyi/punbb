<?php

declare(strict_types=1);

namespace PunBB\Module\Edit;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Edit\Api\EditablePostsInterface;
use PunBB\Module\Edit\Controller\EditController;
use PunBB\Module\Edit\Indexing\EditIndexInterface;
use PunBB\Module\Edit\Interceptor\EditablePostsInterceptor;
use PunBB\Module\Edit\Model\EditablePosts;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Editing a post, and the subject of the topic it opens. The search index it
 * updates is EditIndexInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Edit';
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
		$wiring->contract(EditablePostsInterface::class, EditablePostsInterceptor::class, fn (Container $c): object => new EditablePosts($c->get(Connection::class)));

		$wiring->route(array('edit.php'), EditController::class, fn (Container $c): object => new EditController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(EditablePostsInterface::class),
			$c->get(EditIndexInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(PostRulesInterface::class)
		));
	}
}
