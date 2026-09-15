<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Update\Parsing\PreparserInterface;

/**
 * preparse_bbcode() of include/parser.php, which reads the board's settings
 * from $forum_config: the updater has no config cache, so they are read from
 * the database it opened.
 */
final class LegacyUpdatePreparser implements PreparserInterface {
	public function preparse(string $text, bool $signature): string {
		if (!isset($GLOBALS['forum_config']))
		{
			$config = array();
			foreach (LegacyConnection::open()->select('SELECT c.conf_name, c.conf_value FROM '.LegacyConnection::open()->table('config').' AS c') as $row)
				$config[$row->string('conf_name')] = $row->nullableString('conf_value');

			$GLOBALS['forum_config'] = $config;
		}

		if (!defined('FORUM_PARSER_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/parser.php');

		$errors = array();

		return Markers::markup(\preparse_bbcode($text, $errors, $signature));
	}
}
