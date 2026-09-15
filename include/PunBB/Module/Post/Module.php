<?php

declare(strict_types=1);

namespace PunBB\Module\Post;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Post\Api\PostingInterface;
use PunBB\Module\Post\Controller\PostController;
use PunBB\Module\Post\Creation\PostCreationInterface;
use PunBB\Module\Post\Interceptor\PostingInterceptor;
use PunBB\Module\Post\Model\Posting;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Posting a reply or a new topic. Storing the post is PostCreationInterface,
 * which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Post';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(PostingInterface::class, PostingInterceptor::class, fn (Container $c): object => new Posting($c->get(Connection::class)));

		$wiring->route(array('post.php'), PostController::class, fn (Container $c): object => new PostController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(PostingInterface::class),
			$c->get(PostCreationInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(PostRulesInterface::class),
			$c->get(UsernameRulesInterface::class),
			$c->get(EmailAddressesInterface::class)
		), checksOwnToken: true);
	}
}
