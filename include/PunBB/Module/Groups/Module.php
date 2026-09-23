<?php

declare(strict_types=1);

namespace PunBB\Module\Groups;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Groups\Api\GroupsInterface;
use PunBB\Module\Groups\Controller\GroupsController;
use PunBB\Module\Groups\Interceptor\GroupsInterceptor;
use PunBB\Module\Groups\Model\Groups;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\ConfigCacheInterface;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The user groups: adding, editing and removing them, and the group new users
 * join. The jump list, the settings and the moderator lists they change are
 * QuickjumpCacheInterface, ConfigCacheInterface and ModeratorListsInterface,
 * which the bootstrap's side wires.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Groups';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(GroupsInterface::class, GroupsInterceptor::class, fn (Container $c): object => new Groups($c->get(Connection::class)));

		$wiring->route(array('admin/groups.php'), GroupsController::class, fn (Container $c): object => new GroupsController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfirmPage::class),
			$c->get(GroupsInterface::class),
			$c->get(QuickjumpCacheInterface::class),
			$c->get(ConfigCacheInterface::class),
			$c->get(ModeratorListsInterface::class),
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
			new Table('groups', array(
				new Column('g_id', 'SERIAL'),
				new Column('g_title', 'VARCHAR(50)', false, ''),
				new Column('g_user_title', 'VARCHAR(50)', true),
				new Column('g_moderator', 'TINYINT(1)', false, 0),
				new Column('g_mod_edit_users', 'TINYINT(1)', false, 0),
				new Column('g_mod_rename_users', 'TINYINT(1)', false, 0),
				new Column('g_mod_change_passwords', 'TINYINT(1)', false, 0),
				new Column('g_mod_ban_users', 'TINYINT(1)', false, 0),
				new Column('g_read_board', 'TINYINT(1)', false, 1),
				new Column('g_view_users', 'TINYINT(1)', false, 1),
				new Column('g_post_replies', 'TINYINT(1)', false, 1),
				new Column('g_post_topics', 'TINYINT(1)', false, 1),
				new Column('g_edit_posts', 'TINYINT(1)', false, 1),
				new Column('g_delete_posts', 'TINYINT(1)', false, 1),
				new Column('g_delete_topics', 'TINYINT(1)', false, 1),
				new Column('g_set_title', 'TINYINT(1)', false, 1),
				new Column('g_search', 'TINYINT(1)', false, 1),
				new Column('g_search_users', 'TINYINT(1)', false, 1),
				new Column('g_send_email', 'TINYINT(1)', false, 1),
				new Column('g_post_flood', 'SMALLINT(6)', false, 30),
				new Column('g_search_flood', 'SMALLINT(6)', false, 30),
				new Column('g_email_flood', 'SMALLINT(6)', false, 60),
			), array('g_id'), removedColumns: array('g_edit_subjects_interval', 'g_post_polls', 'g_posts_approved')),
		);
	}
}
