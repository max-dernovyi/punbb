<?php
/**
 * What the installer and the updater share: config.php as each writes it, the
 * schema installed and its translation into the arrays the DBLayer builders
 * take, and the forum's error page a setup route answers with.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\LegacyBridge\Database\LegacySchema;
use PunBB\Module\Setup\Config\BoardConfiguration;
use PunBB\Module\Setup\Config\ConfigFile;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Setup\Schema\BoardSchema;
use PunBB\Module\Setup\Schema\SchemaException;

class SetupFilesTest extends TestCase {
	public function testAnInstalledConfigPhpOffersEveryOptionCommentedOut(): void {
		$configuration = new BoardConfiguration(new DatabaseSettings('mysqli', 'db:3306', 'forum', 'o\'brien', 'p\\a"ss', 'pun_'), 'http://forum.test', 'forum_cookie_abc');

		$this->assertSame("<?php\n\n\$db_type = 'mysqli';\n\$db_host = 'db:3306';\n\$db_name = 'forum';\n\$db_username = 'o\\'brien';\n\$db_password = 'p\\\\a\"ss';\n\$db_prefix = 'pun_';\n\$p_connect = false;\n\n".
			"\$base_url = 'http://forum.test';\n\n\$cookie_name = 'forum_cookie_abc';\n\$cookie_domain = '';\n\$cookie_path = '/';\n\$cookie_secure = 0;\n\ndefine('FORUM', 1);".
			"\n\n// Enable DEBUG mode by removing // from the following line\n//define('FORUM_DEBUG', 1);".
			"\n\n// Enable show DB Queries mode by removing // from the following line\n//define('FORUM_SHOW_QUERIES', 1);".
			"\n\n// Enable forum IDNA support by removing // from the following line\n//define('FORUM_ENABLE_IDNA', 1);".
			"\n\n// Disable forum CSRF checking by removing // from the following line\n//define('FORUM_DISABLE_CSRF_CONFIRM', 1);".
			"\n\n// Disable forum hooks (extensions) by removing // from the following line\n//define('FORUM_DISABLE_HOOKS', 1);".
			"\n\n// Disable forum output buffering by removing // from the following line\n//define('FORUM_DISABLE_BUFFERING', 1);".
			"\n\n// Disable forum extensions version check by removing // from the following line\n//define('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK', 1);",
			ConfigFile::installed($configuration));

		// What it writes is what it reads back
		$file = tempnam(sys_get_temp_dir(), 'config');
		file_put_contents($file, ConfigFile::installed($configuration));
		file_put_contents($file, str_replace("define('FORUM', 1);", '', (string) file_get_contents($file)));
		$read = (static function (string $__file): array { require $__file; return get_defined_vars(); })($file);
		unlink($file);
		$this->assertSame(array('o\'brien', 'p\\a"ss'), array($read['db_username'], $read['db_password']));
	}

	public function testAnUpdatedConfigPhpKeepsWhatTheOldOneSaid(): void {
		$configuration = new BoardConfiguration(new DatabaseSettings('pgsql', 'db', 'forum', 'u', 'p', '', true), 'https://forum.test', 'cookie', '.forum.test', '/forum/', true);

		$this->assertSame("<?php\n\n\$db_type = 'pgsql';\n\$db_host = 'db';\n\$db_name = 'forum';\n\$db_username = 'u';\n\$db_password = 'p';\n\$db_prefix = '';\n\$p_connect = true;\n\n".
			"\$base_url = 'https://forum.test';\n\n\$cookie_name = 'cookie';\n\$cookie_domain = '.forum.test';\n\$cookie_path = '/forum/';\n\$cookie_secure = 1;\n\ndefine('FORUM', 1);",
			ConfigFile::updated($configuration));
		$this->assertTrue(ConfigFile::isSecureAddress('HTTPS://forum.test'));
		$this->assertFalse(ConfigFile::isSecureAddress('http://forum.test/https://'));
	}

	public function testMysqlIndexesAPrefixAndSqliteKeysTheSearchWordsById(): void {
		$online = BoardSchema::table('online', 'mysqli_innodb');
		$this->assertSame(array('user_id_ident_idx' => array('user_id', 'ident(40)')), $online->uniqueKeys);
		$this->assertSame('HEAP', $online->engine);
		$this->assertSame(array('username(8)'), BoardSchema::table('users', 'mysqli')->indexes['username_idx']);
		$this->assertSame(array('ident'), BoardSchema::table('search_cache', 'pgsql')->indexes['ident_idx']);

		$words = BoardSchema::table('search_words', 'sqlite3');
		$this->assertSame(array(array('id'), array('word_idx' => array('word'))), array($words->primaryKey, $words->uniqueKeys));
		$this->assertSame(array(array('word'), array()), array(BoardSchema::table('search_words', 'pgsql')->primaryKey, BoardSchema::table('search_words', 'pgsql')->uniqueKeys));

		$this->expectException(SchemaException::class);
		BoardSchema::table('polls', 'mysqli');
	}

	public function testATableIsTheArrayTheDblayerBuildersTake(): void {
		$table = new Table('t', array(
			new Column('id', 'SERIAL'),
			new Column('name', 'VARCHAR(20)', false, 'it\'s', 'bin'),
			new Column('count', 'INT(10)', false, 0),
			new Column('note', 'TEXT', true),
		), array('id'), array('name_idx' => array('name')), array('count_idx' => array('count')), 'InnoDB');

		$this->assertSame(array(
			'FIELDS'		=> array(
				'id'	=> array('datatype' => 'SERIAL', 'allow_null' => false),
				'name'	=> array('datatype' => 'VARCHAR(20)', 'allow_null' => false, 'default' => '\'it\'\'s\'', 'collation' => 'bin'),
				'count'	=> array('datatype' => 'INT(10)', 'allow_null' => false, 'default' => '0'),
				'note'	=> array('datatype' => 'TEXT', 'allow_null' => true),
			),
			'PRIMARY KEY'	=> array('id'),
			'UNIQUE KEYS'	=> array('name_idx' => array('name')),
			'INDEXES'		=> array('count_idx' => array('count')),
			'ENGINE'		=> 'InnoDB',
		), LegacySchema::definition($table));

		$this->assertSame(array('FIELDS' => array('a' => array('datatype' => 'INT', 'allow_null' => false))), LegacySchema::definition(new Table('u', array(new Column('a', 'INT')))));
	}

	public function testASetupRouteAnswersWithTheForumsErrorPage(): void {
		$response = (new SetupPage(new TemplateRenderer()))->error(new Html('A <em>message</em>'), 'Board & co');

		$this->assertSame(array(503, array('Content-Type' => 'text/html; charset=utf-8')), array($response->status, $response->headers));
		$this->assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\" dir=\"ltr\">\n<head>\n\t<meta charset=\"utf-8\" />\n\t<title>Error - Board &amp; co</title>", $response->body);
		$this->assertStringEndsWith("<body>\n\t<h1>Sorry! The page could not be loaded.</h1>\n<p>A <em>message</em></p>\n</body>\n</html>\n", $response->body);
	}
}
