<?php
/**
 * Upgrade path from 1.4.4.
 *
 * Restores the committed 1.4.4 fixture into MySQL under its own table prefix,
 * drives admin/db_update.php over HTTP until it reports completion, and asserts
 * that the version rows advanced, that every piece of non-ASCII content came
 * through untouched, and that not one PHP diagnostic was emitted along the way.
 * It then walks the upgraded forum over HTTP — pages, extension hook, login,
 * posting and search — so the upgraded data is exercised, not only asserted on.
 * It also asserts the removed-driver guard: a config.php naming 'mysql' has to
 * stop the update with the name of the driver to switch to.
 *
 * Run it from inside the web container — like the install matrix it needs the
 * forum both as files (it rewrites config.php) and as a running site.
 *
 *   php .dev/tests/Integration/upgrade_path.php
 *
 * Environment (all optional, defaults match a stock dev stack):
 *   PUNBB_TEST_BASE_URL          site URL the update script is driven on
 *   PUNBB_TEST_MYSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_ERROR_LOG         error log file to assert on, when there is one
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// The install matrix owns the shared pieces: the curl helpers it pulls in, the
// connection helper, the table list and the config.php stash both runs need.
require_once __DIR__.'/install_matrix.php';

// The functional pass over the upgraded forum drives the same form helpers the
// user flows do; only the steps below are this run's own.
require_once __DIR__.'/user_flows.php';

// The removed-driver map belongs to the forum, not to this script: the guard it
// asserts on is only worth anything if both read the same table.
if (!function_exists('forum_removed_db_type_replacement'))
	require_once dirname(__DIR__, 3).'/include/functions.php';

define('UPGRADE_PATH_ROOT', dirname(__DIR__, 3).'/');

// A prefix of its own, so the fixture never touches the forum installed in the
// same database, nor the prefixes the install matrix claims.
const UPGRADE_PATH_PREFIX = 'up1_';
const UPGRADE_PATH_FIXTURE = 'punbb-1.4.4-mysql.sql';

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


function upgrade_path_fixture_file()
{
	return __DIR__.'/fixtures/'.UPGRADE_PATH_FIXTURE;
}


/** How to reach MySQL, and where the fixture's tables go. */
function upgrade_path_spec()
{
	return array(
		'host' => getenv('PUNBB_TEST_MYSQL_HOST') ?: 'punbb-mysql',
		'username' => getenv('PUNBB_TEST_MYSQL_USER') ?: 'punbb',
		'password' => getenv('PUNBB_TEST_MYSQL_PASSWORD') ?: 'punbb',
		'name' => getenv('PUNBB_TEST_MYSQL_DBNAME') ?: 'punbb',
		'prefix' => UPGRADE_PATH_PREFIX,
	);
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


/** The fixture, with its %PREFIX% placeholder resolved. */
function upgrade_path_fixture_sql($prefix)
{
	$sql = @file_get_contents(upgrade_path_fixture_file());

	if ($sql === false)
		throw new RuntimeException('cannot read the fixture: '.upgrade_path_fixture_file());

	return str_replace('%PREFIX%', $prefix, $sql);
}


/** conf_value of one config row straight out of the fixture text. */
function upgrade_path_fixture_config($sql, $name)
{
	return preg_match('/\(\'' . preg_quote($name, '/') . '\',\s*\'([^\']*)\'\)/', (string) $sql, $match) ? $match[1] : '';
}


/** The version and revision admin/db_update.php upgrades a database to. */
function upgrade_path_target_versions($script)
{
	$source = (string) @file_get_contents($script);
	$version = preg_match('/define\(\'UPDATE_TO\',\s*\'([^\']+)\'\)/', $source, $match) ? $match[1] : '';
	$revision = preg_match('/define\(\'UPDATE_TO_DB_REVISION\',\s*(\d+)\)/', $source, $match) ? $match[1] : '';

	return array('o_cur_version' => $version, 'o_database_revision' => $revision);
}


/** The message plan 02's guard renders for a driver that no longer exists. */
function upgrade_path_removed_driver_message($db_type)
{
	$replacement = forum_removed_db_type_replacement($db_type);

	return 'Your config.php uses the \''.$db_type.'\' database driver, which was removed along with '.
		'the PHP extension it needs. Set $db_type to \''.$replacement.'\' in config.php and run this script again.';
}


/** The URL db_update.php sends the browser to next, or '' when it is done. */
function upgrade_path_next_url($body)
{
	return preg_match('/window\.location\s*=\s*"(db_update\.php[^"]*)"/', (string) $body, $match) ? $match[1] : '';
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


function upgrade_path_drop_schema($spec)
{
	$link = install_matrix_mysql($spec);

	foreach (install_matrix_expected_tables() as $table)
		mysqli_query($link, 'DROP TABLE IF EXISTS `'.$spec['prefix'].$table.'`');

	mysqli_close($link);
}


/** The fixture, restored from nothing. */
function upgrade_path_restore($spec)
{
	upgrade_path_drop_schema($spec);

	$link = install_matrix_mysql($spec);
	mysqli_set_charset($link, 'utf8mb4');

	try
	{
		foreach (upgrade_path_statements(upgrade_path_fixture_sql($spec['prefix'])) as $statement)
			mysqli_query($link, $statement);
	}
	catch (mysqli_sql_exception $e)
	{
		mysqli_close($link);
		throw new RuntimeException('restoring the fixture failed: '.$e->getMessage());
	}

	mysqli_close($link);
}


/** One scalar out of the fixture's database, or null when it cannot be read. */
function upgrade_path_value($spec, $sql)
{
	$link = install_matrix_mysql($spec);
	mysqli_set_charset($link, 'utf8mb4');

	try
	{
		$result = mysqli_query($link, $sql);
		$value = ($result && ($row = mysqli_fetch_row($result))) ? $row[0] : null;
	}
	catch (mysqli_sql_exception $e)
	{
		$value = null;
	}

	mysqli_close($link);

	return $value;
}


function upgrade_path_config_value($spec, $name)
{
	return upgrade_path_value($spec, 'SELECT conf_value FROM `'.$spec['prefix'].'config` WHERE conf_name = \''.$name.'\'');
}


function upgrade_path_count($spec, $table)
{
	$count = upgrade_path_value($spec, 'SELECT COUNT(*) FROM `'.$spec['prefix'].$table.'`');

	return $count === null ? -1 : (int) $count;
}


/**
 * Every piece of text the upgrade must not touch, in a stable order. Compared
 * before and after the run: the update script rewrites schema, never content.
 */
function upgrade_path_content($spec)
{
	$prefix = $spec['prefix'];

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

	$link = install_matrix_mysql($spec);
	mysqli_set_charset($link, 'utf8mb4');
	$content = array();

	foreach ($columns as $table => $fields)
	{
		$select = implode(', ', array_map(static fn(string $field): string => '`'.$field.'`', $fields));

		try
		{
			$result = mysqli_query($link, 'SELECT '.$select.' FROM `'.$prefix.$table.'` ORDER BY id');
		}
		catch (mysqli_sql_exception $e)
		{
			mysqli_close($link);
			throw new RuntimeException('reading '.$table.' failed: '.$e->getMessage());
		}

		while ($result && ($row = mysqli_fetch_assoc($result)))
			foreach ($row as $field => $value)
				$content[] = $table.'.'.$field.'='.(string) $value;
	}

	mysqli_close($link);

	return $content;
}


/** config.php for the fixture database, written where the forum reads it. */
function upgrade_path_write_config($spec, $base_url, $db_type)
{
	$config = "<?php\n\n".
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
		"define('FORUM', 1);\n";

	file_put_contents(UPGRADE_PATH_ROOT.'config.php', $config);
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
 * Follow db_update.php from its start form to its completion page. It hands the
 * browser on with a window.location per stage, so the chain is the run.
 * Returns the list of failures, empty when the update completed.
 */
function upgrade_path_drive($base_url, $jar, &$diagnostics, $max_stages = 500)
{
	$response = smoke_request($base_url.'/admin/db_update.php?stage=start', $jar);
	$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));

	if ($response['status'] !== 200)
		return array('the start stage returned HTTP '.$response['status'].($response['error'] !== '' ? ' ('.$response['error'].')' : ''));

	for ($stage = 0; $stage < $max_stages; ++$stage)
	{
		if (upgrade_path_completed($response['body']))
			return array();

		$next = upgrade_path_next_url($response['body']);

		if ($next === '')
			return array('the update stopped without completing: '.install_matrix_failure_reason($response['body']));

		$response = smoke_request($base_url.'/admin/'.$next, $jar);
		$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));

		if ($response['status'] !== 200)
			return array($next.' returned HTTP '.$response['status']);
	}

	return array('the update never completed: still redirecting after '.$max_stages.' stages');
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


/** The whole upgrade, end to end. Returns the list of failures. */
function upgrade_path_run($base_url, $log)
{
	$spec = upgrade_path_spec();
	$failures = array();
	$diagnostics = array();
	$jar = (string) tempnam(sys_get_temp_dir(), 'upgrade');
	$backups = upgrade_path_config_backups();

	upgrade_path_restore($spec);
	install_matrix_truncate_log($log);

	// A database this script cannot open must be reported by name, not by
	// whatever the missing dblayer would have done.
	upgrade_path_write_config($spec, $base_url, 'mysql');
	$expected = upgrade_path_removed_driver_message('mysql');
	$guard = upgrade_path_await($base_url, $jar, $expected);

	if ($guard === null)
	{
		$response = smoke_request($base_url.'/admin/db_update.php', $jar);
		$failures[] = 'the removed-driver guard did not fire for \'mysql\': '.trim(strip_tags((string) $response['body']));
	}
	else
		$diagnostics = array_merge($diagnostics, smoke_diagnostics($guard['body']));

	// Now the real thing.
	upgrade_path_write_config($spec, $base_url, 'mysqli');
	$before = upgrade_path_content($spec);

	foreach (UPGRADE_PATH_MARKERS as $marker)
		if (!in_array(true, array_map(static fn(string $row): bool => strpos($row, $marker) !== false, $before), true))
			$failures[] = 'the restored fixture already lost '.$marker;

	$form = upgrade_path_await($base_url, $jar, 'value="Start update"');

	if ($form === null)
		$failures[] = 'db_update.php never offered the update: the site is not serving the fixture database';
	else
	{
		$diagnostics = array_merge($diagnostics, smoke_diagnostics($form['body']));
		$failures = array_merge($failures, upgrade_path_drive($base_url, $jar, $diagnostics));
	}

	if (!$failures)
	{
		foreach (upgrade_path_target_versions(UPGRADE_PATH_ROOT.'admin/db_update.php') as $name => $expected_value)
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
		if (($topics = (string) upgrade_path_value($spec, 'SELECT num_topics FROM `'.$spec['prefix'].'forums` WHERE id = 1')) !== '1')
			$failures[] = 'forum 1 reports '.$topics.' topic(s) after the resync, expected 1';

		if (($avatar = (string) upgrade_path_value($spec, 'SELECT avatar FROM `'.$spec['prefix'].'users` WHERE id = 3')) !== '1')
			$failures[] = 'the fixture avatar flag is \''.$avatar.'\' after the update, expected \'1\'';
	}

	// The content assertions above are done, so the pass may add a post of its
	// own: the upgraded data has to serve pages, not only match row values.
	if (!$failures)
		$failures = upgrade_path_functional_pass($base_url, $diagnostics);

	@unlink($jar);

	foreach (array_unique(array_merge($diagnostics, install_matrix_log_diagnostics($log))) as $line)
		$failures[] = $line;

	// The fixture has no o_base_url row, so the update script has no reason to
	// rewrite config.php — a backup here means it took a path 1.4 never takes.
	foreach (upgrade_path_clear_config_backups($backups) as $backup)
		$failures[] = 'the update rewrote config.php and left '.$backup;

	// Leave nothing behind: the schema is gone, so a config.php naming it would
	// only make the checkout serve the database-error page.
	@unlink(UPGRADE_PATH_ROOT.'config.php');
	upgrade_path_drop_schema($spec);
	install_matrix_clear_cache();

	return $failures;
}


function upgrade_path_main($base_url, $log)
{
	echo 'upgrade path on '.$base_url.' (PHP '.PHP_VERSION.")\n\n";
	echo '== '.UPGRADE_PATH_FIXTURE." ==\n";

	try
	{
		$failures = upgrade_path_run($base_url, $log);
	}
	catch (Throwable $e)
	{
		$failures = array(get_class($e).': '.$e->getMessage());
	}

	if ($failures)
	{
		foreach ($failures as $failure)
			echo '   FAIL  '.$failure."\n";

		echo "\n".count($failures)." failure(s)\n";

		return 1;
	}

	echo "   ok    restored, upgraded, content intact, forum usable\n\nupgrade path passed\n";

	return 0;
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
{
	install_matrix_stash_config();

	exit(upgrade_path_main(
		rtrim(getenv('PUNBB_TEST_BASE_URL') ?: 'http://localhost', '/'),
		(string) getenv('PUNBB_TEST_ERROR_LOG')
	));
}
