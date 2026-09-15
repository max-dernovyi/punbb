<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Updates\AvailableHotfix;
use PunBB\Module\Extensions\Updates\LatestVersion;
use PunBB\Module\Extensions\Updates\UpdatesInterface;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * The hotfixes the update check left in $forum_updates, and the newest
 * extension versions the repositories offer, cached in
 * cache/cache_ext_version_notifications.php for an hour. The repositories are
 * gathered through aex_add_extensions_repository and a point per installed
 * extension, aex_add_repository_for_<id>, and aex_before_update_checking runs
 * before the cache is refreshed: admin/extensions.php ran all three, and no
 * event covers them.
 */
final class LegacyUpdates implements UpdatesInterface {
	/** How long the cached versions stand before they are fetched again. */
	private const FRESH_FOR = 3600;

	public function __construct(private readonly PageScope $scope, private readonly ExtensionRows $rows) {}

	public function hotfixes(): array {
		$updates = $GLOBALS['forum_updates'] ?? null;
		if (!is_array($updates) || !isset($updates['hotfix']) || !is_array($updates['hotfix']))
			return array();

		// One hotfix is not a list of them
		$listed = is_array(current($updates['hotfix'])) ? $updates['hotfix'] : array($updates['hotfix']);
		$updates['hotfix'] = $listed;
		$GLOBALS['forum_updates'] = $updates;

		$hotfixes = array();
		foreach ($listed as $hotfix)
			if (is_array($hotfix))
				$hotfixes[] = new AvailableHotfix(Markers::markup(is_array($hotfix['attributes'] ?? null) ? $hotfix['attributes']['id'] ?? '' : ''), Markers::markup($hotfix['content'] ?? ''));

		return $hotfixes;
	}

	public function latestVersions(array $installed): array {
		$inst_exts = array();
		foreach ($installed as $extension)
			$inst_exts[$extension->id()] = $this->rows->extension($extension);

		$repository_urls = array(\FORUM_PUN_EXTENSION_REPOSITORY_URL);
		$this->scope->run('aex_add_extensions_repository', array('repository_urls' => &$repository_urls, 'inst_exts' => &$inst_exts));

		$repository_url_by_extension = array();
		foreach (array_keys($inst_exts) as $id)
		{
			$point = 'aex_add_repository_for_'.$id;
			$this->scope->run($point, array('id' => $id, 'repository_urls' => &$repository_urls, 'repository_url_by_extension' => &$repository_url_by_extension, 'inst_exts' => &$inst_exts));
		}

		$cache = Markers::markup(defined('FORUM_CACHE_DIR') ? constant('FORUM_CACHE_DIR') : LegacyChromeSource::root().'cache/').'cache_ext_version_notifications.php';
		$cached = self::cached($cache);
		$forum_ext_last_versions = $cached['forum_ext_last_versions'] ?? null;
		$updated = $cached['forum_ext_versions_update_cache'] ?? null;

		// No cache, an extension added or removed since, or an hour gone
		$update_new_versions_cache = !defined('FORUM_EXT_VERSIONS_LOADED')
			|| (is_array($forum_ext_last_versions) && array_diff(array_keys($inst_exts), array_keys($forum_ext_last_versions)) !== array())
			|| (is_numeric($updated) && time() - (int) $updated > self::FRESH_FOR);

		$this->scope->run('aex_before_update_checking', array(
			'repository_urls'				=> &$repository_urls,
			'repository_url_by_extension'	=> &$repository_url_by_extension,
			'update_new_versions_cache'		=> &$update_new_versions_cache,
			'forum_ext_last_versions'		=> &$forum_ext_last_versions,
			'inst_exts'						=> &$inst_exts,
		));

		if ($update_new_versions_cache)
		{
			if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
				LegacyScope::requireGlobally(LegacyChromeSource::root().'include/cache.php');

			\generate_ext_versions_cache($inst_exts, $repository_urls, $repository_url_by_extension);

			$forum_ext_last_versions = self::cached($cache)['forum_ext_last_versions'] ?? null;
		}

		$GLOBALS['forum_ext_last_versions'] = $forum_ext_last_versions;

		$latest = array();
		foreach (is_array($forum_ext_last_versions) ? $forum_ext_last_versions : array() as $id => $version)
			if (is_array($version))
				$latest[] = new LatestVersion((string) $id, Markers::markup($version['version'] ?? ''), Markers::markup($version['repo_url'] ?? ''));

		return $latest;
	}

	/**
	 * The variables the cache file defines; none when it is missing.
	 *
	 * @return array<string, mixed>
	 */
	private static function cached(string $file): array {
		if (!is_readable($file))
			return array();

		return (static function (string $__file): array {
			include $__file;

			return get_defined_vars();
		})($file);
	}
}
