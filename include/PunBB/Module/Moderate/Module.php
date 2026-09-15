<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Moderate\Api\ModeratedPostsInterface;
use PunBB\Module\Moderate\Api\ModeratedTopicsInterface;
use PunBB\Module\Moderate\Controller\ModerateController;
use PunBB\Module\Moderate\Controller\PostsModeration;
use PunBB\Module\Moderate\Controller\TopicsModeration;
use PunBB\Module\Moderate\Indexing\PostIndexInterface;
use PunBB\Module\Moderate\Interceptor\ModeratedPostsInterceptor;
use PunBB\Module\Moderate\Interceptor\ModeratedTopicsInterceptor;
use PunBB\Module\Moderate\Model\ModeratedPosts;
use PunBB\Module\Moderate\Model\ModeratedTopics;
use PunBB\Module\Moderate\Network\HostnameLookup;
use PunBB\Module\Moderate\Network\HostnameLookupInterface;
use PunBB\Module\Moderate\Sync\BoardSyncInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Moderation: a forum's topics and a topic's posts as its moderators change
 * them, and the name a post's address resolves to. The counts a change syncs
 * and the search index it strips are BoardSyncInterface and
 * PostIndexInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Moderate';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(ModeratedPostsInterface::class, ModeratedPostsInterceptor::class, fn (Container $c): object => new ModeratedPosts($c->get(Connection::class)));
		$wiring->contract(ModeratedTopicsInterface::class, ModeratedTopicsInterceptor::class, fn (Container $c): object => new ModeratedTopics($c->get(Connection::class)));
		$wiring->service(HostnameLookupInterface::class, fn (): object => new HostnameLookup());

		$wiring->service(PostsModeration::class, fn (Container $c): object => new PostsModeration(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ModeratedPostsInterface::class),
			$c->get(BoardSyncInterface::class),
			$c->get(PostIndexInterface::class),
			$c->get(PostRulesInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));

		$wiring->service(TopicsModeration::class, fn (Container $c): object => new TopicsModeration(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfirmPage::class),
			$c->get(ModeratedTopicsInterface::class),
			$c->get(BoardSyncInterface::class),
			$c->get(PostIndexInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));

		$wiring->route(array('moderate.php'), ModerateController::class, fn (Container $c): object => new ModerateController(
			$c->get(EventDispatcher::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ModeratedPostsInterface::class),
			$c->get(ModeratedTopicsInterface::class),
			$c->get(PostsModeration::class),
			$c->get(TopicsModeration::class),
			$c->get(HostnameLookupInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(UrlsInterface::class)
		));
	}
}
