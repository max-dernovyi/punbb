<?php

declare(strict_types=1);

namespace PunBB\Module\Reports;

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
use PunBB\Module\Reports\Api\ReportsInterface;
use PunBB\Module\Reports\Controller\ReportsController;
use PunBB\Module\Reports\Interceptor\ReportsInterceptor;
use PunBB\Module\Reports\Model\Reports;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The administration's reports: the posts members reported, and marking them read.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Reports';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '1.4.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(ReportsInterface::class, ReportsInterceptor::class, fn (Container $c): object => new Reports($c->get(Connection::class)));

		$wiring->route(array('admin/reports.php'), ReportsController::class, fn (Container $c): object => new ReportsController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ReportsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));
	}

	public function tables(Platform $platform): array {
		return array(
			new Table('reports', array(
				new Column('id', 'SERIAL'),
				new Column('post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('topic_id', 'INT(10) UNSIGNED', false, 0),
				new Column('forum_id', 'INT(10) UNSIGNED', false, 0),
				new Column('reported_by', 'INT(10) UNSIGNED', false, 0),
				new Column('created', 'INT(10) UNSIGNED', false, 0),
				new Column('message', 'TEXT', true),
				new Column('zapped', 'INT(10) UNSIGNED', true),
				new Column('zapped_by', 'INT(10) UNSIGNED', true),
			), array('id'), array(), array(
				'zapped_idx'	=> array('zapped'),
			)),
		);
	}
}
