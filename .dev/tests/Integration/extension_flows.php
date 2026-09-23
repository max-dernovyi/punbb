<?php
/**
 * Extension compatibility pass.
 *
 * Per driver: installs a forum of its own over HTTP, installs the synthetic
 * fixture extensions (.dev/tests/fixtures/extensions/) through
 * admin/extensions.php, walks the pages their hooks sit on and reads back from
 * punbb_fixture_markers which hooks fired, in what order and what they saw.
 * Then the by-reference, short-circuit and FORUM_DISABLE_HOOKS contracts, and
 * an uninstall that must put the schema and the config back as they were.
 * Every response and the error log are swept for PHP diagnostics.
 *
 * Run it from inside the web container — it rewrites config.php and links the
 * fixtures into extensions/, so it needs the checkout and the running site.
 *
 *   php .dev/tests/Integration/extension_flows.php [driver ...]
 *
 * Environment (all optional, defaults match a stock dev stack):
 *   PUNBB_TEST_BASE_URL          site URL the pass is driven on
 *   PUNBB_TEST_MYSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_PGSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_ERROR_LOG         error log file to assert on, when there is one
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// The functional pass owns the form driving; the install matrix under it owns
// the connections, the installer form and the config.php stash.
require_once __DIR__.'/user_flows.php';

define('EXTENSION_FLOWS_FIXTURES', INSTALL_MATRIX_ROOT.'.dev/tests/fixtures/extensions/');
define('EXTENSION_FLOWS_SQLITE', '.dev/tmp/matrix/extension_flows.sqlite3');

// Install order; uninstall runs it backwards.
const EXTENSION_FLOWS_IDS = array('punbb_fixture', 'punbb_fixture_dep');

// Documentation addresses (RFC 5737): one the short-circuit must return, one
// that must be ignored while hooks are disabled.
const EXTENSION_FLOWS_ADDRESS = '203.0.113.7';
const EXTENSION_FLOWS_DISABLED_ADDRESS = '198.51.100.23';

const EXTENSION_FLOWS_REPLY = 'Extension flows reply';


/** install_matrix_drivers() with prefixes and a SQLite file of this run's own. */
function extension_flows_drivers()
{
	$prefixes = array('mysqli' => 'ef1_', 'mysqli_innodb' => 'ef2_', 'pgsql' => 'ef3_', 'sqlite3' => '');
	$drivers = array();

	foreach (install_matrix_drivers() as $db_type => $spec)
		$drivers[$db_type] = array_merge($spec, array('prefix' => $prefixes[$db_type]));

	$drivers['sqlite3']['name'] = EXTENSION_FLOWS_SQLITE;

	return $drivers;
}


/** The points a fixture manifest attaches to, one entry per extension_hooks row. */
function extension_flows_manifest_points($id)
{
	$manifest = (string) @file_get_contents(EXTENSION_FLOWS_FIXTURES.$id.'/manifest.xml');
	$points = array();

	if (preg_match_all('#<hook\s+id="([^"]+)"#', $manifest, $matches))
		foreach ($matches[1] as $ids)
			foreach (explode(',', $ids) as $point)
				$points[] = trim($point);

	return $points;
}


/** A value for X-Punbb-Fixture-Request: unique, and unchanged by the fixture's sanitiser. */
function extension_flows_request_id($label)
{
	return substr(trim((string) preg_replace('/[^0-9a-z]+/', '-', strtolower($label)), '-'), 0, 23).'-'.bin2hex(random_bytes(8));
}


/** config.php with the installer's commented-out FORUM_DISABLE_HOOKS line switched on, or '' when it has none. */
function extension_flows_disable_hooks($config_body)
{
	$commented = "//define('FORUM_DISABLE_HOOKS', 1);";

	if (strpos((string) $config_body, $commented) === false)
		return '';

	return str_replace($commented, substr($commented, 2), (string) $config_body);
}


/** Markers as "extension:hook", in firing order, minus the hooks named in $except. */
function extension_flows_sequence($markers, $except = array('fn_get_remote_address_start'))
{
	$sequence = array();

	foreach ($markers as $marker)
		if (!in_array($marker['hook'], $except, true))
			$sequence[] = $marker['extension'].':'.$marker['hook'];

	return $sequence;
}


/** The markers one extension left at one hook. */
function extension_flows_pick($markers, $extension, $hook)
{
	return array_values(array_filter($markers, static fn(array $marker): bool =>
		$marker['extension'] === $extension && $marker['hook'] === $hook));
}


/**
 * Where each post's echo landed: post id => offset of the echo, or -1 when it is
 * not between that post's entry title and the next post's.
 */
function extension_flows_echo_offsets($body, $post_ids)
{
	$body = (string) $body;
	$offsets = array();
	$post_ids = array_values($post_ids);

	foreach ($post_ids as $index => $post_id)
	{
		$start = strpos($body, 'id="pc'.$post_id.'"');
		$end = isset($post_ids[$index + 1]) ? strpos($body, 'id="pc'.$post_ids[$index + 1].'"') : strlen($body);
		$echo = strpos($body, '<p class="punbb-fixture-echo" data-post-id="'.$post_id.'">');

		$offsets[$post_id] = ($start !== false && $end !== false && $echo !== false && $echo > $start && $echo < $end) ? $echo : -1;
	}

	return $offsets;
}


/** Rows of one query against this run's database, every value a string or null. */
function extension_flows_rows($spec, $sql)
{
	$sql = str_replace('%p', $spec['prefix'], $sql);
	$rows = array();

	switch ($spec['backend'])
	{
		case 'mysql':
			$link = install_matrix_mysql($spec);
			mysqli_set_charset($link, 'utf8mb4');

			try
			{
				$result = mysqli_query($link, $sql);
				while ($result instanceof mysqli_result && ($row = mysqli_fetch_assoc($result)))
					$rows[] = $row;
			}
			catch (mysqli_sql_exception $e)
			{
				mysqli_close($link);
				throw new UserFlowsFailure('query failed: '.$e->getMessage().': '.$sql);
			}

			mysqli_close($link);
			break;

		case 'pgsql':
			$link = install_matrix_pgsql($spec);
			$result = @pg_query($link, $sql);

			if ($result === false)
			{
				$error = pg_last_error($link);
				pg_close($link);
				throw new UserFlowsFailure('query failed: '.$error.': '.$sql);
			}

			while ($row = pg_fetch_assoc($result))
				$rows[] = $row;

			pg_close($link);
			break;

		case 'sqlite3':
			// One connection per query: SQLite refuses to drop a table another connection still reads.
			try
			{
				$link = new SQLite3(INSTALL_MATRIX_ROOT.$spec['name'], SQLITE3_OPEN_READWRITE);
				$link->enableExceptions(true);
				$link->busyTimeout(5000);

				$result = $link->query($sql);
				while ($result instanceof SQLite3Result && ($row = $result->fetchArray(SQLITE3_ASSOC)))
					$rows[] = $row;

				if ($result instanceof SQLite3Result)
					$result->finalize();

				$link->close();
			}
			catch (Exception $e)
			{
				throw new UserFlowsFailure('query failed: '.$e->getMessage().': '.$sql);
			}
			break;
	}

	return array_map(static fn(array $row): array => array_map(static fn($value): ?string => $value === null ? null : (string) $value, $row), $rows);
}


/** The markers one request left, in the order they were written. */
function extension_flows_markers($spec, $request_id)
{
	$rows = extension_flows_rows($spec, 'SELECT extension_id, hook_id, seen FROM %ppunbb_fixture_markers WHERE request_id = \''.preg_replace('/[^0-9a-z-]/', '', $request_id).'\' ORDER BY id');

	return array_map(static fn(array $row): array => array(
		'extension' => $row['extension_id'],
		'hook' => $row['hook_id'],
		'seen' => json_decode((string) $row['seen'], true),
	), $rows);
}


/** Every table of this run's prefix, unprefixed. */
function extension_flows_tables($spec)
{
	switch ($spec['backend'])
	{
		case 'mysql':
			$sql = 'SHOW TABLES';
			break;

		case 'pgsql':
			$sql = 'SELECT tablename FROM pg_tables WHERE schemaname = current_schema()';
			break;

		default:
			$sql = 'SELECT name FROM sqlite_master WHERE type = \'table\' AND name NOT LIKE \'sqlite_%\'';
	}

	$tables = array();

	foreach (extension_flows_rows($spec, $sql) as $row)
	{
		$name = (string) reset($row);

		if ($spec['prefix'] === '' || strpos($name, $spec['prefix']) === 0)
			$tables[] = substr($name, strlen($spec['prefix']));
	}

	sort($tables);

	return $tables;
}


/** Columns and indexes of one table, as the server describes them. */
function extension_flows_table_schema($spec, $table)
{
	$name = $spec['prefix'].$table;

	switch ($spec['backend'])
	{
		case 'mysql':
			$columns = extension_flows_rows($spec, 'SHOW COLUMNS FROM `'.$name.'`');
			// Cardinality moves with the data, not with the schema.
			$indexes = array_map(static fn(array $row): array => array_intersect_key($row, array_flip(array('Key_name', 'Seq_in_index', 'Column_name', 'Non_unique', 'Sub_part', 'Index_type'))),
				extension_flows_rows($spec, 'SHOW INDEX FROM `'.$name.'`'));
			break;

		case 'pgsql':
			$columns = extension_flows_rows($spec, 'SELECT column_name, data_type, character_maximum_length, is_nullable, column_default FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = \''.$name.'\' ORDER BY ordinal_position');
			$indexes = extension_flows_rows($spec, 'SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = \''.$name.'\' ORDER BY indexname');
			break;

		default:
			$columns = extension_flows_rows($spec, 'PRAGMA table_info("'.$name.'")');
			$indexes = extension_flows_rows($spec, 'SELECT name, sql FROM sqlite_master WHERE type = \'index\' AND tbl_name = \''.$name.'\' ORDER BY name');
	}

	return array('columns' => $columns, 'indexes' => $indexes);
}


/** What an uninstall has to put back: schema, config, the forums rows and the extension tables. */
function extension_flows_snapshot($spec)
{
	$snapshot = array('tables' => array(), 'config' => array());

	foreach (extension_flows_tables($spec) as $table)
		$snapshot['tables'][$table] = extension_flows_table_schema($spec, $table);

	foreach (extension_flows_rows($spec, 'SELECT conf_name, conf_value FROM %pconfig ORDER BY conf_name') as $row)
		$snapshot['config'][$row['conf_name']] = $row['conf_value'];

	$snapshot['forums'] = extension_flows_rows($spec, 'SELECT * FROM %pforums ORDER BY id');
	$snapshot['extensions'] = extension_flows_rows($spec, 'SELECT * FROM %pextensions ORDER BY id');
	$snapshot['extension_hooks'] = extension_flows_rows($spec, 'SELECT * FROM %pextension_hooks ORDER BY id, extension_id');

	return $snapshot;
}


/** How $after differs from $before, one line per difference. */
function extension_flows_snapshot_diff($before, $after)
{
	$differences = array();

	foreach (array_diff(array_keys($after['tables']), array_keys($before['tables'])) as $table)
		$differences[] = 'table '.$table.' was left behind';

	foreach (array_diff(array_keys($before['tables']), array_keys($after['tables'])) as $table)
		$differences[] = 'table '.$table.' is gone';

	foreach (array_intersect_key($before['tables'], $after['tables']) as $table => $schema)
		foreach (array('columns', 'indexes') as $part)
			if ($schema[$part] !== $after['tables'][$table][$part])
				$differences[] = 'the '.$part.' of '.$table.' changed: '.json_encode($schema[$part]).' became '.json_encode($after['tables'][$table][$part]);

	foreach ($after['config'] as $name => $value)
		if (!array_key_exists($name, $before['config']))
			$differences[] = 'config '.$name.' was left behind';
		else if ($before['config'][$name] !== $value)
			$differences[] = 'config '.$name.' is \''.$value.'\', was \''.$before['config'][$name].'\'';

	foreach (array_diff_key($before['config'], $after['config']) as $name => $value)
		$differences[] = 'config '.$name.' is gone';

	foreach (array('forums', 'extensions', 'extension_hooks') as $table)
		if ($before[$table] !== $after[$table])
			$differences[] = 'the rows of '.$table.' changed: '.json_encode($before[$table]).' became '.json_encode($after[$table]);

	return $differences;
}


/** A page view tagged with a request id, and any extra headers. */
function extension_flows_view(&$state, $session, $path, $request_id, $headers = array())
{
	$headers[] = 'X-Punbb-Fixture-Request: '.$request_id;

	return user_flows_request($state, $session, user_flows_resolve($state['base_url'].'/', $path), null, array(200), $headers);
}


/** Relative to extensions/, so the link resolves in the container, on CI and on the host alike. */
function extension_flows_link_target($id)
{
	return '../.dev/tests/fixtures/extensions/'.$id;
}


/** The fixtures, linked into extensions/ the way an unpacked archive would sit there. */
function extension_flows_link_fixtures()
{
	foreach (EXTENSION_FLOWS_IDS as $id)
	{
		$link = INSTALL_MATRIX_ROOT.'extensions/'.$id;

		if (is_link($link))
			unlink($link);
		else if (file_exists($link))
			throw new RuntimeException($link.' exists and is not the fixture link: move it away first');

		if (!symlink(extension_flows_link_target($id), $link))
			throw new RuntimeException('cannot link '.$link);
	}
}


function extension_flows_unlink_fixtures()
{
	foreach (EXTENSION_FLOWS_IDS as $id)
		if (is_link(INSTALL_MATRIX_ROOT.'extensions/'.$id))
			@unlink(INSTALL_MATRIX_ROOT.'extensions/'.$id);
}


/** install_matrix_drop_schema(), plus the table the fixture creates. */
function extension_flows_drop_schema($spec)
{
	install_matrix_drop_schema($spec);

	switch ($spec['backend'])
	{
		case 'mysql':
			$link = install_matrix_mysql($spec);
			mysqli_query($link, 'DROP TABLE IF EXISTS `'.$spec['prefix'].'punbb_fixture_markers`');
			mysqli_close($link);
			break;

		case 'pgsql':
			$link = install_matrix_pgsql($spec);
			@pg_query($link, 'DROP TABLE IF EXISTS "'.$spec['prefix'].'punbb_fixture_markers" CASCADE');
			pg_close($link);
			break;
	}
}


/**
 * Poll index.php until the banner is (or is not) there: config.php is read per
 * request, but a cached compile of the previous one may serve a request or two.
 */
function extension_flows_await_banner(&$state, $present)
{
	for ($attempt = 0; $attempt < 20; $attempt++)
	{
		$index = extension_flows_view($state, 'guest', 'index.php', extension_flows_request_id('await'));

		if ((strpos((string) $index['body'], 'id="punbb-fixture-banner"') !== false) === $present)
			return;

		usleep(250000);
	}

	throw new UserFlowsFailure('the banner never '.($present ? 'came back' : 'went away').' after config.php was rewritten');
}


/**
 * config.php rewritten with a later mtime than it had: opcache revalidates by
 * the second, so two writes within one second would keep serving the first.
 */
function extension_flows_write_config($body)
{
	$config = INSTALL_MATRIX_ROOT.'config.php';
	$previous = (int) filemtime($config);

	file_put_contents($config, $body);
	clearstatcache(true, $config);

	if ((int) filemtime($config) <= $previous)
		touch($config, $previous + 1);
}


function extension_flows_set_hidden($state, $hidden)
{
	extension_flows_rows($state['spec'], 'UPDATE %pforums SET punbb_fixture_hidden = '.($hidden ? 1 : 0).' WHERE id = '.$state['forum_id']);
}


// ---------------------------------------------------------------- the steps --

/** Another post, so the post loop runs its hook more than once. */
function extension_flows_step_reply(&$state)
{
	$posts = count(extension_flows_rows($state['spec'], 'SELECT id FROM %pposts WHERE topic_id = '.$state['topic_id']));
	$form = user_flows_get($state, 'admin', 'post.php?tid='.$state['topic_id']);

	$response = user_flows_submit($state, 'admin', $form, 'name="req_message"', array(
		'form_sent' => '1',
		'req_message' => EXTENSION_FLOWS_REPLY,
		'submit' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	$state['post_ids'] = array_column(extension_flows_rows($state['spec'], 'SELECT id FROM %pposts WHERE topic_id = '.$state['topic_id'].' ORDER BY id'), 'id');

	user_flows_assert(count($state['post_ids']) === $posts + 1, 'the reply was not stored: '.user_flows_summary($response['body']));
}


function extension_flows_step_install(&$state)
{
	$state['before'] = extension_flows_snapshot($state['spec']);

	user_flows_assert(array_intersect(EXTENSION_FLOWS_IDS, array_column($state['before']['extensions'], 'id')) === array(), 'the forum already has a fixture extension installed');

	foreach (EXTENSION_FLOWS_IDS as $id)
	{
		$form = user_flows_get($state, 'admin', 'admin/extensions.php?install='.$id);

		$response = user_flows_submit($state, 'admin', $form, 'name="install_comply"', array('install_comply' => '1'));
		$response = user_flows_confirm($state, 'admin', $response);
		user_flows_follow($state, 'admin', $response);

		$row = extension_flows_rows($state['spec'], 'SELECT disabled, dependencies FROM %pextensions WHERE id = \''.$id.'\'');
		user_flows_assert(count($row) === 1, $id.' was not installed: '.user_flows_summary($response['body']));
		user_flows_assert($row[0]['disabled'] === '0', $id.' was installed disabled');

		$hooks = array_column(extension_flows_rows($state['spec'], 'SELECT id FROM %pextension_hooks WHERE extension_id = \''.$id.'\''), 'id');
		$expected = extension_flows_manifest_points($id);
		sort($hooks);
		sort($expected);

		user_flows_assert($hooks === $expected, $id.' stored the hooks '.implode(', ', $hooks).', its manifest declares '.implode(', ', $expected));
	}

	$dependencies = extension_flows_rows($state['spec'], 'SELECT dependencies FROM %pextensions WHERE id = \'punbb_fixture_dep\'');
	user_flows_assert($dependencies[0]['dependencies'] === '|punbb_fixture|', 'the dependency is stored as \''.$dependencies[0]['dependencies'].'\'');

	$installed = extension_flows_snapshot($state['spec']);

	user_flows_assert(isset($installed['tables']['punbb_fixture_markers']), '<install> did not create punbb_fixture_markers');
	user_flows_assert(in_array('punbb_fixture_hidden', extension_flows_column_names($state['spec'], $installed['tables']['forums']['columns']), true),
		'<install> did not add forums.punbb_fixture_hidden');
	user_flows_assert(($installed['config']['o_punbb_fixture_banner'] ?? null) === 'punbb_fixture', '<install> did not add o_punbb_fixture_banner');
}


function extension_flows_column_names($spec, $columns)
{
	$key = array('mysql' => 'Field', 'pgsql' => 'column_name', 'sqlite3' => 'name')[$spec['backend']];

	return array_column($columns, $key);
}


/** Shape 5, and output: a point punbb_fixture offers, the dependent attached to it, the banner in the page. */
function extension_flows_step_index(&$state)
{
	$request_id = extension_flows_request_id('index');
	$index = extension_flows_view($state, 'admin', 'index.php', $request_id);
	$markers = extension_flows_markers($state['spec'], $request_id);

	$expected = array('punbb_fixture_dep:punbb_fixture_banner_pre_output', 'punbb_fixture:in_main_output_start', 'punbb_fixture:in_qr_get_cats_and_forums');
	user_flows_assert(extension_flows_sequence($markers) === $expected,
		'index.php fired '.json_encode(extension_flows_sequence($markers)).', expected '.json_encode($expected));

	$offered = extension_flows_pick($markers, 'punbb_fixture_dep', 'punbb_fixture_banner_pre_output')[0]['seen'];
	user_flows_assert($offered['banner'] === array('punbb_fixture'), 'the dependent saw $banner as '.json_encode($offered['banner']));
	user_flows_assert($offered['dependency'] === 'punbb_fixture', 'the dependent saw $ext_info[\'dependencies\'] as '.json_encode($offered['dependency']));

	$banner = extension_flows_pick($markers, 'punbb_fixture', 'in_main_output_start')[0]['seen'];
	user_flows_assert($banner['parts'] === array('punbb_fixture', 'punbb_fixture_dep'), 'the dependent\'s write to $banner did not reach the caller: '.json_encode($banner['parts']));
	user_flows_assert($banner['ext_info_id'] === 'punbb_fixture', 'after the nested point $ext_info names \''.$banner['ext_info_id'].'\', not the outer extension');
	user_flows_assert($banner['url'] === $state['base_url'].'/extensions/punbb_fixture', '$ext_info[\'url\'] is '.$banner['url']);

	$body = (string) $index['body'];
	$html = '<p id="punbb-fixture-banner">punbb_fixture punbb_fixture_dep</p>';
	$at = strpos($body, $html);

	$main = strpos($body, '<div id="brd-main"');
	$forum = strpos($body, 'id="forum'.$state['forum_id'].'"');

	user_flows_assert($at !== false && substr_count($body, $html) === 1, 'index.php does not carry the banner exactly once: '.user_flows_summary($body));
	user_flows_assert($main !== false && $forum !== false && $at > $main && $at < $forum,
		'the banner did not land in the main section ahead of the forum list');
}


/** Shapes 1 and 4: caller locals read, and one echo per post inside that post. */
function extension_flows_step_viewtopic(&$state)
{
	$request_id = extension_flows_request_id('viewtopic');
	$topic = extension_flows_view($state, 'admin', 'viewtopic.php?id='.$state['topic_id'], $request_id);
	$markers = extension_flows_markers($state['spec'], $request_id);

	$expected = array('punbb_fixture:vt_modify_topic_info');
	foreach ($state['post_ids'] as $post_id)
		$expected[] = 'punbb_fixture:vt_row_new_post_entry_data';

	user_flows_assert(extension_flows_sequence($markers) === $expected,
		'viewtopic.php fired '.json_encode(extension_flows_sequence($markers)).', expected '.json_encode($expected));

	$row = extension_flows_rows($state['spec'], 'SELECT forum_id, subject FROM %ptopics WHERE id = '.$state['topic_id'])[0];
	$seen = extension_flows_pick($markers, 'punbb_fixture', 'vt_modify_topic_info')[0]['seen'];

	user_flows_assert((string) $seen['id'] === (string) $state['topic_id'], 'the hook saw $id as '.json_encode($seen['id']));
	user_flows_assert((string) $seen['forum_id'] === $row['forum_id'] && $seen['subject'] === $row['subject'],
		'the hook saw $cur_topic as '.json_encode($seen).', the topic is '.json_encode($row));

	$entries = extension_flows_pick($markers, 'punbb_fixture', 'vt_row_new_post_entry_data');

	user_flows_assert(array_map(static fn(array $marker): string => (string) $marker['seen']['post_id'], $entries) === $state['post_ids'],
		'the post loop hook saw the posts '.json_encode(array_column(array_column($entries, 'seen'), 'post_id')));

	foreach ($entries as $entry)
		user_flows_assert($entry['seen']['ob_level'] >= 2, 'the post loop hook ran at output buffer level '.$entry['seen']['ob_level'].', outside the captured section');

	foreach (extension_flows_echo_offsets($topic['body'], $state['post_ids']) as $post_id => $offset)
		user_flows_assert($offset !== -1, 'the echo for post '.$post_id.' did not land inside that post');
}


/** Shape 2: $query narrowed by name at in_qr_get_cats_and_forums, and index.php runs it. */
function extension_flows_step_by_reference(&$state)
{
	extension_flows_set_hidden($state, true);

	$request_id = extension_flows_request_id('by-reference');
	$index = extension_flows_view($state, 'admin', 'index.php', $request_id);
	$markers = extension_flows_pick(extension_flows_markers($state['spec'], $request_id), 'punbb_fixture', 'in_qr_get_cats_and_forums');

	extension_flows_set_hidden($state, false);

	user_flows_assert(count($markers) === 1 && str_ends_with((string) $markers[0]['seen']['where'], ' AND f.punbb_fixture_hidden=0'),
		'the query hook did not narrow $query: '.json_encode($markers));
	user_flows_assert(strpos((string) $index['body'], 'id="forum'.$state['forum_id'].'"') === false,
		'index.php still lists the hidden forum, so the narrowed $query did not reach the caller');

	$index = extension_flows_view($state, 'admin', 'index.php', extension_flows_request_id('by-reference-visible'));
	user_flows_assert(strpos((string) $index['body'], 'id="forum'.$state['forum_id'].'"') !== false,
		'index.php does not list the forum once it is visible again');
}


/** Shape 3: a returned value leaves get_remote_address(), and the later extension does not run. */
function extension_flows_step_short_circuit(&$state)
{
	$request_id = extension_flows_request_id('short-circuit');
	extension_flows_view($state, 'guest', 'index.php', $request_id, array('X-Punbb-Fixture-Address: '.EXTENSION_FLOWS_ADDRESS));
	$markers = extension_flows_markers($state['spec'], $request_id);

	$returned = extension_flows_pick($markers, 'punbb_fixture', 'fn_get_remote_address_start');

	user_flows_assert($returned !== array(), 'the short-circuit hook never fired');
	user_flows_assert(array_unique(array_column(array_column($returned, 'seen'), 'returned')) === array(EXTENSION_FLOWS_ADDRESS), 'the hook returned '.json_encode($returned));
	user_flows_assert(extension_flows_pick($markers, 'punbb_fixture_dep', 'fn_get_remote_address_start') === array(),
		'the dependent ran at fn_get_remote_address_start after punbb_fixture returned');

	$online = extension_flows_rows($state['spec'], 'SELECT user_id FROM %ponline WHERE ident = \''.EXTENSION_FLOWS_ADDRESS.'\'');
	user_flows_assert(count($online) === 1 && $online[0]['user_id'] === '1', 'the guest is not online as '.EXTENSION_FLOWS_ADDRESS.', so get_remote_address() did not return the hook\'s value');

	// Without the header the fixture returns nothing and the dependent runs.
	$request_id = extension_flows_request_id('no-short-circuit');
	extension_flows_view($state, 'guest', 'index.php', $request_id);
	$markers = extension_flows_markers($state['spec'], $request_id);

	user_flows_assert(extension_flows_pick($markers, 'punbb_fixture_dep', 'fn_get_remote_address_start') !== array(),
		'the dependent did not run at fn_get_remote_address_start when nothing returned before it');
	user_flows_assert(extension_flows_pick($markers, 'punbb_fixture', 'fn_get_remote_address_start') === array(),
		'punbb_fixture marked a short-circuit without the address header');
}


function extension_flows_step_disable_hooks(&$state)
{
	$body = (string) file_get_contents(INSTALL_MATRIX_ROOT.'config.php');
	$disabled = extension_flows_disable_hooks($body);

	user_flows_assert($disabled !== '', 'config.php carries no commented-out FORUM_DISABLE_HOOKS line');

	extension_flows_set_hidden($state, true);
	extension_flows_write_config($disabled);

	try
	{
		extension_flows_await_banner($state, false);

		$count = static fn(): string => extension_flows_rows($state['spec'], 'SELECT COUNT(*) AS n FROM %ppunbb_fixture_markers')[0]['n'];
		$before = $count();

		$index = extension_flows_view($state, 'guest', 'index.php', extension_flows_request_id('disabled-index'),
			array('X-Punbb-Fixture-Address: '.EXTENSION_FLOWS_DISABLED_ADDRESS));
		$topic = extension_flows_view($state, 'admin', 'viewtopic.php?id='.$state['topic_id'], extension_flows_request_id('disabled-viewtopic'));

		user_flows_assert($count() === $before, 'a hook wrote a marker while FORUM_DISABLE_HOOKS was defined');
		user_flows_assert(strpos((string) $index['body'], 'id="forum'.$state['forum_id'].'"') !== false, 'the query hook still narrowed index.php');
		user_flows_assert(strpos((string) $topic['body'], 'punbb-fixture-echo') === false, 'the post loop hook still echoed');
		user_flows_assert(extension_flows_rows($state['spec'], 'SELECT user_id FROM %ponline WHERE ident = \''.EXTENSION_FLOWS_DISABLED_ADDRESS.'\'') === array(),
			'get_remote_address() still returned the hook\'s value');
	}
	finally
	{
		extension_flows_write_config($body);
		extension_flows_set_hidden($state, false);
	}

	extension_flows_await_banner($state, true);
}


function extension_flows_step_uninstall(&$state)
{
	foreach (array_reverse(EXTENSION_FLOWS_IDS) as $id)
	{
		$form = user_flows_get($state, 'admin', 'admin/extensions.php?section=manage&uninstall='.$id);

		$response = user_flows_submit($state, 'admin', $form, 'name="uninstall_comply"', array('uninstall_comply' => '1'));
		$response = user_flows_confirm($state, 'admin', $response);
		user_flows_follow($state, 'admin', $response);

		user_flows_assert(extension_flows_rows($state['spec'], 'SELECT id FROM %pextensions WHERE id = \''.$id.'\'') === array(),
			$id.' was not uninstalled: '.user_flows_summary($response['body']));
	}

	$after = extension_flows_snapshot($state['spec']);

	user_flows_assert(!isset($after['tables']['punbb_fixture_markers']), 'punbb_fixture_markers and its rows survived the uninstall');

	$differences = extension_flows_snapshot_diff($state['before'], $after);
	user_flows_assert($differences === array(), 'the uninstall did not put the forum back: '.implode('; ', $differences));

	// A hook left in the cache would reach for the dropped table and fail the page.
	$index = extension_flows_view($state, 'admin', 'index.php', extension_flows_request_id('uninstalled'));
	user_flows_assert(strpos((string) $index['body'], 'punbb-fixture-banner') === false, 'index.php still carries the banner after the uninstall');
}


function extension_flows_steps()
{
	return array(
		array('reply to the topic', 'extension_flows_step_reply'),
		array('install both fixtures', 'extension_flows_step_install'),
		array('index: offered point, output', 'extension_flows_step_index'),
		array('viewtopic: locals, post loop', 'extension_flows_step_viewtopic'),
		array('by reference: narrowed query', 'extension_flows_step_by_reference'),
		array('short-circuit with return', 'extension_flows_step_short_circuit'),
		array('FORUM_DISABLE_HOOKS', 'extension_flows_step_disable_hooks'),
		array('uninstall both fixtures', 'extension_flows_step_uninstall'),
	);
}


/** A walk's state over the forum on $spec, with a jar each for its administrator and a guest. */
function extension_flows_state($base_url, $spec)
{
	return array(
		'base_url' => $base_url,
		'spec' => $spec,
		'jars' => array(
			'admin' => (string) tempnam(sys_get_temp_dir(), 'extfa'),
			'guest' => (string) tempnam(sys_get_temp_dir(), 'extfg'),
		),
		'diagnostics' => array(),
		// The topic and forum a fresh install creates, and the upgrade fixtures carry.
		'topic_id' => 1,
		'forum_id' => 1,
		'post_ids' => array(),
		'before' => array(),
	);
}


/**
 * The steps over the forum $state names, its administrator signed in on the
 * admin jar, with the fixtures linked for the length of the walk. Returns the
 * failures; diagnostics stay in $state.
 */
function extension_flows_walk(&$state)
{
	extension_flows_link_fixtures();

	try
	{
		$failures = array();
		$steps = extension_flows_steps();
		$width = max(array_map(static fn(array $step): int => strlen($step[0]), $steps)) + 2;

		foreach ($steps as list($label, $run))
		{
			if ($failures)
			{
				printf("   skip  %-{$width}s\n", $label);
				continue;
			}

			try
			{
				$run($state);
				printf("   ok    %-{$width}s\n", $label);
			}
			catch (Throwable $e)
			{
				$failures[] = $label.': '.($e instanceof UserFlowsFailure ? $e->getMessage() : get_class($e).': '.$e->getMessage());
				printf("   FAIL  %-{$width}s  %s\n", $label, end($failures));
			}
		}

		return $failures;
	}
	finally
	{
		extension_flows_unlink_fixtures();
	}
}


/** One driver end to end. Returns the list of failures, empty when it passed. */
function extension_flows_run_driver($db_type, $spec, $base_url, $log)
{
	$diagnostics = array();

	@unlink(INSTALL_MATRIX_ROOT.'config.php');
	install_matrix_clear_cache();
	extension_flows_drop_schema($spec);
	install_matrix_truncate_log($log);

	$state = extension_flows_state($base_url, $spec);

	try
	{
		$response = smoke_request($base_url.'/admin/install.php', $state['jars']['admin'], install_matrix_form_fields($db_type, $spec, $base_url));
		$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));

		if ($response['status'] !== 200 || !install_matrix_install_succeeded($response['body']))
			return array_merge(array('the forum did not install: HTTP '.$response['status'].', '.install_matrix_failure_reason($response['body'])), $diagnostics);

		$reason = install_matrix_login($base_url, $state['jars']['admin'], $diagnostics);

		if ($reason !== '')
			return array_merge(array('the administrator could not log in: '.$reason), $diagnostics);

		$failures = extension_flows_walk($state);

		return array_merge($failures, array_values(array_unique(array_merge($diagnostics, $state['diagnostics'], install_matrix_log_diagnostics($log)))));
	}
	finally
	{
		foreach ($state['jars'] as $jar)
			@unlink($jar);

		@unlink(INSTALL_MATRIX_ROOT.'config.php');
		extension_flows_drop_schema($spec);
		install_matrix_clear_cache();
	}
}


function extension_flows_main($base_url, $requested, $log)
{
	$drivers = extension_flows_drivers();
	$unknown = array_diff($requested, array_keys($drivers));

	if ($unknown)
	{
		fwrite(STDERR, 'unknown driver(s): '.implode(', ', $unknown)."\n");
		return 2;
	}

	if ($requested)
		$drivers = array_intersect_key($drivers, array_flip($requested));

	echo 'extension flows on '.$base_url.' (PHP '.PHP_VERSION.")\n\n";

	$failures = array();

	foreach ($drivers as $db_type => $spec)
	{
		echo '== '.$db_type." ==\n";

		try
		{
			$driver_failures = extension_flows_run_driver($db_type, $spec, $base_url, $log);
		}
		catch (Throwable $e)
		{
			$driver_failures = array(get_class($e).': '.$e->getMessage());
		}

		foreach ($driver_failures as $failure)
			$failures[] = $db_type.': '.$failure;

		echo "\n";
	}

	if ($failures)
	{
		echo count($failures)." failure(s):\n";
		foreach ($failures as $failure)
			echo '  - '.$failure."\n";

		return 1;
	}

	echo count($drivers).' driver(s) passed '.count(extension_flows_steps())." step(s) each\n";

	return 0;
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
{
	install_matrix_stash_config();
	register_shutdown_function('extension_flows_unlink_fixtures');

	exit(extension_flows_main(
		rtrim(getenv('PUNBB_TEST_BASE_URL') ?: 'http://localhost', '/'),
		array_slice($argv, 1),
		(string) getenv('PUNBB_TEST_ERROR_LOG')
	));
}
