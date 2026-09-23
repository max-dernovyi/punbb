<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Ranks\Api\RanksInterface;
use PunBB\Module\Ranks\Cache\RankCacheInterface;
use PunBB\Module\Ranks\Controller\RanksController;
use PunBB\Module\Ranks\Interceptor\RanksInterceptor;
use PunBB\Module\Ranks\Model\Ranks;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The ranks members earn by posting: adding, editing and removing them. The
 * list poster titles are chosen from is RankCacheInterface, which the
 * bootstrap's side wires.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Ranks';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(RanksInterface::class, RanksInterceptor::class, fn (Container $c): object => new Ranks($c->get(Connection::class)));

		$wiring->route(array('admin/ranks.php'), RanksController::class, fn (Container $c): object => new RanksController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(RanksInterface::class),
			$c->get(RankCacheInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));
	}

	public function tables(Platform $platform): array {
		return array(
			new Table('ranks', array(
				new Column('id', 'SERIAL'),
				new Column('rank', 'VARCHAR(50)', false, ''),
				new Column('min_posts', 'MEDIUMINT(8) UNSIGNED', false, 0),
			), array('id')),
		);
	}
}
