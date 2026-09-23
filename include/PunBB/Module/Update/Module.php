<?php

declare(strict_types=1);

namespace PunBB\Module\Update;

use Closure;
use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchOwnerInterface;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Version\ModuleUpgrade;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Config\ConfigurationInterface;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Controller\Update;
use PunBB\Module\Update\Controller\UpdateController;
use PunBB\Module\Update\Interceptor\BoardDataInterceptor;
use PunBB\Module\Update\Interceptor\BoardSettingsInterceptor;
use PunBB\Module\Update\Interceptor\ConversionInterceptor;
use PunBB\Module\Update\Model\BoardData;
use PunBB\Module\Update\Model\BoardSettings;
use PunBB\Module\Update\Model\Conversion;
use PunBB\Module\Update\Parsing\PreparserInterface;
use PunBB\Module\Update\Patch\AddOptions;
use PunBB\Module\Update\Patch\ConvertMisc;
use PunBB\Module\Update\Patch\ConvertRows;
use PunBB\Module\Update\Patch\ConvertTables;
use PunBB\Module\Update\Patch\LimitGroupMail;
use PunBB\Module\Update\Patch\ModeratorGroups;
use PunBB\Module\Update\Patch\MoveUnverifiedUsers;
use PunBB\Module\Update\Patch\Preparse;
use PunBB\Module\Update\Patch\RecordAvatars;
use PunBB\Module\Update\Patch\RecordFirstPosts;
use PunBB\Module\Update\Patch\SchemeLinkedinAddresses;

/**
 * Updating a board's database to this release, a stage per request: the
 * tables of each module behind its version, then each data patch of a module
 * behind, a batch per request. The patches here carry an older board's data
 * to this release's shape. The preparser is declared here and wired by the
 * bootstrap's side.
 *
 * It has no permission check: remove this module's directory once the update
 * has run, and admin/db_update.php is a page not found.
 */
final class Module implements ModuleInterface, PatchOwnerInterface {
	public function name(): string {
		return 'Update';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Setup');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(BoardSettingsInterface::class, BoardSettingsInterceptor::class, fn (Container $c): object => new BoardSettings($c->get(Connection::class)));
		$wiring->contract(BoardDataInterface::class, BoardDataInterceptor::class, fn (Container $c): object => new BoardData($c->get(Connection::class)));
		$wiring->contract(ConversionInterface::class, ConversionInterceptor::class, fn (Container $c): object => new Conversion($c->get(Connection::class)));

		$wiring->route(array('admin/db_update.php'), UpdateController::class, fn (Container $c): object => new UpdateController(
			$c->get(EnvironmentInterface::class),
			$c->get(ConfigurationInterface::class),
			$c->get(DatabaseInterface::class),
			$c->get(SetupPage::class),
			// The board's connection is opened from config.php, so it is reached once the controller opened it
			static fn (): Update => new Update(
				$c->get(BoardSettingsInterface::class),
				$c->get(BoardDataInterface::class),
				$c->get(SchemaInterface::class),
				$c->get(DatabaseInterface::class),
				$c->get(EnvironmentInterface::class),
				$c->get(BoardFilesInterface::class),
				$c->get(SetupPage::class),
				$c->get(TemplateRenderer::class),
				$c->get(ModuleUpgrade::class)
			)
		), setup: true);
	}

	/** The data a board from 1.2 on still holds in a shape this release does not read. */
	public function patches(): array {
		$conversions = array('Update::convert_misc', 'Update::convert_reports', 'Update::convert_search_words', 'Update::convert_users', 'Update::convert_topics', 'Update::convert_posts');

		return array(
			new PatchDeclaration('Update::avatars', array(), static fn (Container $c): RecordAvatars => new RecordAvatars($c->get(BoardSettingsInterface::class), $c->get(BoardDataInterface::class), $c->get(BoardFilesInterface::class))),
			new PatchDeclaration('Update::options', array(), static fn (Container $c): AddOptions => new AddOptions($c->get(BoardSettingsInterface::class), $c->get(EnvironmentInterface::class))),
			new PatchDeclaration('Update::moderator_groups', array(), static fn (Container $c): ModeratorGroups => new ModeratorGroups($c->get(BoardSettingsInterface::class), $c->get(BoardDataInterface::class))),
			new PatchDeclaration('Update::group_mail', array('Update::moderator_groups'), static fn (Container $c): LimitGroupMail => new LimitGroupMail($c->get(BoardDataInterface::class))),
			new PatchDeclaration('Update::first_posts', array(), static fn (Container $c): RecordFirstPosts => new RecordFirstPosts($c->get(BoardSettingsInterface::class), $c->get(BoardDataInterface::class))),
			new PatchDeclaration('Update::unverified_users', array(), static fn (Container $c): MoveUnverifiedUsers => new MoveUnverifiedUsers($c->get(BoardDataInterface::class))),
			new PatchDeclaration('Update::linkedin_addresses', array(), static fn (Container $c): SchemeLinkedinAddresses => new SchemeLinkedinAddresses($c->get(BoardSettingsInterface::class), $c->get(BoardDataInterface::class))),
			// The text a 1.2 board stored, converted once every option and group is in place
			new PatchDeclaration('Update::convert_misc', array('Update::options', 'Update::moderator_groups'), static fn (Container $c): ConvertMisc => new ConvertMisc($c->get(BoardSettingsInterface::class), $c->get(ConversionInterface::class), $c->get(DatabaseInterface::class))),
			new PatchDeclaration('Update::convert_reports', array('Update::convert_misc'), self::rows('reports', array('message'), array(), 'report')),
			new PatchDeclaration('Update::convert_search_words', array('Update::convert_misc'), self::rows('search_words', array('word'), array(), 'search word')),
			new PatchDeclaration('Update::convert_users', array('Update::convert_misc'), self::rows('users', array('username', 'title', 'realname', 'location', 'signature', 'admin_note'), array('title', 'realname', 'location', 'signature', 'admin_note'), 'user', 2)),
			new PatchDeclaration('Update::convert_topics', array('Update::convert_misc'), self::rows('topics', array('poster', 'subject', 'last_poster'), array(), 'topic')),
			new PatchDeclaration('Update::convert_posts', array('Update::convert_misc'), self::rows('posts', array('poster', 'message', 'edited_by'), array('edited_by'), 'post')),
			// Read as UTF-8 only once every row is converted, and preparsed only once it is UTF-8
			new PatchDeclaration('Update::convert_tables', $conversions, static fn (Container $c): ConvertTables => new ConvertTables($c->get(BoardSettingsInterface::class), $c->get(ConversionInterface::class), $c->get(SchemaInterface::class), $c->get(Connection::class))),
			new PatchDeclaration('Update::preparse_posts', array('Update::convert_tables'), self::preparse('posts', 'message', false, 'Preparsing post')),
			new PatchDeclaration('Update::preparse_signatures', array('Update::convert_tables'), self::preparse('users', 'signature', true, 'Preparsing signature', 1)),
		);
	}

	/**
	 * @param list<string> $columns
	 * @param list<string> $nullable
	 * @return Closure(Container): ConvertRows
	 */
	private static function rows(string $table, array $columns, array $nullable, string $label, ?int $from = null): Closure {
		return static fn (Container $c): ConvertRows => new ConvertRows($c->get(BoardSettingsInterface::class), $c->get(ConversionInterface::class), $c->get(DatabaseInterface::class), $table, $columns, $nullable, $label, $from);
	}

	/** @return Closure(Container): Preparse */
	private static function preparse(string $table, string $column, bool $signature, string $label, ?int $from = null): Closure {
		return static fn (Container $c): Preparse => new Preparse($c->get(BoardSettingsInterface::class), $c->get(ConversionInterface::class), $c->get(DatabaseInterface::class), $c->get(PreparserInterface::class), $table, $column, $signature, $label, $from);
	}
}
