<?php
/**
 * A generated cache file does nothing when it is requested directly.
 *
 * `cache/` holds PHP the forum generates and include()s, and cache_config.php
 * carries the whole `config` table — the SMTP credentials included. The only
 * thing keeping it off the web was cache/.htaccess, which is not read by nginx
 * and not read by Apache with AllowOverride off. The files themselves now
 * refuse to run outside the forum, the way the quickjump cache always has.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CacheFileGuardTest extends TestCase
{
	/** The literal every generator has to emit first. */
	private const GUARD = '\'<?php\'."\n\n".\'if (!defined(\'FORUM\')) exit;\'';

	/**
	 * Every cache file written through write_cache_file() with a literal
	 * header, and the constant that used to be the first thing in it.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function generatorProvider(): array
	{
		return array(
			'config'       => array('cache_config.php', 'FORUM_CONFIG_LOADED'),
			'bans'         => array('cache_bans.php', 'FORUM_BANS_LOADED'),
			'ranks'        => array('cache_ranks.php', 'FORUM_RANKS_LOADED'),
			'stats'        => array('cache_stats.php', 'FORUM_STATS_LOADED'),
			'censors'      => array('cache_censors.php', 'FORUM_CENSORS_LOADED'),
			'hooks'        => array('cache_hooks.php', 'FORUM_HOOKS_LOADED'),
			'updates'      => array('cache_updates.php', 'FORUM_UPDATES_LOADED'),
			'ext versions' => array('cache_ext_version_notifications.php', 'FORUM_EXT_VERSIONS_LOADED'),
		);
	}

	private function source(): string
	{
		return str_replace('\\\'', '\'', (string) file_get_contents(FORUM_ROOT.'include/cache.php'));
	}

	#[DataProvider('generatorProvider')]
	public function testTheGeneratorEmitsTheGuardFirst(string $file, string $constant): void
	{
		$source = $this->source();

		$this->assertStringContainsString(
			'FORUM_CACHE_DIR.\''.$file.'\', '.self::GUARD,
			$source,
			$file.': the generated file no longer refuses to run outside the forum'
		);

		// The control: the pre-fix header opened straight onto the define.
		$this->assertStringNotContainsString(
			'FORUM_CACHE_DIR.\''.$file.'\', \'<?php\'."\n\n".\'define(\''.$constant,
			$source,
			$file.': the guard was dropped back out of the header'
		);
	}

	/** The quickjump cache is written from a variable, in two branches. */
	public function testBothQuickjumpBranchesCarryTheGuard(): void
	{
		$source = $this->source();

		$this->assertSame(2, substr_count($source, '$output = '.self::GUARD),
			'the quickjump cache has a branch that writes a file without the guard');
	}

	/**
	 * No generator may write a cache file without it: the two tests above
	 * cover the nine calls the file makes, so a tenth has to be added to them
	 * rather than slipping past unguarded.
	 */
	public function testEveryCacheFileIsCovered(): void
	{
		$this->assertSame(9, substr_count($this->source(), 'write_cache_file(FORUM_CACHE_DIR.'),
			'include/cache.php writes a cache file this test does not cover');
	}

	/**
	 * And the guard actually works: the header the generators emit ends the
	 * request when the file is reached from outside the forum, and does not
	 * when the forum includes it.
	 */
	public function testTheGuardStopsADirectRequest(): void
	{
		$file = sys_get_temp_dir().'/punbb_cache_'.uniqid().'.php';
		file_put_contents($file, '<?php'."\n\n".'if (!defined(\'FORUM\')) exit;'."\n\n".'echo \'LEAKED\';'."\n");

		try
		{
			$this->assertSame('', $this->includeCacheFile($file, false), 'a direct request runs the cache file body');
			$this->assertSame('LEAKEDREACHED', $this->includeCacheFile($file, true), 'the forum can no longer include the cache file');
		}
		finally
		{
			@unlink($file);
		}
	}

	private function includeCacheFile(string $file, bool $inside_forum): string
	{
		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/cache_guard_harness.php').
			' '.escapeshellarg($file).' '.escapeshellarg($inside_forum ? '1' : '0');

		// shell_exec() reports "no output at all" as null, which is exactly
		// what the guard produces.
		return (string) shell_exec($command);
	}

	/**
	 * The server-side denial has to be written in a form Apache 2.4 accepts:
	 * a bare "Deny from all" needs mod_access_compat, which is not loaded by
	 * default, and an unrecognised directive in .htaccess is a 500 for the
	 * whole directory.
	 *
	 * @return array<string, array{string}>
	 */
	public static function htaccessProvider(): array
	{
		return array(
			'cache'   => array('cache/.htaccess'),
			'avatars' => array('img/avatars/.htaccess'),
		);
	}

	#[DataProvider('htaccessProvider')]
	public function testTheDirectoryDenialIsWrittenForApache24(string $file): void
	{
		$rules = (string) file_get_contents(FORUM_ROOT.$file);

		$this->assertStringContainsString('<IfModule mod_authz_core.c>', $rules, $file.': no 2.4 authorisation');
		$this->assertStringContainsString('Require all denied', $rules, $file.': nothing is denied');

		// Every 2.2 directive has to sit behind the negated guard, or it is a
		// fatal "Invalid command" on a host without mod_access_compat.
		$compat = substr($rules, (int) strpos($rules, '<IfModule !mod_authz_core.c>'));
		foreach (array('Deny from', 'Allow from', 'Order ') as $directive)
			$this->assertSame(substr_count($rules, $directive), substr_count($compat, $directive),
				$file.': "'.$directive.'" is used outside the mod_access_compat fallback');
	}

	/** The avatar directory still serves the three types the upload produces. */
	public function testTheAvatarDirectoryStillServesImages(): void
	{
		$rules = (string) file_get_contents(FORUM_ROOT.'img/avatars/.htaccess');

		$this->assertSame(2, substr_count($rules, '<FilesMatch "\.(gif|jpg|png)$">'));
		$this->assertStringContainsString('Require all granted', $rules);
	}
}
