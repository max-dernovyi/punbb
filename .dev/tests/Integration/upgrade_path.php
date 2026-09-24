<?php
/**
 * Upgrade paths from 1.4.4 and from 1.5.1, on every supported driver.
 *
 * Per driver, installs a fresh forum of this release to hold the upgrades
 * against, then for each starting release restores its committed fixture under
 * a table prefix of its own and drives admin/db_update.php over HTTP until it
 * reports completion. It asserts that the version rows advanced, that every
 * piece of non-ASCII content came through untouched, that the schema, the
 * recorded patches and the recorded module versions are the fresh install's,
 * that a second run changes nothing, and that not one PHP diagnostic was
 * emitted along the way, nor, on the form and the completion page, any markup
 * smoke_dead_markup() names.
 * It then walks the upgraded forum over HTTP — pages, the fixture extension's
 * hook, login, posting and search, the extension flows and, on MySQL, the user
 * flows — so the upgraded data is exercised, not only asserted on. It also
 * asserts the removed-driver guard: a config.php naming a driver that no longer
 * exists has to stop the update with the name of the driver to switch to.
 *
 * Run it from inside the web container — like the install matrix it needs the
 * forum both as files (it rewrites config.php) and as a running site.
 *
 *   php .dev/tests/Integration/upgrade_path.php [driver ...]
 *
 * Environment (all optional, defaults match a stock dev stack):
 *   PUNBB_TEST_BASE_URL          site URL the update script is driven on
 *   PUNBB_TEST_MYSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_PGSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_ERROR_LOG         error log file to assert on, when there is one
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// The extension flows bring the user flows and, under them, the install matrix:
// the curl helpers, the connections, the form driving and the config.php stash.
require_once __DIR__.'/extension_flows.php';

// The removed-driver map belongs to the forum, not to this script: the guard it
// asserts on is only worth anything if both read the same table.
if (!function_exists('forum_removed_db_type_replacement'))
	require_once dirname(__DIR__, 3).'/include/functions.php';

define('UPGRADE_PATH_ROOT', dirname(__DIR__, 3).'/');
define('UPGRADE_PATH_SQLITE', '.dev/tmp/matrix/upgrade.sqlite3');
define('UPGRADE_PATH_FRESH_SQLITE', '.dev/tmp/matrix/upgrade-fresh.sqlite3');

// The releases a board is upgraded from: the last 1.4 the fork imported, and
// 1.5.1, which every existing forum of this fork runs.
const UPGRADE_PATH_RELEASES = array('1.4.4', '1.5.1');

// The fixture administrator, and what the functional pass posts as them.
const UPGRADE_PATH_USERNAME = 'fixture-admin';
const UPGRADE_PATH_PASSWORD = 'fixture-password';
const UPGRADE_PATH_KEYWORD = 'quaesitum';
const UPGRADE_PATH_REPLY = 'Ответ после обновления: '.UPGRADE_PATH_KEYWORD.' — [b]жирный[/b]';

// What the fixture's enabled extension writes into the page head. The hook has
// to survive the upgrade and run on 8.4, or the marker never renders.
const UPGRADE_PATH_HOOK_MARKER = '<meta name="fixture-ext" content="1" />';

// Text that has to survive the upgrade byte for byte. Every one of these lives
// in a different table, so a column the update script converts loses one of them.
const UPGRADE_PATH_MARKERS = array(
	'Привет, мир!',            // posts.message
	'Ümlaut-Thema',            // topics.subject
	'Ärger & Umlauts',         // forums.forum_name
	'фикстура-юзер',           // users.username
	'Подпись с [b]тегами[/b]', // users.signature
	'中文, ελληνικά, עברית',   // posts.message, outside Cyrillic and Latin
);


/** The fixture of $release for one backend: mysql, pgsql or sqlite3. */
function upgrade_path_fixture_file($release, $backend)
{
	return __DIR__.'/fixtures/punbb-'.$release.'-'.$backend.'.sql';
}


/**
 * install_matrix_drivers() with the prefixes and SQLite file of the upgraded
 * boards, or with $fresh those of the fresh install they are held against.
 */
function upgrade_path_drivers($fresh = false)
{
	$prefixes = $fresh
		? array('mysqli' => 'uf1_', 'mysqli_innodb' => 'uf2_', 'pgsql' => 'uf3_', 'sqlite3' => '')
		: array('mysqli' => 'up1_', 'mysqli_innodb' => 'up2_', 'pgsql' => 'up3_', 'sqlite3' => '');
	$drivers = array();

	foreach (install_matrix_drivers() as $db_type => $spec)
		$drivers[$db_type] = array_merge($spec, array('prefix' => $prefixes[$db_type]));

	$drivers['sqlite3']['name'] = $fresh ? UPGRADE_PATH_FRESH_SQLITE : UPGRADE_PATH_SQLITE;

	return $drivers;
}


/**
 * The storage this run claims, for the other runs to keep off: each prefix on
 * a shared database, and each driver's backend|name|prefix.
 */
function upgrade_path_claimed()
{
	$claimed = array();

	foreach (array_merge(array_values(upgrade_path_drivers()), array_values(upgrade_path_drivers(true))) as $spec)
	{
		if ($spec['backend'] !== 'sqlite3')
			$claimed[] = $spec['prefix'];

		$claimed[] = $spec['backend'].'|'.$spec['name'].'|'.$spec['prefix'];
	}

	return $claimed;
}


/**
 * The statements of a dump, in order. Splits on the semicolons that are not
 * inside a quoted string — the fixture carries apostrophes and escapes in its
 * post bodies, and a naive explode() would cut them in half.
 */
function upgrade_path_statements($sql)
{
	$statements = array();
	$current = '';
	$quote = '';
	$length = strlen($sql);

	for ($i = 0; $i < $length; ++$i)
	{
		$char = $sql[$i];

		if ($quote !== '')
		{
			$current .= $char;

			if ($char === '\\' && $i + 1 < $length)
			{
				$current .= $sql[++$i];
				continue;
			}

			if ($char === $quote)
				$quote = '';

			continue;
		}

		// A -- comment runs to the end of its line and never carries SQL.
		if ($char === '-' && substr($sql, $i, 2) === '--')
		{
			$end = strpos($sql, "\n", $i);
			$i = ($end === false) ? $length : $end;
			continue;
		}

		if ($char === '\'' || $char === '"' || $char === '`')
			$quote = $char;

		if ($char === ';')
		{
			if (trim($current) !== '')
				$statements[] = trim($current);

			$current = '';
			continue;
		}

		$current .= $char;
	}

	if (trim($current) !== '')
		$statements[] = trim($current);

	return $statements;
}


/**
 * The fixture of $release for $db_type, with its %PREFIX% placeholder resolved.
 * A mysqli_innodb board is the MySQL one with its tables on InnoDB: that engine
 * is all the driver created differently.
 */
function upgrade_path_fixture_sql($release, $db_type, $prefix)
{
	$file = upgrade_path_fixture_file($release, install_matrix_drivers()[$db_type]['backend']);
	$sql = @file_get_contents($file);

	if ($sql === false)
		throw new RuntimeException('cannot read the fixture: '.$file);

	if ($db_type === 'mysqli_innodb')
		$sql = str_replace('ENGINE=MyISAM', 'ENGINE=InnoDB', $sql);

	return str_replace('%PREFIX%', $prefix, $sql);
}


/** conf_value of one config row straight out of the fixture text. */
function upgrade_path_fixture_config($sql, $name)
{
	return preg_match('/\(\'' . preg_quote($name, '/') . '\',\s*\'([^\']*)\'\)/', (string) $sql, $match) ? $match[1] : '';
}


/** The version and revision admin/db_update.php upgrades a database to: the release's, as include/constants.php of $root defines them. */
function upgrade_path_target_versions($root)
{
	$source = (string) @file_get_contents($root.'include/constants.php');
	$version = preg_match('/define\(\'FORUM_VERSION\',\s*\'([^\']+)\'\)/', $source, $match) ? $match[1] : '';
	$revision = preg_match('/define\(\'FORUM_DB_REVISION\',\s*(\d+)\)/', $source, $match) ? $match[1] : '';

	return array('o_cur_version' => $version, 'o_database_revision' => $revision);
}


/** The removed driver a board on $db_type used to run on, or '' when there was none. */
function upgrade_path_removed_driver($db_type)
{
	foreach (array('mysql', 'mysql_innodb', 'sqlite') as $removed)
		if (forum_removed_db_type_replacement($removed) === $db_type)
			return $removed;

	return '';
}


/** The message plan 02's guard renders for a driver that no longer exists. */
function upgrade_path_removed_driver_message($db_type)
{
	$replacement = forum_removed_db_type_replacement($db_type);

	return 'Your config.php uses the \''.$db_type.'\' database driver, which was removed along with '.
		'the PHP extension it needs. Set $db_type to \''.$replacement.'\' in config.php and run this script again.';
}


/** The URL db_update.php sends the browser to next, or '' when it is done. The script escapes it as a string literal. */
function upgrade_path_next_url($body)
{
	if (!preg_match('/window\.location\s*=\s*"(db_update\.php[^"]*)"/', (string) $body, $match))
		return '';

	$url = json_decode('"'.$match[1].'"');

	return is_string($url) ? $url : '';
}


function upgrade_path_completed($body)
{
	return strpos((string) $body, 'Database Update completed!') !== false;
}


/** The update script's start form, and nothing else, offers this button. */
function upgrade_path_offers_update($body)
{
	return strpos((string) $body, 'value="Start update"') !== false;
}


/** What db_update.php answers on a board it has nothing left to do for. */
function upgrade_path_up_to_date($body)
{
	return strpos((string) $body, 'Your database is already as up-to-date as this script can make it.') !== false;
}


/** The fixture of $release, restored from nothing into $spec's database. */
function upgrade_path_restore($release, $db_type, $spec)
{
	extension_flows_drop_schema($spec);

	$statements = upgrade_path_statements(upgrade_path_fixture_sql($release, $db_type, $spec['prefix']));

	switch ($spec['backend'])
	{
		case 'mysql':
			$link = install_matrix_mysql($spec);
			mysqli_set_charset($link, 'utf8mb4');

			try
			{
				foreach ($statements as $statement)
					mysqli_query($link, $statement);
			}
			catch (mysqli_sql_exception $e)
			{
				mysqli_close($link);
				throw new RuntimeException('restoring the fixture failed: '.$e->getMessage());
			}

			mysqli_close($link);
			break;

		case 'pgsql':
			$link = install_matrix_pgsql($spec);

			foreach ($statements as $statement)
			{
				if (@pg_query($link, $statement) === false)
				{
					$error = pg_last_error($link);
					pg_close($link);
					throw new RuntimeException('restoring the fixture failed: '.$error);
				}
			}

			pg_close($link);
			break;

		case 'sqlite3':
			$file = INSTALL_MATRIX_ROOT.$spec['name'];

			try
			{
				$link = new SQLite3($file, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
				$link->enableExceptions(true);

				foreach ($statements as $statement)
					$link->exec($statement);

				$link->close();
			}
			catch (Exception $e)
			{
				throw new RuntimeException('restoring the fixture failed: '.$e->getMessage());
			}

			// The site writes the file this run created.
			@chmod($file, 0666);
			break;
	}
}


/** One scalar out of the upgraded board's database, or null when it cannot be read. */
function upgrade_path_value($spec, $sql)
{
	try
	{
		$rows = extension_flows_rows($spec, $sql);
	}
	catch (UserFlowsFailure $e)
	{
		return null;
	}

	return $rows === array() ? null : reset($rows[0]);
}


function upgrade_path_config_value($spec, $name)
{
	return upgrade_path_value($spec, 'SELECT conf_value FROM %pconfig WHERE conf_name = \''.$name.'\'');
}


function upgrade_path_count($spec, $table)
{
	$count = upgrade_path_value($spec, 'SELECT COUNT(*) AS n FROM %p'.$table);

	return $count === null ? -1 : (int) $count;
}


/** The data patches a board has recorded, by name. */
function upgrade_path_patches($spec)
{
	return array_column(extension_flows_rows($spec, 'SELECT name FROM %pdata_patches ORDER BY name'), 'name');
}


/** The version a board records for each module, schema and data, by module. */
function upgrade_path_modules($spec)
{
	return extension_flows_rows($spec, 'SELECT name, schema_version, data_version FROM %pmodules ORDER BY name');
}


/**
 * Every piece of text the upgrade must not touch, in a stable order. Compared
 * before and after the run: the update script rewrites schema, never content.
 */
function upgrade_path_content($spec)
{
	$columns = array(
		'posts' => array('message', 'poster'),
		'topics' => array('subject', 'poster'),
		'forums' => array('forum_name', 'forum_desc'),
		'users' => array('username', 'signature', 'realname', 'location', 'url'),
		'categories' => array('cat_name'),
		'censoring' => array('search_for', 'replace_with'),
		'bans' => array('username', 'message'),
		'reports' => array('message'),
		'extensions' => array('title', 'description'),
	);

	$content = array();

	foreach ($columns as $table => $fields)
		foreach (extension_flows_rows($spec, 'SELECT '.implode(', ', $fields).' FROM %p'.$table.' ORDER BY id') as $row)
			foreach ($row as $field => $value)
				$content[] = $table.'.'.$field.'='.(string) $value;

	return $content;
}


/**
 * The schema of the board on $spec as its database reports it: every table,
 * each column's nullability, default and collation, the primary key, the
 * indexes and on MySQL the engine. Types are compared by the gap against the
 * declared schema, as the differ compares them: SQLite ignores the length a
 * type names. Column order is not compared: only MySQL places a column it adds.
 */
function upgrade_path_schema($spec)
{
	$engines = array();
	if ($spec['backend'] === 'mysql')
		foreach (extension_flows_rows($spec, 'SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()') as $row)
			$engines[$row['name']] = $row['engine'];

	$tables = extension_flows_tables($spec);

	// Only now: pg_connect() hands every caller the same link, and extension_flows_rows() closes it
	$reader = new PunBB\Module\Database\Schema\SchemaReader(install_matrix_connection($spec));
	$schema = array();

	foreach ($tables as $name)
	{
		$table = $reader->table($name);
		if ($table === null)
			continue;

		$columns = array();
		foreach ($table->columns as $column)
			$columns[$column->name] = ($column->nullable ? 'NULL' : 'NOT NULL').($column->default !== null ? ' DEFAULT \''.$column->default.'\'' : '').($column->collation !== null ? ' COLLATE '.$column->collation : '');

		ksort($columns);

		$indexes = array();
		foreach ($table->indexes as $index)
			$indexes[] = ($index->unique ? 'unique ' : '').($index->name ?? '(unnamed)').' ('.implode(', ', $index->columns).')';

		sort($indexes);

		$schema[$name] = array(
			'columns' => $columns,
			'primary key' => implode(', ', $table->primaryKey),
			'indexes' => $indexes,
			'engine' => $engines[$spec['prefix'].$name] ?? '',
		);
	}

	return $schema;
}


/** How $actual differs from $expected, one line per difference. */
function upgrade_path_schema_diff($expected, $actual)
{
	$differences = array();

	foreach (array_diff_key($expected, $actual) as $table => $unused)
		$differences[] = 'table '.$table.' is missing';

	foreach (array_diff_key($actual, $expected) as $table => $unused)
		$differences[] = 'table '.$table.' is not in a fresh install';

	foreach (array_intersect_key($actual, $expected) as $table => $shape)
	{
		foreach (array_diff_key($expected[$table]['columns'], $shape['columns']) as $column => $unused)
			$differences[] = $table.'.'.$column.' is missing';

		foreach (array_diff_key($shape['columns'], $expected[$table]['columns']) as $column => $unused)
			$differences[] = $table.'.'.$column.' is not in a fresh install';

		foreach (array_intersect_key($shape['columns'], $expected[$table]['columns']) as $column => $definition)
			if ($definition !== $expected[$table]['columns'][$column])
				$differences[] = $table.'.'.$column.' is '.$definition.', a fresh install has '.$expected[$table]['columns'][$column];

		foreach (array('primary key', 'indexes', 'engine') as $part)
			if ($shape[$part] !== $expected[$table][$part])
				$differences[] = $table.' '.$part.': '.json_encode($shape[$part], JSON_UNESCAPED_UNICODE).', a fresh install has '.json_encode($expected[$table][$part], JSON_UNESCAPED_UNICODE);
	}

	return $differences;
}


/**
 * A fresh install of this release on $db_type, installed over HTTP beside the
 * upgrades and removed again: its schema and the data patches it recorded, or
 * null with the reason in $failures.
 */
function upgrade_path_fresh($db_type, $base_url, $log, &$failures)
{
	$spec = upgrade_path_drivers(true)[$db_type];
	$jar = (string) tempnam(sys_get_temp_dir(), 'upfresh');

	@unlink(UPGRADE_PATH_ROOT.'config.php');
	install_matrix_clear_cache();
	install_matrix_drop_schema($spec);

	// Each upgrade empties the log it asserts on, so the install asserts its own
	if ($log !== '')
		install_matrix_truncate_log($log);

	try
	{
		$response = smoke_request($base_url.'/admin/install.php', $jar, install_matrix_form_fields($db_type, $spec, $base_url));
		$failures = array_merge($failures, smoke_diagnostics($response['body']));

		if ($response['status'] !== 200 || !install_matrix_install_succeeded($response['body']))
		{
			$failures[] = 'the fresh install to compare with did not complete: HTTP '.$response['status'].', '.install_matrix_failure_reason($response['body']);
			return null;
		}

		return array('schema' => upgrade_path_schema($spec), 'patches' => upgrade_path_patches($spec), 'modules' => upgrade_path_modules($spec));
	}
	finally
	{
		@unlink($jar);
		@unlink(UPGRADE_PATH_ROOT.'config.php');
		install_matrix_drop_schema($spec);
		install_matrix_clear_cache();

		$failures = array_merge($failures, install_matrix_log_diagnostics($log));
	}
}


/**
 * config.php for the fixture database, as the installer of 1.4 and 1.5 wrote
 * it: IDNA and hooks behind the two commented-out defines the flows switch on.
 */
function upgrade_path_config($spec, $base_url, $db_type)
{
	return "<?php\n\n".
		'$db_type = \''.$db_type."';\n".
		'$db_host = \''.$spec['host']."';\n".
		'$db_name = \''.$spec['name']."';\n".
		'$db_username = \''.$spec['username']."';\n".
		'$db_password = \''.$spec['password']."';\n".
		'$db_prefix = \''.$spec['prefix']."';\n".
		'$p_connect = false;'."\n\n".
		'$base_url = \''.$base_url."';\n\n".
		'$cookie_name = \'forum_cookie_upgrade\';'."\n".
		'$cookie_domain = \'\';'."\n".
		'$cookie_path = \'/\';'."\n".
		'$cookie_secure = 0;'."\n\n".
		"define('FORUM', 1);\n\n".
		"// Enable forum IDNA support by removing // from the following line\n//define('FORUM_ENABLE_IDNA', 1);\n\n".
		"// Disable forum hooks (extensions) by removing // from the following line\n//define('FORUM_DISABLE_HOOKS', 1);\n";
}


/** upgrade_path_config(), written where the forum reads it. */
function upgrade_path_write_config($spec, $base_url, $db_type)
{
	file_put_contents(UPGRADE_PATH_ROOT.'config.php', upgrade_path_config($spec, $base_url, $db_type));
	install_matrix_clear_cache();
}


/**
 * Wait until the site serves the config.php that was just written. The forum
 * includes config.php on every request, but an opcache still inside its
 * revalidate window keeps compiling the previous one — the assertions would
 * then be made against whichever database the checkout was pointed at before.
 */
function upgrade_path_await($base_url, $jar, $needle, $attempts = 20)
{
	for ($attempt = 0; $attempt < $attempts; ++$attempt)
	{
		$response = smoke_request($base_url.'/admin/db_update.php', $jar);

		if (strpos((string) $response['body'], $needle) !== false)
			return $response;

		usleep(500000);
	}

	return null;
}


/**
 * Follow db_update.php from its start stage to its completion page. It hands
 * the browser on with a window.location per stage, so the chain is the run;
 * $stages collects each stage's address and page. Returns the list of
 * failures, empty when the update completed.
 */
function upgrade_path_drive($base_url, $jar, &$diagnostics, &$stages, $max_stages = 500)
{
	$stages = array();
	$next = 'db_update.php?stage=start';

	for ($stage = 0; $stage < $max_stages; ++$stage)
	{
		$response = smoke_request($base_url.'/admin/'.$next, $jar);
		$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));
		$stages[] = array('url' => $next, 'body' => (string) $response['body']);

		if ($response['status'] !== 200)
			return array($next.' returned HTTP '.$response['status'].($response['error'] !== '' ? ' ('.$response['error'].')' : ''));

		if (upgrade_path_completed($response['body']))
			return array_map(static fn(string $markup): string => 'the completion page carries '.$markup, smoke_dead_markup($response['body']));

		$next = upgrade_path_next_url($response['body']);

		if ($next === '')
			return array('the update stopped without completing: '.install_matrix_failure_reason($response['body']));
	}

	return array('the update never completed: still redirecting after '.$max_stages.' stages');
}


/** What a second run must leave exactly as it found it. */
function upgrade_path_state($spec)
{
	return array(
		'schema' => upgrade_path_schema($spec),
		'content' => upgrade_path_content($spec),
		'patches' => extension_flows_rows($spec, 'SELECT name, applied FROM %pdata_patches ORDER BY name'),
		'modules' => upgrade_path_modules($spec),
	);
}


/**
 * The update run a second time over the board it just upgraded. As it stands
 * the script refuses, the board being up to date; set back to the version rows
 * of $fixture, it finds every module recorded at its version and every patch
 * applied, so it goes from the start straight to the finish. Returns the list
 * of failures.
 */
function upgrade_path_rerun($fixture, $spec, $base_url, $jar, &$diagnostics)
{
	$failures = array();

	$response = smoke_request($base_url.'/admin/db_update.php', $jar);
	$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));

	if (!upgrade_path_up_to_date($response['body']))
		$failures[] = 'db_update.php did not refuse to run again on the upgraded board: '.install_matrix_failure_reason($response['body']);

	$before = upgrade_path_state($spec);

	foreach (array('o_cur_version', 'o_database_revision') as $name)
		extension_flows_rows($spec, 'UPDATE %pconfig SET conf_value = \''.upgrade_path_fixture_config($fixture, $name).'\' WHERE conf_name = \''.$name.'\'');

	install_matrix_clear_cache();

	$stages = array();
	$failures = array_merge($failures, upgrade_path_drive($base_url, $jar, $diagnostics, $stages));

	if ($failures)
		return $failures;

	$visited = array_column($stages, 'url');
	if ($visited !== array('db_update.php?stage=start', 'db_update.php?stage=finish'))
		$failures[] = 'the second run went through '.implode(', ', $visited).', not from the start straight to the finish';

	// A change or a patch is reported as a line ending in an ellipsis
	if (strpos($stages[0]['body'], '…') !== false)
		$failures[] = 'the second run\'s start changed the schema: '.trim(strip_tags(substr($stages[0]['body'], 0, (int) strpos($stages[0]['body'], '<script'))));

	$after = upgrade_path_state($spec);

	foreach (upgrade_path_schema_diff($before['schema'], $after['schema']) as $difference)
		$failures[] = 'the second run changed the schema: '.$difference;

	if ($after['content'] !== $before['content'])
		$failures[] = 'the second run changed '.count(array_diff($before['content'], $after['content'])).' row value(s)';

	if ($after['patches'] !== $before['patches'])
		$failures[] = 'the second run changed the recorded patches: '.json_encode($after['patches']);

	if ($after['modules'] !== $before['modules'])
		$failures[] = 'the second run changed the recorded module versions: '.json_encode($after['modules']);

	return $failures;
}


/** config.old.<time>.php is the update script's backup, by file name. */
function upgrade_path_config_backups()
{
	return array_map('basename', (array) glob(UPGRADE_PATH_ROOT.'config.old.*.php'));
}


/**
 * The backups this run produced; they must not pile up. A backup that was
 * already there belongs to a real upgrade of this checkout and carries its
 * database credentials, so it is left alone.
 */
function upgrade_path_clear_config_backups($known)
{
	$removed = array();

	foreach (upgrade_path_config_backups() as $backup)
	{
		if (in_array($backup, $known, true))
			continue;

		$removed[] = $backup;
		@unlink(UPGRADE_PATH_ROOT.$backup);
	}

	return $removed;
}


/**
 * The functional pass over the upgraded forum: the pages a visitor sees, the
 * hook of the extension the fixture had enabled, then login, posting and search
 * as the fixture administrator. Every request is swept for diagnostics, so the
 * upgraded data is walked exactly the way a fresh install is.
 */
function upgrade_path_functional_pass($base_url, &$diagnostics)
{
	$state = array(
		'base_url' => $base_url,
		'jars' => array('admin' => (string) tempnam(sys_get_temp_dir(), 'upgradef')),
		'diagnostics' => array(),
	);

	$failures = array();

	try
	{
		$index = user_flows_get($state, 'admin', 'index.php');

		user_flows_assert(strpos((string) $index['body'], 'Ärger &amp; Umlauts') !== false,
			'index.php does not list the upgraded forums: '.user_flows_summary($index['body']));

		user_flows_assert(strpos((string) $index['body'], UPGRADE_PATH_HOOK_MARKER) !== false,
			'the fixture extension\'s hd_head hook did not run after the update: '.user_flows_summary($index['body']));

		$forum = user_flows_get($state, 'admin', 'viewforum.php?id=1');
		user_flows_assert(strpos((string) $forum['body'], 'Приветствие') !== false,
			'viewforum.php does not list the upgraded topics: '.user_flows_summary($forum['body']));

		$topic = user_flows_get($state, 'admin', 'viewtopic.php?id=1');
		user_flows_assert(strpos((string) $topic['body'], 'Привет, мир!') !== false,
			'viewtopic.php does not render the upgraded posts: '.user_flows_summary($topic['body']));

		$form = user_flows_get($state, 'admin', 'login.php');
		$response = user_flows_submit($state, 'admin', $form, 'name="req_password"', array(
			'form_sent' => '1',
			'req_username' => UPGRADE_PATH_USERNAME,
			'req_password' => UPGRADE_PATH_PASSWORD,
			'login' => '1',
		));

		$index = user_flows_follow($state, 'admin', $response);
		user_flows_assert(strpos((string) $index['body'], 'logout') !== false,
			'the fixture administrator could not log in after the update: '.user_flows_summary($response['body']));

		$form = user_flows_get($state, 'admin', 'post.php?tid=1');
		$response = user_flows_submit($state, 'admin', $form, 'name="req_message"', array(
			'form_sent' => '1',
			'req_message' => UPGRADE_PATH_REPLY,
			'submit' => '1',
		));

		user_flows_follow($state, 'admin', $response);

		$topic = user_flows_get($state, 'admin', 'viewtopic.php?id=1');
		user_flows_assert(strpos((string) $topic['body'], UPGRADE_PATH_KEYWORD) !== false,
			'the reply posted on the upgraded forum is not in the topic: '.user_flows_summary($response['body']));

		// The reply is the only post the upgraded index knows about, so finding
		// it proves the index the update left behind is still being written to.
		$response = user_flows_get($state, 'admin', 'search.php?action=search&keywords='.UPGRADE_PATH_KEYWORD.'&show_as=topics');
		$results = user_flows_follow($state, 'admin', $response);

		user_flows_assert(strpos((string) $results['body'], 'Приветствие') !== false,
			'the search did not find the reply on the upgraded forum: '.user_flows_summary($results['body']));
	}
	catch (Throwable $e)
	{
		$failures[] = 'functional pass: '.($e instanceof UserFlowsFailure ? $e->getMessage() : get_class($e).': '.$e->getMessage());
	}

	@unlink($state['jars']['admin']);
	$diagnostics = array_merge($diagnostics, $state['diagnostics']);

	return $failures;
}


/** The extension flows over the upgraded forum, as the fixture administrator. */
function upgrade_path_extension_flows($base_url, $spec, &$diagnostics)
{
	echo "   -- extension flows\n";

	$state = extension_flows_state($base_url, $spec);

	try
	{
		$reason = install_matrix_login($base_url, $state['jars']['admin'], $diagnostics, UPGRADE_PATH_USERNAME, UPGRADE_PATH_PASSWORD);

		if ($reason !== '')
			return array('extension flows: the fixture administrator could not log in: '.$reason);

		return array_map(static fn(string $failure): string => 'extension flows: '.$failure, extension_flows_walk($state));
	}
	finally
	{
		foreach ($state['jars'] as $jar)
			@unlink($jar);

		$diagnostics = array_merge($diagnostics, $state['diagnostics']);
	}
}


/** The user flows over the upgraded forum, set up as user_flows_run() sets up its own. MySQL only, as they are. */
function upgrade_path_user_flows($base_url, $spec, &$diagnostics, &$user_id)
{
	echo "   -- user flows\n";

	$reason = user_flows_enable_idna();

	if ($reason !== '')
		return array('user flows: '.$reason);

	user_flows_relax_throttles($spec);

	return array_map(static fn(string $failure): string => 'user flows: '.$failure,
		user_flows_walk($base_url, $spec, array(UPGRADE_PATH_USERNAME, UPGRADE_PATH_PASSWORD), $diagnostics, $user_id));
}


/** One release on one driver, end to end. Returns the list of failures. */
function upgrade_path_run($release, $db_type, $spec, $fresh, $base_url, $log)
{
	$failures = array();
	$diagnostics = array();
	$user_id = 0;
	$jar = (string) tempnam(sys_get_temp_dir(), 'upgrade');
	$backups = upgrade_path_config_backups();
	$fixture = upgrade_path_fixture_sql($release, $db_type, $spec['prefix']);

	upgrade_path_restore($release, $db_type, $spec);
	install_matrix_truncate_log($log);

	// A database this script cannot open must be reported by name, not by
	// whatever the missing dblayer would have done.
	$removed = upgrade_path_removed_driver($db_type);

	if ($removed !== '')
	{
		upgrade_path_write_config($spec, $base_url, $removed);
		$guard = upgrade_path_await($base_url, $jar, upgrade_path_removed_driver_message($removed));

		if ($guard === null)
		{
			$response = smoke_request($base_url.'/admin/db_update.php', $jar);
			$failures[] = 'the removed-driver guard did not fire for \''.$removed.'\': '.trim(strip_tags((string) $response['body']));
		}
		else
			$diagnostics = array_merge($diagnostics, smoke_diagnostics($guard['body']));
	}

	// Now the real thing.
	upgrade_path_write_config($spec, $base_url, $db_type);
	$before = upgrade_path_content($spec);

	foreach (UPGRADE_PATH_MARKERS as $marker)
		if (!in_array(true, array_map(static fn(string $row): bool => strpos($row, $marker) !== false, $before), true))
			$failures[] = 'the restored fixture already lost '.$marker;

	$form = upgrade_path_await($base_url, $jar, 'value="Start update"');

	if ($form === null)
		$failures[] = 'db_update.php never offered the update: the site is not serving the fixture database';
	else
	{
		$stages = array();
		$diagnostics = array_merge($diagnostics, smoke_diagnostics($form['body']));

		foreach (smoke_dead_markup($form['body']) as $markup)
			$failures[] = 'the update form carries '.$markup;

		$failures = array_merge($failures, upgrade_path_drive($base_url, $jar, $diagnostics, $stages));
	}

	if (!$failures)
	{
		foreach (upgrade_path_target_versions(UPGRADE_PATH_ROOT) as $name => $expected_value)
		{
			$value = (string) upgrade_path_config_value($spec, $name);
			if ($value !== $expected_value)
				$failures[] = $name.' is \''.$value.'\' after the update, expected \''.$expected_value.'\'';
		}

		foreach (array('users' => 4, 'topics' => 2, 'posts' => 4, 'forums' => 2, 'extensions' => 1) as $table => $rows)
		{
			$count = upgrade_path_count($spec, $table);
			if ($count !== $rows)
				$failures[] = $table.' holds '.$count.' row(s) after the update, expected '.$rows;
		}

		$after = upgrade_path_content($spec);
		if ($after !== $before)
			$failures[] = count(array_diff($before, $after)).' row value(s) changed during the update: '.
				implode(' | ', array_slice(array_diff($before, $after), 0, 3));

		// sync_forum() runs at the finish stage: the counters it rebuilds have to
		// match the fixture's own, or the upgraded forum lies about its content.
		if (($topics = (string) upgrade_path_value($spec, 'SELECT num_topics FROM %pforums WHERE id = 1')) !== '1')
			$failures[] = 'forum 1 reports '.$topics.' topic(s) after the resync, expected 1';

		if (($avatar = (string) upgrade_path_value($spec, 'SELECT avatar FROM %pusers WHERE id = 3')) !== '1')
			$failures[] = 'the fixture avatar flag is \''.$avatar.'\' after the update, expected \'1\'';

		foreach (install_matrix_schema_gap($db_type, $spec) as $change)
			$failures[] = 'the upgraded schema is not the declared one, it needs: '.$change;

		foreach (upgrade_path_schema_diff($fresh['schema'], upgrade_path_schema($spec)) as $difference)
			$failures[] = 'the upgraded schema is not a fresh install\'s: '.$difference;

		if (($patches = upgrade_path_patches($spec)) !== $fresh['patches'])
			$failures[] = 'the upgrade recorded the patches '.implode(', ', $patches).', a fresh install records '.implode(', ', $fresh['patches']);

		// The fixtures predate module versions: every module is brought up and recorded as a fresh install records it
		if (($modules = upgrade_path_modules($spec)) !== $fresh['modules'])
			$failures[] = 'the upgrade recorded the module versions '.json_encode($modules).', a fresh install records '.json_encode($fresh['modules']);
	}

	if (!$failures)
		$failures = upgrade_path_rerun($fixture, $spec, $base_url, $jar, $diagnostics);

	// The content assertions above are done, so the passes may add posts of
	// their own: the upgraded data has to serve pages, not only match row values.
	if (!$failures)
		$failures = upgrade_path_functional_pass($base_url, $diagnostics);

	if (!$failures)
		$failures = upgrade_path_extension_flows($base_url, $spec, $diagnostics);

	if (!$failures && $spec['backend'] === 'mysql')
		$failures = upgrade_path_user_flows($base_url, $spec, $diagnostics, $user_id);

	@unlink($jar);

	foreach (array_unique(array_merge($diagnostics, install_matrix_log_diagnostics($log))) as $line)
		$failures[] = $line;

	// The fixtures have no o_base_url row, so the update script has no reason to
	// rewrite config.php — a backup here means it took a path 1.4 never takes.
	foreach (upgrade_path_clear_config_backups($backups) as $backup)
		$failures[] = 'the update rewrote config.php and left '.$backup;

	// Leave nothing behind: the schema is gone, so a config.php naming it would
	// only make the checkout serve the database-error page.
	user_flows_clear_avatars($user_id);
	@unlink(UPGRADE_PATH_ROOT.'config.php');
	extension_flows_drop_schema($spec);
	install_matrix_clear_cache();

	return $failures;
}


function upgrade_path_main($base_url, $requested, $log)
{
	$drivers = upgrade_path_drivers();
	$unknown = array_diff($requested, array_keys($drivers));

	if ($unknown)
	{
		fwrite(STDERR, 'unknown driver(s): '.implode(', ', $unknown)."\n");
		return 2;
	}

	if ($requested)
		$drivers = array_intersect_key($drivers, array_flip($requested));

	$target = upgrade_path_target_versions(UPGRADE_PATH_ROOT)['o_cur_version'];

	echo 'upgrade path on '.$base_url.' (PHP '.PHP_VERSION.")\n\n";

	$failed = array();

	foreach ($drivers as $db_type => $spec)
	{
		$fresh_failures = array();

		try
		{
			$fresh = upgrade_path_fresh($db_type, $base_url, $log, $fresh_failures);

			if ($fresh_failures !== array())
				$fresh = null;
		}
		catch (Throwable $e)
		{
			$fresh = null;
			$fresh_failures[] = get_class($e).': '.$e->getMessage();
		}

		foreach (UPGRADE_PATH_RELEASES as $release)
		{
			$name = $release.' to '.$target.' on '.$db_type;
			echo '== '.$name." ==\n";

			if ($fresh === null)
				$failures = $fresh_failures;
			else
			{
				try
				{
					$failures = upgrade_path_run($release, $db_type, $spec, $fresh, $base_url, $log);
				}
				catch (Throwable $e)
				{
					$failures = array(get_class($e).': '.$e->getMessage());
				}
			}

			if ($failures)
			{
				$failed[] = $name;
				foreach ($failures as $failure)
					echo '   FAIL  '.$failure."\n";
			}
			else
				echo "   ok    restored, upgraded to a fresh install's schema, rerun unchanged, content intact, forum usable\n";

			echo "\n";
		}
	}

	if ($failed)
	{
		echo count($failed).' upgrade(s) failed: '.implode(', ', $failed)."\n";
		return 1;
	}

	echo count($drivers) * count(UPGRADE_PATH_RELEASES)." upgrade(s) passed\n";

	return 0;
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
{
	install_matrix_stash_config();
	register_shutdown_function('extension_flows_unlink_fixtures');

	exit(upgrade_path_main(
		rtrim(getenv('PUNBB_TEST_BASE_URL') ?: 'http://localhost', '/'),
		array_slice($argv, 1),
		(string) getenv('PUNBB_TEST_ERROR_LOG')
	));
}
