<?php
/**
 * Fresh-install matrix.
 *
 * Drives admin/install.php over HTTP once per supported database driver and
 * asserts, for each of them, that the install completes, config.php is written
 * for that driver, the whole schema exists and is populated, the admin account
 * can log in, and not one PHP diagnostic was emitted along the way.
 *
 * Run it from inside the web container — it needs the forum both as files (it
 * moves config.php aside and back) and as a running site on $base_url.
 *
 *   php .dev/tests/Integration/install_matrix.php [driver ...]
 *
 * Environment (all optional, defaults match a stock dev stack):
 *   PUNBB_TEST_BASE_URL          site URL the installer is driven on
 *   PUNBB_TEST_MYSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_PGSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_ERROR_LOG         error log file to assert on, when there is one
 *
 * Every driver gets its own table prefix (SQLite gets its own file), so the
 * matrix never touches a forum already installed in the same database.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

require_once dirname(__DIR__, 2).'/bin/smoke.php';

define('INSTALL_MATRIX_ROOT', dirname(__DIR__, 3).'/');
define('INSTALL_MATRIX_SQLITE', '.dev/tmp/matrix/matrix.sqlite3');

// Admin account every install in the matrix creates.
const INSTALL_MATRIX_USERNAME = 'matrix-admin';
const INSTALL_MATRIX_PASSWORD = 'matrix-password';
const INSTALL_MATRIX_EMAIL = 'matrix-admin@example.invalid';


// The tables a fresh install creates, unprefixed. install_matrix_installer_tables()
// reads the same list back out of the installer, so drift fails the unit suite.
function install_matrix_expected_tables()
{
	return array(
		'bans', 'categories', 'censoring', 'config', 'extension_hooks', 'extensions',
		'forum_perms', 'forum_subscriptions', 'forums', 'groups', 'online', 'posts',
		'ranks', 'reports', 'search_cache', 'search_matches', 'search_words',
		'subscriptions', 'topics', 'users',
	);
}


/** The tables admin/install.php actually creates, read out of its source. */
function install_matrix_installer_tables($installer)
{
	if (!preg_match_all('/create_table\(\s*\'([a-z_]+)\'/', (string) @file_get_contents($installer), $matches))
		return array();

	$tables = array_unique($matches[1]);
	sort($tables);

	return array_values($tables);
}


/**
 * One entry per driver: how to reach its database and where to put its tables.
 * Kept separate from the run so the shape can be asserted without a database.
 */
function install_matrix_drivers()
{
	$mysql = array(
		'host' => getenv('PUNBB_TEST_MYSQL_HOST') ?: 'punbb-mysql',
		'username' => getenv('PUNBB_TEST_MYSQL_USER') ?: 'punbb',
		'password' => getenv('PUNBB_TEST_MYSQL_PASSWORD') ?: 'punbb',
		'name' => getenv('PUNBB_TEST_MYSQL_DBNAME') ?: 'punbb',
	);

	$pgsql = array(
		'host' => getenv('PUNBB_TEST_PGSQL_HOST') ?: 'punbb-postgres',
		'username' => getenv('PUNBB_TEST_PGSQL_USER') ?: 'punbb',
		'password' => getenv('PUNBB_TEST_PGSQL_PASSWORD') ?: 'punbb',
		'name' => getenv('PUNBB_TEST_PGSQL_DBNAME') ?: 'punbb',
	);

	return array(
		'mysqli' => $mysql + array('backend' => 'mysql', 'prefix' => 'mx1_'),
		'mysqli_innodb' => $mysql + array('backend' => 'mysql', 'prefix' => 'mx2_'),
		'pgsql' => $pgsql + array('backend' => 'pgsql', 'prefix' => 'mx3_'),
		// SQLite reads $db_name as a path below FORUM_ROOT and gets a file to itself.
		'sqlite3' => array(
			'backend' => 'sqlite3', 'prefix' => '',
			'host' => '', 'username' => '', 'password' => '',
			'name' => INSTALL_MATRIX_SQLITE,
		),
	);
}


/** The install form, filled in for one driver. */
function install_matrix_form_fields($db_type, $spec, $base_url)
{
	return array(
		'form_sent' => '1',
		'req_db_type' => $db_type,
		'req_db_host' => $spec['host'],
		'req_db_name' => $spec['name'],
		'db_username' => $spec['username'],
		'db_password' => $spec['password'],
		'db_prefix' => $spec['prefix'],
		'req_username' => INSTALL_MATRIX_USERNAME,
		'req_email' => INSTALL_MATRIX_EMAIL,
		'req_password1' => INSTALL_MATRIX_PASSWORD,
		'req_language' => 'English',
		'req_base_url' => $base_url,
		'install_pun_repository' => '1',
	);
}


/** The installer's success page, and nothing else, carries these two markers. */
function install_matrix_install_succeeded($body)
{
	return strpos((string) $body, 'Final instructions') !== false
		&& strpos((string) $body, 'has been fully installed') !== false;
}


/** $db_type out of a written config.php, or '' when it names none. */
function install_matrix_config_db_type($config_body)
{
	return preg_match('/\$db_type\s*=\s*([\'"])(.*?)\1/', (string) $config_body, $match) ? $match[2] : '';
}


// mysqli throws by default since PHP 8.1, so the failure arrives as an
// exception rather than a false return. It becomes a matrix failure, not a fatal.
function install_matrix_mysql($spec)
{
	try
	{
		return mysqli_connect($spec['host'], $spec['username'], $spec['password'], $spec['name']);
	}
	catch (mysqli_sql_exception $e)
	{
		throw new RuntimeException('cannot reach MySQL on '.$spec['host'].': '.$e->getMessage());
	}
}


function install_matrix_pgsql($spec)
{
	$dsn = 'host='.$spec['host'].' user='.$spec['username'].' password='.$spec['password'].' dbname='.$spec['name'];
	$link = @pg_connect($dsn);
	if (!$link)
		throw new RuntimeException('cannot reach PostgreSQL on '.$spec['host']);

	return $link;
}


/** Every prefixed table of one driver, dropped; SQLite loses its whole file. */
function install_matrix_drop_schema($spec)
{
	$tables = install_matrix_expected_tables();

	switch ($spec['backend'])
	{
		case 'mysql':
			$link = install_matrix_mysql($spec);
			foreach ($tables as $table)
				mysqli_query($link, 'DROP TABLE IF EXISTS `'.$spec['prefix'].$table.'`');
			mysqli_close($link);
			break;

		case 'pgsql':
			$link = install_matrix_pgsql($spec);
			foreach ($tables as $table)
				@pg_query($link, 'DROP TABLE IF EXISTS "'.$spec['prefix'].$table.'" CASCADE');
			pg_close($link);
			break;

		case 'sqlite3':
			@unlink(INSTALL_MATRIX_ROOT.$spec['name']);
			break;
	}
}


/** The prefixed tables of one driver that exist right now. */
function install_matrix_present_tables($spec)
{
	$found = array();

	switch ($spec['backend'])
	{
		case 'mysql':
			$link = install_matrix_mysql($spec);
			foreach (install_matrix_expected_tables() as $table)
				if (mysqli_num_rows(mysqli_query($link, 'SHOW TABLES LIKE \''.mysqli_real_escape_string($link, $spec['prefix'].$table).'\'')) > 0)
					$found[] = $table;
			mysqli_close($link);
			break;

		case 'pgsql':
			$link = install_matrix_pgsql($spec);
			foreach (install_matrix_expected_tables() as $table)
			{
				$result = pg_query_params($link, 'SELECT to_regclass($1) IS NOT NULL', array($spec['prefix'].$table));
				if ($result && pg_fetch_result($result, 0, 0) === 't')
					$found[] = $table;
			}
			pg_close($link);
			break;

		case 'sqlite3':
			$file = INSTALL_MATRIX_ROOT.$spec['name'];
			if (!is_file($file))
				break;

			$link = new SQLite3($file, SQLITE3_OPEN_READONLY);
			foreach (install_matrix_expected_tables() as $table)
				if ($link->querySingle('SELECT name FROM sqlite_master WHERE type = \'table\' AND name = \''.SQLite3::escapeString($spec['prefix'].$table).'\'') !== null)
					$found[] = $table;
			$link->close();
			break;
	}

	return $found;
}


/** Row count of one prefixed table, or -1 when it cannot be read. */
function install_matrix_count($spec, $table)
{
	$name = $spec['prefix'].$table;

	switch ($spec['backend'])
	{
		case 'mysql':
			$link = install_matrix_mysql($spec);
			try
			{
				$result = mysqli_query($link, 'SELECT COUNT(*) FROM `'.$name.'`');
				$count = $result ? (int) mysqli_fetch_row($result)[0] : -1;
			}
			catch (mysqli_sql_exception $e)
			{
				$count = -1;
			}
			mysqli_close($link);
			return $count;

		case 'pgsql':
			$link = install_matrix_pgsql($spec);
			$result = @pg_query($link, 'SELECT COUNT(*) FROM "'.$name.'"');
			$count = $result ? (int) pg_fetch_result($result, 0, 0) : -1;
			pg_close($link);
			return $count;

		case 'sqlite3':
			$file = INSTALL_MATRIX_ROOT.$spec['name'];
			if (!is_file($file))
				return -1;

			$link = new SQLite3($file, SQLITE3_OPEN_READONLY);
			$count = ($row = @$link->querySingle('SELECT COUNT(*) FROM "'.$name.'"')) === false ? -1 : (int) $row;
			$link->close();
			return $count;
	}

	return -1;
}


/** cache/*.php is per-install state and must never survive into the next driver. */
function install_matrix_clear_cache()
{
	foreach ((array) glob(INSTALL_MATRIX_ROOT.'cache/*.php') as $file)
		@unlink((string) $file);
}


/**
 * Wait until the running site serves the install that was just written, not the
 * one it served before. The forum reads config.php on every request, and an
 * opcache still inside its revalidate window keeps compiling the previous file —
 * the login would then be checked against the previous forum's user table.
 * Returns false when the site never caught up.
 */
function install_matrix_await_install($base_url, $jar, $attempts = 20)
{
	for ($attempt = 0; $attempt < $attempts; $attempt++)
	{
		$response = smoke_request($base_url.'/userlist.php', $jar);

		if (strpos((string) $response['body'], INSTALL_MATRIX_USERNAME) !== false)
			return true;

		usleep(500000);
	}

	return false;
}


/**
 * Log in as the account the install just created. Returns '' on success and the
 * reason otherwise. The CSRF token is bound to $base_url, so this only passes
 * when the matrix drives the site on exactly the URL it installed it with.
 */
function install_matrix_login($base_url, $jar, &$diagnostics)
{
	if (!install_matrix_await_install($base_url, $jar))
		return 'the site never started serving the new install';

	$form = smoke_request($base_url.'/login.php', $jar);
	$diagnostics = array_merge($diagnostics, smoke_diagnostics($form['body']));

	if (!preg_match('/name="csrf_token" value="([^"]+)"/', (string) $form['body'], $match))
		return 'login.php rendered no csrf token';

	$response = smoke_request($base_url.'/login.php', $jar, array(
		'form_sent' => '1',
		'csrf_token' => $match[1],
		'req_username' => INSTALL_MATRIX_USERNAME,
		'req_password' => INSTALL_MATRIX_PASSWORD,
		'redirect_url' => $base_url.'/index.php',
	));
	$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));

	if (!in_array($response['status'], array(200, 302), true))
		return 'login returned HTTP '.$response['status'];

	$index = smoke_request($base_url.'/index.php', $jar);
	$diagnostics = array_merge($diagnostics, smoke_diagnostics($index['body']));

	if (strpos((string) $index['body'], 'logout') !== false)
		return '';

	// The confirm form means the token was rejected, which on a URL the matrix
	// installed itself is a real CSRF regression, not an environment mismatch.
	if (strpos((string) $response['body'], 'name="prev_url"') !== false)
		return 'the login POST hit the CSRF confirm form';

	return 'still anonymous after posting the login form (login POST returned HTTP '.$response['status'].')';
}


/**
 * New PHP diagnostics in the error log since install_matrix_truncate_log().
 *
 * PUNBB_TEST_ERROR_LOG is optional: when it is unset the caller is expected to
 * scan the container log itself. When it is set but unusable the gate would
 * silently pass, so that comes back as a failure instead.
 */
function install_matrix_log_diagnostics($log)
{
	if ($log === '')
		return array();

	if (!is_file($log) || !is_readable($log))
		return array('PUNBB_TEST_ERROR_LOG is set to '.$log.', which is not a readable file');

	return smoke_diagnostics((string) file_get_contents($log));
}


/** Announces once whether the in-script log gate is armed, and empties the log. */
function install_matrix_truncate_log($log)
{
	if ($log === '')
	{
		echo "   note  PUNBB_TEST_ERROR_LOG is unset: the PHP error log is not asserted here\n";
		return;
	}

	if (!is_file($log) || !is_writable($log))
	{
		echo '   note  PUNBB_TEST_ERROR_LOG is '.$log.", which cannot be truncated\n";
		return;
	}

	file_put_contents($log, '');
}


/** One driver end to end. Returns the list of failures, empty when it passed. */
function install_matrix_run_driver($db_type, $spec, $base_url, $log)
{
	$failures = array();
	$diagnostics = array();
	$config = INSTALL_MATRIX_ROOT.'config.php';

	// A driver starts from nothing: no config, no cache, no schema.
	@unlink($config);
	install_matrix_clear_cache();
	install_matrix_drop_schema($spec);
	install_matrix_truncate_log($log);

	$jar = (string) tempnam(sys_get_temp_dir(), 'matrix');

	$response = smoke_request($base_url.'/admin/install.php', $jar, install_matrix_form_fields($db_type, $spec, $base_url));
	$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));

	if ($response['status'] !== 200)
		$failures[] = 'installer returned HTTP '.$response['status'].($response['error'] !== '' ? ' ('.$response['error'].')' : '');
	else if (!install_matrix_install_succeeded($response['body']))
		$failures[] = 'installer did not reach the success page: '.install_matrix_failure_reason($response['body']);

	if (!is_file($config))
		$failures[] = 'config.php was not written';
	else if (($written = install_matrix_config_db_type((string) file_get_contents($config))) !== $db_type)
		$failures[] = 'config.php names $db_type \''.$written.'\', not \''.$db_type.'\'';

	$missing = array_diff(install_matrix_expected_tables(), install_matrix_present_tables($spec));
	if ($missing)
		$failures[] = count($missing).' table(s) missing: '.implode(', ', $missing);

	// A schema alone proves nothing: the installer also seeds these.
	foreach (array('users' => 2, 'config' => 1, 'forums' => 1, 'topics' => 1, 'posts' => 1, 'groups' => 1) as $table => $least)
	{
		$count = install_matrix_count($spec, $table);
		if ($count < $least)
			$failures[] = $table.' holds '.$count.' row(s), expected at least '.$least;
	}

	if (!$failures)
	{
		$reason = install_matrix_login($base_url, $jar, $diagnostics);
		if ($reason !== '')
			$failures[] = 'admin login failed: '.$reason;
	}

	@unlink($jar);

	foreach (array_unique(array_merge($diagnostics, install_matrix_log_diagnostics($log))) as $line)
		$failures[] = $line;

	// Leave nothing behind for the next driver, or for the checkout.
	@unlink($config);
	install_matrix_clear_cache();
	install_matrix_drop_schema($spec);

	return $failures;
}


/** The installer's own error message, when its page carries one. */
function install_matrix_failure_reason($body)
{
	if (preg_match('#<p[^>]*class="[^"]*warn[^"]*"[^>]*>(.*?)</p>#is', (string) $body, $match))
		return trim(strip_tags($match[1]));

	return 'HTTP 200 without the success markers';
}


/**
 * config.php belongs to whatever forum this checkout already has installed. The
 * matrix overwrites it, so it is stashed for the whole run and restored on the
 * way out — including on a fatal, hence the shutdown hook.
 */
function install_matrix_stash_config()
{
	$config = INSTALL_MATRIX_ROOT.'config.php';
	$stash = INSTALL_MATRIX_ROOT.'.dev/tmp/matrix/config.php.stash';

	// The stash is a verbatim copy of config.php, $db_password included.
	@mkdir(dirname($stash), 0700, true);
	@chmod(dirname($stash), 0700);

	// A stash left by a crashed run is the real config: never overwrite it.
	// A stash that cannot be written aborts the run: every driver starts by
	// deleting config.php, so without the copy the forum's own config is lost.
	if (!is_file($stash) && is_file($config) && !copy($config, $stash))
	{
		fwrite(STDERR, 'cannot stash '.$config.' to '.$stash."\n");
		exit(2);
	}

	install_matrix_stash_avatars();

	register_shutdown_function('install_matrix_restore_config');
}


/**
 * img/avatars belongs to the forum this checkout already has installed, and the
 * run uploads over it: member ids collide across installs and an avatar is not
 * regenerable. The directory is moved aside for the run and moved back on the
 * way out, on the same shutdown hook as config.php.
 */
function install_matrix_stash_avatars()
{
	$stash = INSTALL_MATRIX_ROOT.'.dev/tmp/matrix/avatars.stash';
	$marker = install_matrix_avatar_stash_marker();

	// A marked stash left by a crashed run holds every original: never touch it.
	// An unmarked one was interrupted halfway, so img/avatars still holds
	// originals as well — the move is finished here rather than skipped.
	if (is_dir($stash) && is_file($marker))
		return;

	if (!@mkdir($stash, 0700, true) && !is_dir($stash))
	{
		fwrite(STDERR, 'cannot stash avatars to '.$stash."\n");
		exit(2);
	}

	$moved = array();

	foreach (install_matrix_avatar_files(INSTALL_MATRIX_ROOT.'img/avatars') as $file)
	{
		if (rename($file, $stash.'/'.basename($file)))
		{
			$moved[] = basename($file);
			continue;
		}

		install_matrix_unstash_avatars($moved);
		fwrite(STDERR, 'cannot stash '.$file."\n");
		exit(2);
	}

	// Only a marked stash is known to hold every original, so the restore may
	// clear the live directory. Without the marker the run must not start.
	if (file_put_contents($marker, '') === false)
	{
		install_matrix_unstash_avatars($moved);
		fwrite(STDERR, 'cannot mark the avatar stash at '.$marker."\n");
		exit(2);
	}
}


/** The file that says the avatar stash holds every original. */
function install_matrix_avatar_stash_marker()
{
	return INSTALL_MATRIX_ROOT.'.dev/tmp/matrix/avatars.stash.complete';
}


/** Undo an interrupted stash: the named files go back to img/avatars. */
function install_matrix_unstash_avatars($names)
{
	$stash = INSTALL_MATRIX_ROOT.'.dev/tmp/matrix/avatars.stash';

	foreach ($names as $name)
		rename($stash.'/'.$name, INSTALL_MATRIX_ROOT.'img/avatars/'.$name);

	@rmdir($stash);
}


function install_matrix_restore_avatars()
{
	$stash = INSTALL_MATRIX_ROOT.'.dev/tmp/matrix/avatars.stash';
	$marker = install_matrix_avatar_stash_marker();

	if (!is_dir($stash))
		return;

	// Whatever the run uploaded goes first, then the originals come back — but
	// only a marked stash holds them all, so an unmarked one deletes nothing.
	if (is_file($marker))
	{
		foreach (install_matrix_avatar_files(INSTALL_MATRIX_ROOT.'img/avatars') as $file)
			@unlink($file);

		// The live directory is clear, so from here on the originals are split
		// across both directories: unmark before the first move back, or an
		// interrupted restore looks complete and the next run deletes the
		// originals it already put back.
		@unlink($marker);
	}

	foreach (install_matrix_avatar_files($stash) as $file)
		rename($file, INSTALL_MATRIX_ROOT.'img/avatars/'.basename($file));

	@rmdir($stash);
}


/** The avatar files of a directory: index.html is part of the checkout. */
function install_matrix_avatar_files($directory)
{
	$files = array();

	foreach ((array) glob(rtrim($directory, '/').'/*') as $file)
	{
		if (is_file($file) && basename((string) $file) !== 'index.html')
			$files[] = (string) $file;
	}

	return $files;
}


function install_matrix_restore_config()
{
	$config = INSTALL_MATRIX_ROOT.'config.php';
	$stash = INSTALL_MATRIX_ROOT.'.dev/tmp/matrix/config.php.stash';

	install_matrix_restore_avatars();

	if (!is_file($stash))
		return;

	// The stash is the only copy of the checkout's config.php: it goes only
	// once the restore is known to have written it.
	if (!copy($stash, $config))
	{
		fwrite(STDERR, 'cannot restore '.$config.' from '.$stash.', which is kept'."\n");
		return;
	}

	@unlink($stash);
	install_matrix_clear_cache();
}


function install_matrix_main($base_url, $requested, $log)
{
	$drivers = install_matrix_drivers();
	$unknown = array_diff($requested, array_keys($drivers));

	if ($unknown)
	{
		fwrite(STDERR, 'unknown driver(s): '.implode(', ', $unknown)."\n");
		return 2;
	}

	if ($requested)
		$drivers = array_intersect_key($drivers, array_flip($requested));

	echo 'install matrix on '.$base_url.' (PHP '.PHP_VERSION.")\n\n";

	$failed = array();

	foreach ($drivers as $db_type => $spec)
	{
		echo '== '.$db_type." ==\n";

		try
		{
			$failures = install_matrix_run_driver($db_type, $spec, $base_url, $log);
		}
		catch (Throwable $e)
		{
			$failures = array(get_class($e).': '.$e->getMessage());
		}

		if ($failures)
		{
			$failed[$db_type] = $failures;
			foreach ($failures as $failure)
				echo '   FAIL  '.$failure."\n";
		}
		else
			echo "   ok    installed, seeded and logged in\n";

		echo "\n";
	}

	if ($failed)
	{
		echo count($failed).' driver(s) failed: '.implode(', ', array_keys($failed))."\n";
		return 1;
	}

	echo count($drivers)." driver(s) passed\n";

	return 0;
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
{
	install_matrix_stash_config();

	exit(install_matrix_main(
		rtrim(getenv('PUNBB_TEST_BASE_URL') ?: 'http://localhost', '/'),
		array_slice($argv, 1),
		(string) getenv('PUNBB_TEST_ERROR_LOG')
	));
}
