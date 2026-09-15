<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reindex;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Reindex\Indexing\SearchIndexInterface;

/**
 * The search index of include/search_idx.php: emptied with the statements
 * admin/reindex.php built, their points run by name, and filled by
 * update_search_index() with the extension code attached to it.
 */
final class LegacySearchIndex implements SearchIndexInterface {
	public function __construct(private readonly PageScope $scope) {}

	public function clear(): void {
		$query = array(
			'DELETE'	=> 'search_matches'
		);

		$this->scope->run('ari_cycle_qr_empty_search_matches', array('query' => &$query));
		PluggedQuery::run($query);

		$query = array(
			'DELETE'	=> 'search_words'
		);

		$this->scope->run('ari_cycle_qr_empty_search_words', array('query' => &$query));
		PluggedQuery::run($query);

		$db = LegacyConnection::legacy();
		$prefix = Markers::markup($db->prefix);

		// SQLite starts the ids over by itself
		$reset = match ($GLOBALS['db_type'] ?? null) {
			'mysqli', 'mysqli_innodb'	=> 'ALTER TABLE '.$prefix.'search_words auto_increment=1',
			'pgsql'						=> 'SELECT setval(\''.$prefix.'search_words_id_seq\', 1, false)',
			default						=> null,
		};

		if ($reset !== null && $db->query($reset) === false)
			\error(__FILE__, __LINE__);
	}

	public function index(int $postId, string $message, ?string $subject): void {
		if (!defined('FORUM_SEARCH_IDX_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/search_idx.php');

		if ($subject !== null)
			\update_search_index('post', $postId, $message, $subject);
		else
			\update_search_index('post', $postId, $message);
	}
}
