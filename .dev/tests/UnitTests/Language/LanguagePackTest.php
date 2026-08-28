<?php
/**
 * Language-pack completeness.
 *
 * `lang/English` is the reference pack: every file it must ship, every array it
 * must define. A pack bundled with the forum has to match it key for key, so a
 * translated string that is dropped or renamed fails the build. Packs a site
 * installs itself are advisory — their state is not this repository's to gate.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LanguagePackTest extends TestCase {
	/** Reference pack; also the only one the repository ships. */
	private const REFERENCE = 'English';

	/** Packs gated by this test. Anything else found in `lang/` is advisory. */
	private const BUNDLED = array('English');

	/** The array each language file defines, keyed by file name. */
	private const ELEMENTS = array(
		'admin_bans.php'       => 'lang_admin_bans',
		'admin_categories.php' => 'lang_admin_categories',
		'admin_censoring.php'  => 'lang_admin_censoring',
		'admin_common.php'     => 'lang_admin_common',
		'admin_ext.php'        => 'lang_admin_ext',
		'admin_forums.php'     => 'lang_admin_forums',
		'admin_groups.php'     => 'lang_admin_groups',
		'admin_index.php'      => 'lang_admin_index',
		'admin_prune.php'      => 'lang_admin_prune',
		'admin_ranks.php'      => 'lang_admin_ranks',
		'admin_reindex.php'    => 'lang_admin_reindex',
		'admin_reports.php'    => 'lang_admin_reports',
		'admin_settings.php'   => 'lang_admin_settings',
		'admin_users.php'      => 'lang_admin_users',
		'common.php'           => 'lang_common',
		'delete.php'           => 'lang_delete',
		'forum.php'            => 'lang_forum',
		'help.php'             => 'lang_help',
		'index.php'            => 'lang_index',
		'install.php'          => 'lang_install',
		'login.php'            => 'lang_login',
		'misc.php'             => 'lang_misc',
		'post.php'             => 'lang_post',
		'profile.php'          => 'lang_profile',
		'search.php'           => 'lang_search',
		'topic.php'            => 'lang_topic',
		'url_replace.php'      => 'lang_url_replace',
		'userlist.php'         => 'lang_ul',
	);

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function elementProvider(): array {
		$cases = array();
		foreach (self::ELEMENTS as $file => $name)
			$cases[$file] = array($file, $name);

		return $cases;
	}

	#[DataProvider('elementProvider')]
	public function testReferencePackDefinesEveryElement(string $file, string $name): void {
		$path = FORUM_ROOT.'lang/'.self::REFERENCE.'/'.$file;
		$this->assertFileExists($path);

		$keys = self::keys($path, $name);
		$this->assertNotNull($keys, $file.' does not define $'.$name);
		$this->assertNotSame(array(), $keys, $file.' defines an empty $'.$name);

		foreach ($keys as $key)
			$this->assertNotSame('', $key, $file.' has an empty key in $'.$name);
	}

	/** A language file added to the reference pack has to be registered here. */
	public function testEveryReferenceFileIsListed(): void {
		$files = glob(FORUM_ROOT.'lang/'.self::REFERENCE.'/*.php');
		$this->assertNotFalse($files);

		$listed = array_keys(self::ELEMENTS);
		$found = array_map('basename', $files);
		sort($listed);
		sort($found);

		$this->assertSame($listed, $found);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function bundledPackProvider(): array {
		$cases = array();
		foreach (self::BUNDLED as $pack) {
			if ($pack !== self::REFERENCE)
				$cases[$pack] = array($pack);
		}

		// A provider may not be empty; the reference compares against itself.
		if ($cases === array())
			$cases[self::REFERENCE] = array(self::REFERENCE);

		return $cases;
	}

	#[DataProvider('bundledPackProvider')]
	public function testBundledPackMatchesTheReference(string $pack): void {
		// The fallback dataset would compare the reference against itself, which
		// is empty by construction and would read as a pass it never earned.
		if ($pack === self::REFERENCE)
			$this->markTestSkipped(self::REFERENCE.' is the reference; no other pack is bundled');

		$this->assertSame(array(), self::diff($pack), 'language pack '.$pack.' differs from '.self::REFERENCE);
	}

	/** Packs the site installed itself: reported, never gating. */
	public function testThirdPartyPacksAreAdvisory(): void {
		$packs = array_values(array_diff(self::installed(), self::BUNDLED));
		if ($packs === array())
			$this->markTestSkipped('no third-party language packs installed');

		$report = array();
		foreach ($packs as $pack) {
			foreach (self::diff($pack) as $file => $problem)
				$report[] = $pack.'/'.$file.': '.$problem;
		}

		if ($report !== array())
			$this->markTestSkipped("third-party language packs are incomplete:\n".implode("\n", $report));

		$this->assertSame(array(), $report);
	}

	/**
	 * Language packs present in the checkout, in directory order.
	 *
	 * A directory counts as a pack when it holds `common.php` — the same test
	 * `get_language_packs()` applies (`include/functions.php:1240`).
	 *
	 * @return list<string>
	 */
	private static function installed(): array {
		$packs = array();

		foreach ((array) glob(FORUM_ROOT.'lang/*', GLOB_ONLYDIR) as $dir) {
			if (is_string($dir) && file_exists($dir.'/common.php'))
				$packs[] = basename($dir);
		}

		return $packs;
	}

	/**
	 * Every file of $pack that does not hold exactly the reference keys.
	 *
	 * @return array<string, string> file name => what is wrong with it
	 */
	private static function diff(string $pack): array {
		$problems = array();

		foreach (self::ELEMENTS as $file => $name) {
			$path = FORUM_ROOT.'lang/'.$pack.'/'.$file;
			if (!file_exists($path)) {
				$problems[$file] = 'missing file';
				continue;
			}

			$keys = self::keys($path, $name);
			if ($keys === null) {
				$problems[$file] = 'does not define $'.$name;
				continue;
			}

			$reference = self::keys(FORUM_ROOT.'lang/'.self::REFERENCE.'/'.$file, $name) ?? array();
			$missing = array_diff($reference, $keys);
			$extra = array_diff($keys, $reference);

			if ($missing !== array() || $extra !== array()) {
				$problems[$file] = trim(
					($missing !== array() ? 'missing keys ['.implode(', ', $missing).'] ' : '').
					($extra !== array() ? 'unneeded keys ['.implode(', ', $extra).']' : '')
				);
			}
		}

		return $problems;
	}

	/**
	 * Keys of the array a language file defines, or null if it defines none.
	 *
	 * Loaded in an isolated scope with a plain `include`, so a pack read twice
	 * in one run — the reference is — reports the same keys both times.
	 *
	 * @return list<string>|null
	 */
	private static function keys(string $path, string $name): ?array {
		$load = static function (string $file, string $variable): ?array {
			include $file;

			$value = $$variable ?? null;

			return is_array($value) ? $value : null;
		};

		$data = $load($path, $name);

		return $data === null ? null : array_map('strval', array_keys($data));
	}
}
