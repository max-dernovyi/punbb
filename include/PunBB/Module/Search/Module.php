<?php

declare(strict_types=1);

namespace PunBB\Module\Search;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Search\Api\ResultsInterface;
use PunBB\Module\Search\Api\SearchesInterface;
use PunBB\Module\Search\Controller\SearchController;
use PunBB\Module\Search\Interceptor\ResultsInterceptor;
use PunBB\Module\Search\Interceptor\SearchesInterceptor;
use PunBB\Module\Search\Model\Results;
use PunBB\Module\Search\Model\Searches;
use PunBB\Module\Search\Words\SearchWordsInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The search page: the form, keyword and author searches stored for their
 * results pages, and the quick searches.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Search';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(SearchesInterface::class, SearchesInterceptor::class, fn (Container $c): object => new Searches($c->get(Connection::class)));
		$wiring->contract(ResultsInterface::class, ResultsInterceptor::class, fn (Container $c): object => new Results($c->get(Connection::class)));

		$wiring->route(array('search.php'), SearchController::class, fn (Container $c): object => new SearchController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(SearchesInterface::class),
			$c->get(ResultsInterface::class),
			$c->get(SearchWordsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class)
		));
	}
}
