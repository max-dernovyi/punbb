<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;

/**
 * A DBLayer connected as include/dblayer/common_db.php connects one, left in
 * $forum_db and $db_type, where LegacyConnection and the legacy helpers the
 * setup routes call find it. A connection the server refuses is the forum's
 * error page, as the driver renders it.
 */
final class LegacySetupDatabase implements DatabaseInterface {
	public function open(DatabaseSettings $settings): void {
		$this->connect($settings);
	}

	public function openUnencoded(DatabaseSettings $settings): void {
		// The driver binds no character set of its own: setNames() binds the one the text is in
		if (!defined('FORUM_NO_SET_NAMES'))
			define('FORUM_NO_SET_NAMES', 1);

		$this->connect($settings);
	}

	public function serverVersion(): string {
		$version = LegacyConnection::legacy()->get_version();

		return Markers::markup(is_array($version) ? ($version['version'] ?? '') : '');
	}

	public function supportsInnodb(): bool {
		$db = LegacyConnection::legacy();

		$row = $db->fetch_assoc($db->query('SHOW VARIABLES LIKE \'have_innodb\''));
		if (is_array($row) && strtolower(Markers::markup($row['Value'] ?? '')) === 'yes')
			return true;

		// Newer servers report the engines instead
		$engines = $db->query('SHOW ENGINES');
		while (is_array($engine = $db->fetch_assoc($engines)))
			if (($engine['Engine'] ?? null) === 'InnoDB')
				return true;

		return false;
	}

	public function setNames(string $charset): void {
		LegacyConnection::legacy()->set_names($charset);
	}

	public function startTransaction(): void {
		LegacyConnection::legacy()->start_transaction();
	}

	public function endTransaction(): void {
		LegacyConnection::legacy()->end_transaction();
	}

	public function close(): void {
		$db = $GLOBALS['forum_db'] ?? null;
		if ($db instanceof \DBLayer)
			$db->close();
	}

	private function connect(DatabaseSettings $settings): void {
		$GLOBALS['db_type'] = $settings->type;
		$GLOBALS['db_host'] = $settings->host;
		$GLOBALS['db_name'] = $settings->name;
		$GLOBALS['db_username'] = $settings->username;
		$GLOBALS['db_password'] = $settings->password;
		$GLOBALS['db_prefix'] = $settings->prefix;
		$GLOBALS['p_connect'] = $settings->persistent;

		LegacyScope::requireGlobally(LegacyChromeSource::root().'include/dblayer/common_db.php');
	}
}
