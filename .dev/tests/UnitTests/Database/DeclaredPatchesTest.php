<?php
/**
 * The data patches the modules declare, put in the order they apply: each
 * after its dependencies, otherwise as declared; named for their module, once.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\DeclaredPatches;
use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchException;
use PunBB\Module\Database\Patch\PatchOwnerInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;
use PunBB\Module\Framework\Modules\Wiring;

class DeclaredPatchesTest extends TestCase {
	/** @param array<string, list<string>> $patches name => its dependencies, in declared order */
	public static function owner(string $name, array $patches): ModuleInterface {
		return new class($name, $patches) implements ModuleInterface, PatchOwnerInterface {
			/** @param array<string, list<string>> $patches */
			public function __construct(private string $name, private array $patches) {}

			public function name(): string { return $this->name; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function version(): string { return '1.0.0'; }

			public function wire(Wiring $wiring): void {}

			public function patches(): array {
				$declarations = array();
				foreach ($this->patches as $patch => $dependencies)
					$declarations[] = new PatchDeclaration($patch, $dependencies, static fn (Container $c): DataPatchInterface => new class() implements DataPatchInterface {
						public function apply(int $startAt): PatchStep { return new PatchStep(); }
					});

				return $declarations;
			}
		};
	}

	/** @return list<string> */
	private static function names(DeclaredPatches $patches): array {
		return array_map(static fn (PatchDeclaration $patch): string => $patch->name, $patches->ordered());
	}

	public function testPatchesApplyAsDeclaredInModuleOrder(): void {
		$patches = new DeclaredPatches(self::owner('Forums', array('Forums::counts' => array(), 'Forums::moderators' => array())), self::owner('Users', array('Users::titles' => array())));

		$this->assertSame(array('Forums::counts', 'Forums::moderators', 'Users::titles'), self::names($patches));
	}

	public function testAPatchAppliesAfterItsDependenciesWhereverTheyAreDeclared(): void {
		$patches = new DeclaredPatches(
			self::owner('Forums', array('Forums::counts' => array('Users::titles'), 'Forums::moderators' => array())),
			self::owner('Users', array('Users::titles' => array('Forums::moderators')))
		);

		$this->assertSame(array('Forums::moderators', 'Users::titles', 'Forums::counts'), self::names($patches));
	}

	public function testAPatchIsNamedForItsModule(): void {
		$this->expectException(PatchException::class);
		$this->expectExceptionMessage('Module Forums declares data patch "Users::counts"; a patch is named Forums::<lowercase_name>');

		(new DeclaredPatches(self::owner('Forums', array('Users::counts' => array()))))->ordered();
	}

	/** data_patches records a name in VARCHAR(150). */
	public function testAPatchNameIsAtMost150CharactersOnOneLine(): void {
		$fits = 'Forums::'.str_repeat('a', 142);
		$this->assertSame(array($fits), self::names(new DeclaredPatches(self::owner('Forums', array($fits => array())))));

		foreach (array($fits.'a', "Forums::counts\n") as $name)
		{
			try {
				(new DeclaredPatches(self::owner('Forums', array($name => array()))))->ordered();
				$this->fail('accepted data patch '.$name);
			}
			catch (PatchException $e) {
				$this->assertStringContainsString('a patch is named Forums::<lowercase_name>, at most 150 characters', $e->getMessage());
			}
		}
	}

	public function testAPatchIsDeclaredOnce(): void {
		$this->expectException(PatchException::class);
		$this->expectExceptionMessage('Data patch Forums::counts is declared twice');

		(new DeclaredPatches(self::owner('Forums', array('Forums::counts' => array())), self::owner('Forums', array('Forums::counts' => array()))))->ordered();
	}

	public function testADependencyNoModuleDeclaresIsAnError(): void {
		$this->expectException(PatchException::class);
		$this->expectExceptionMessage('Data patch Forums::counts depends on Polls::votes, which no module declares');

		(new DeclaredPatches(self::owner('Forums', array('Forums::counts' => array('Polls::votes')))))->ordered();
	}

	public function testPatchesDependingOnEachOtherAreAnError(): void {
		$this->expectException(PatchException::class);
		$this->expectExceptionMessage('Data patches depend on each other in a cycle: Forums::counts, Forums::moderators');

		(new DeclaredPatches(self::owner('Forums', array('Forums::counts' => array('Forums::moderators'), 'Forums::moderators' => array('Forums::counts')))))->ordered();
	}

	/** The forum's patches: the update's conversion of an older board, a step each. */
	public function testTheForumAppliesTheUpdatesConversionInOrder(): void {
		$patches = ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->container()->get(DeclaredPatches::class);

		$this->assertSame(array(
			'Update::avatars',
			'Update::options',
			'Update::moderator_groups',
			'Update::group_mail',
			'Update::first_posts',
			'Update::unverified_users',
			'Update::linkedin_addresses',
			'Update::convert_misc',
			'Update::convert_reports',
			'Update::convert_search_words',
			'Update::convert_users',
			'Update::convert_topics',
			'Update::convert_posts',
			'Update::convert_tables',
			'Update::preparse_posts',
			'Update::preparse_signatures',
		), self::names($patches));
	}
}
