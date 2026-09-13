<?php
/**
 * Extension corpus runner.
 *
 * Points the extension compatibility pass at a directory of real extensions.
 * Per driver: installs a forum of its own over HTTP, copies every extension of
 * PUNBB_TEST_EXTENSIONS_DIR into extensions/, installs them in dependency order
 * through admin/extensions.php, walks the forum's pages, uninstalls them in
 * reverse and prints a report per extension: whether the maxtestedon gate
 * refuses it, whether it installed, which of its hook points fired, which
 * points it attaches to that the tree does not offer, every PHP diagnostic its
 * code raised, whether it uninstalled.
 *
 * The report is the product: a failing extension is reported and skipped, and
 * the exit status is non-zero only when no report could be produced. With
 * PUNBB_TEST_EXTENSIONS_DIR unset it skips and exits 0.
 *
 * Which hooks fired, and whose code raised a diagnostic, comes from
 * extension_corpus_probe.php: after an extension installs, each of its stored
 * hook bodies is prefixed with a call into it. The install itself runs on the
 * manifest as shipped, with FORUM_DISABLE_EXTENSIONS_VERSION_CHECK and
 * FORUM_DEBUG switched on once the gate has been asked.
 *
 * Corpus code runs against the test database: a query that ignores the prefix
 * reaches unprefixed tables there. Run only extensions you would install.
 *
 * Run it from inside the web container, like the other integration runs:
 *
 *   PUNBB_TEST_EXTENSIONS_DIR=/path/to/extensions php .dev/tests/Integration/extension_corpus.php [driver ...]
 *
 * The driver defaults to mysqli, which 1.4-era extensions were written for.
 * Environment: PUNBB_TEST_BASE_URL, PUNBB_TEST_MYSQL_* and PUNBB_TEST_PGSQL_*,
 * as for extension_flows.php.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// The fixture pass owns the database readers and the config.php rewrite; the
// functional pass and the install matrix under it own the form driving.
require_once __DIR__.'/extension_flows.php';
require_once __DIR__.'/extension_corpus_probe.php';

define('EXTENSION_CORPUS_SQLITE', '.dev/tmp/matrix/extension_corpus.sqlite3');

const EXTENSION_CORPUS_DRIVER = 'mysqli';

// Markup the parser hooks see, on a post the walk writes and then views.
const EXTENSION_CORPUS_REPLY = "[b]Corpus reply[/b] [i]with[/i] [url=http://example.com/]a link[/url]\n\n[quote]a quote[/quote]\n\n[code]some code[/code] :)";

// The forum's error page, and the boilerplate on it that says nothing about the error.
const EXTENSION_CORPUS_ERROR_PAGE = 'Sorry! The page could not be loaded.';
const EXTENSION_CORPUS_ERROR_DESCRIPTION = 'This is probably a temporary error. Just refresh the page and retry. If problem continues, please check back in 5-10 minutes.';

// Any status is an answer: what went wrong is read off the page.
const EXTENSION_CORPUS_STATUSES = array(200, 302, 400, 403, 404, 500, 503);

// admin/extensions.php offers aex_add_repository_for_<id>, built at runtime and so absent from the inventory.
const EXTENSION_CORPUS_DYNAMIC_POINT = 'aex_add_repository_for_';


/** install_matrix_drivers() with prefixes and a SQLite file of this run's own. */
function extension_corpus_drivers()
{
	$prefixes = array('mysqli' => 'ec1_', 'mysqli_innodb' => 'ec2_', 'pgsql' => 'ec3_', 'sqlite3' => '');
	$drivers = array();

	foreach (install_matrix_drivers() as $db_type => $spec)
		$drivers[$db_type] = array_merge($spec, array('prefix' => $prefixes[$db_type]));

	$drivers['sqlite3']['name'] = EXTENSION_CORPUS_SQLITE;

	return $drivers;
}


/**
 * What the runner needs from one manifest. The install parses it with the
 * forum's own parser; this reading only orders and labels, so a manifest it
 * cannot parse is still offered to the install.
 */
function extension_corpus_manifest($dir)
{
	$manifest = array('id' => basename($dir), 'version' => '', 'maxtestedon' => '', 'dependencies' => array(), 'points' => array(), 'error' => '');

	$previous = libxml_use_internal_errors(true);
	$xml = simplexml_load_string((string) @file_get_contents($dir.'/manifest.xml'), 'SimpleXMLElement', LIBXML_NOCDATA);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	if ($xml === false)
	{
		$manifest['error'] = 'manifest.xml does not parse';
		return $manifest;
	}

	$manifest['version'] = trim((string) $xml->version);
	$manifest['maxtestedon'] = trim((string) $xml->maxtestedon);

	foreach ((array) $xml->xpath('/extension/dependencies/dependency') as $dependency)
		$manifest['dependencies'][] = trim((string) $dependency);

	foreach ((array) $xml->xpath('/extension/hooks/hook') as $hook)
		foreach (explode(',', (string) $hook['id']) as $point)
			if (trim($point) !== '' && !in_array(trim($point), $manifest['points'], true))
				$manifest['points'][] = trim($point);

	return $manifest;
}


/** Every folder of $corpus carrying a manifest.xml, by id. */
function extension_corpus_discover($corpus)
{
	$manifests = array();

	foreach ((array) glob(rtrim($corpus, '/').'/*/manifest.xml') as $file)
	{
		$manifest = extension_corpus_manifest(dirname((string) $file));
		$manifests[$manifest['id']] = $manifest;
	}

	ksort($manifests, SORT_STRING);

	return $manifests;
}


/** Why the corpus cannot be run, or '' when it can. */
function extension_corpus_unusable($corpus)
{
	if ($corpus === '')
		return 'PUNBB_TEST_EXTENSIONS_DIR is unset: no corpus to run';

	if (!is_dir($corpus))
		return $corpus.' is not a directory';

	if (extension_corpus_discover($corpus) === array())
		return $corpus.' holds no folder with a manifest.xml';

	return '';
}


/**
 * Install order: each extension after the dependencies the corpus carries, ties
 * by id. A dependency the corpus lacks holds nothing back — the install refuses
 * and the report says so. A cycle is broken at its first id.
 */
function extension_corpus_install_order($manifests)
{
	ksort($manifests, SORT_STRING);

	$order = array();

	while ($manifests)
	{
		$pending = array_map('strval', array_keys($manifests));
		$next = $pending[0];

		foreach ($manifests as $id => $manifest)
			if (array_diff(array_intersect($manifest['dependencies'], $pending), array((string) $id)) === array())
			{
				$next = (string) $id;
				break;
			}

		$order[] = $next;
		unset($manifests[$next]);
	}

	return $order;
}


/** A point the tree offers: listed in the inventory, or the dynamic family admin/extensions.php builds per installed id ([0-9a-z_]+). */
function extension_corpus_in_tree($tree, $point)
{
	return isset($tree[$point]) || preg_match('/^'.preg_quote(EXTENSION_CORPUS_DYNAMIC_POINT, '/').'[0-9a-z_]+$/D', $point) === 1;
}


/** The points the tree offers: every inventory line without a removal note. */
function extension_corpus_tree_points()
{
	$points = array();

	foreach ((array) file(INSTALL_MATRIX_ROOT.'.dev/tests/fixtures/hook_points.txt', FILE_IGNORE_NEW_LINES) as $line)
		if ($line !== '' && $line[0] !== '#' && strpos($line, ' -- ') === false)
			$points[$line] = true;

	return $points;
}


/** point => the extensions of the corpus that offer it from their own code. */
function extension_corpus_offered_points($corpus, $ids)
{
	$offered = array();

	foreach ($ids as $id)
	{
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(rtrim($corpus, '/').'/'.$id, FilesystemIterator::SKIP_DOTS));

		foreach ($files as $file)
		{
			if (!in_array(strtolower($file->getExtension()), array('php', 'xml'), true))
				continue;

			preg_match_all('/get_hook\(\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\)/', (string) file_get_contents($file->getPathname()), $matches);

			foreach ($matches[1] as $point)
				if (!in_array($id, $offered[$point] ?? array(), true))
					$offered[$point][] = $id;
		}
	}

	return $offered;
}


/** config.php with the installer's commented-out define of $constant switched on, or '' when it has none. */
function extension_corpus_switch_on($config_body, $constant)
{
	$commented = "//define('".$constant."', 1);";

	if (strpos((string) $config_body, $commented) === false)
		return '';

	return str_replace($commented, substr($commented, 2), (string) $config_body);
}


/** $value as a string literal for the backend. */
function extension_corpus_sql_string($spec, $value)
{
	if ($spec['backend'] === 'mysql')
		return '\''.str_replace(array('\\', '\''), array('\\\\', '\\\''), (string) $value).'\'';

	return '\''.str_replace('\'', '\'\'', (string) $value).'\'';
}


/**
 * Every stored hook body of $id starts with the probe from here on. The rows
 * change, not the cache, so a rebuild keeps the probe; the cache is deleted and
 * the next request rebuilds it from the rows.
 */
function extension_corpus_instrument($spec, $id)
{
	$where = 'extension_id = '.extension_corpus_sql_string($spec, $id);

	foreach (extension_flows_rows($spec, 'SELECT id FROM %pextension_hooks WHERE '.$where) as $row)
	{
		$prefix = extension_corpus_sql_string($spec, extension_corpus_probe_prefix($id, $row['id']));
		$code = $spec['backend'] === 'mysql' ? 'CONCAT('.$prefix.', code)' : $prefix.' || code';

		extension_flows_rows($spec, 'UPDATE %pextension_hooks SET code = '.$code.' WHERE '.$where.' AND id = '.extension_corpus_sql_string($spec, $row['id']));
	}

	@unlink(INSTALL_MATRIX_ROOT.'cache/cache_hooks.php');
}


/** The probe's records from byte $offset of its log on. */
function extension_corpus_read_log($offset)
{
	$records = array();

	foreach (explode("\n", (string) @file_get_contents(extension_corpus_probe_log(), false, null, $offset)) as $line)
		if (is_array($record = json_decode($line, true)) && isset($record['extension']))
			$records[] = $record;

	return $records;
}


function extension_corpus_reset_log()
{
	$log = extension_corpus_probe_log();

	// The web server appends to it, and may not be the user the run is.
	@mkdir(dirname($log), 0777, true);
	@chmod(dirname($log), 0777);
	file_put_contents($log, '');
	@chmod($log, 0666);
}


/**
 * One step's diagnostics as [extension, text] pairs: what the probe logged, then
 * each diagnostic read off a page that the probe did not log — named by its
 * file under extensions/, else by $owner. '' is forum code.
 */
function extension_corpus_attribute($records, $page_diagnostics, $owner, $extensions_dir)
{
	$pairs = array();
	$seen = array();

	foreach ($records as $record)
	{
		if (!isset($record['text']))
			continue;

		$key = (string) preg_replace('/\s+/', ' ', trim((string) $record['text']));

		if (!isset($seen[$key]))
			$pairs[] = array((string) $record['extension'], (string) $record['text']);

		$seen[$key] = true;
	}

	foreach ($page_diagnostics as $text)
	{
		$text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$key = (string) preg_replace('/\s+/', ' ', trim($text));

		if (isset($seen[$key]))
			continue;

		$seen[$key] = true;
		$in_extension = strpos($text, 'eval()\'d code') === false && preg_match('#'.preg_quote($extensions_dir, '#').'([0-9a-z_]+)/#', $text, $match);

		$pairs[] = array($in_extension ? $match[1] : (string) $owner, $text);
	}

	return $pairs;
}


/**
 * Runs one step and files what it produced under $run: the points that fired,
 * the diagnostics by extension. Returns the failure, '' when it went through.
 */
function extension_corpus_step(&$run, $label, $owner, $action)
{
	clearstatcache(true, extension_corpus_probe_log());
	$offset = (int) @filesize(extension_corpus_probe_log());
	$run['state']['diagnostics'] = array();
	$failure = '';

	try
	{
		$action();
	}
	catch (Throwable $e)
	{
		$failure = $e instanceof UserFlowsFailure ? $e->getMessage() : get_class($e).': '.$e->getMessage();
	}

	$records = extension_corpus_read_log($offset);

	foreach ($records as $record)
		if (isset($record['point']))
			$run['fired'][(string) $record['extension']][(string) $record['point']] = true;

	foreach (extension_corpus_attribute($records, $run['state']['diagnostics'], $owner, INSTALL_MATRIX_ROOT.'extensions/') as list($extension, $text))
		$run['diagnostics'][$extension][$text][$label] = true;

	return $failure;
}


/** What admin/extensions.php answers when the maxtestedon gate refuses. */
function extension_corpus_gate_message()
{
	require INSTALL_MATRIX_ROOT.'lang/English/admin_ext.php';

	return $lang_admin_ext['Maxtestedon error'];
}


/** 'refused', 'passes', or '-' when the page never got as far as the gate. */
function extension_corpus_gate_verdict($body, $id, $gate_message)
{
	if (strpos((string) $body, $gate_message) !== false)
		return 'refused';

	return strpos((string) $body, '/admin/extensions.php?install='.$id.'"') !== false ? 'passes' : '-';
}


/**
 * The walk, in order: session, page, and for a form the markup it carries and
 * what is filled in. Ids match a fresh install: forum 1, topic 1, post 1, the
 * administrator is user 2.
 */
function extension_corpus_walk()
{
	$admin = array(
		'index.php', 'viewforum.php?id=1', 'viewtopic.php?id=1', 'viewtopic.php?pid=1', 'post.php?fid=1',
		'edit.php?id=1', 'search.php', 'search.php?action=show_recent', 'userlist.php', 'help.php?section=bbcode',
		'profile.php?id=2', 'profile.php?section=identity&id=2', 'profile.php?section=settings&id=2',
		'profile.php?section=signature&id=2', 'profile.php?section=avatar&id=2', 'profile.php?section=admin&id=2',
		'moderate.php?fid=1', 'moderate.php?fid=1&tid=1', 'misc.php?action=rules', 'extern.php?action=feed&type=rss',
		'admin/index.php', 'admin/settings.php?section=setup', 'admin/settings.php?section=registration',
		'admin/forums.php', 'admin/forums.php?edit_forum=1', 'admin/categories.php', 'admin/groups.php',
		'admin/users.php', 'admin/bans.php', 'admin/censoring.php', 'admin/ranks.php', 'admin/prune.php',
		'admin/reindex.php', 'admin/reports.php', 'admin/extensions.php?section=manage',
	);

	$guest = array(
		'index.php', 'viewforum.php?id=1', 'viewtopic.php?id=1', 'login.php', 'login.php?action=forget',
		'register.php', 'search.php', 'userlist.php', 'misc.php?action=rules',
	);

	$walk = array(
		array('admin', 'post.php?tid=1', 'name="req_message"', array('form_sent' => '1', 'req_message' => EXTENSION_CORPUS_REPLY, 'submit' => '1')),
		array('admin', 'admin/settings.php?section=features', 'name="form_sent"', array('save' => '1')),
	);

	foreach ($admin as $path)
		$walk[] = array('admin', $path);

	foreach ($guest as $path)
		$walk[] = array('guest', $path);

	return $walk;
}


/** One entry of the walk: the page, or the form on it submitted and followed. */
function extension_corpus_visit(&$state, $entry)
{
	$page = extension_corpus_get($state, $entry[0], $entry[1]);

	if (isset($entry[2]))
		extension_corpus_submit($state, $entry[0], $page, $entry[2], $entry[3]);
}


function extension_corpus_get(&$state, $session, $path)
{
	$page = user_flows_get($state, $session, $path, EXTENSION_CORPUS_STATUSES);
	extension_corpus_assert_page($page);

	return $page;
}


/** The form carrying $needle submitted, a CSRF confirm answered, the redirect followed. */
function extension_corpus_submit(&$state, $session, $page, $needle, $fields)
{
	$page = user_flows_submit($state, $session, $page, $needle, $fields, EXTENSION_CORPUS_STATUSES);

	if (strpos((string) $page['body'], 'name="prev_url"') !== false)
		$page = user_flows_submit($state, $session, $page, 'name="prev_url"', array(), EXTENSION_CORPUS_STATUSES);

	extension_corpus_assert_page($page);

	$target = user_flows_redirect_target($page['body']);

	if ($target === '')
		$target = (string) ($page['headers']['location'] ?? '');

	if ($target === '')
		return $page;

	$page = user_flows_request($state, $session, user_flows_resolve($page['url'], $target), null, EXTENSION_CORPUS_STATUSES);
	extension_corpus_assert_page($page);

	return $page;
}


/** A server error or the forum's error page fails the step, with everything that page reports. */
function extension_corpus_assert_page($page)
{
	$body = (string) $page['body'];

	if ($page['status'] < 500 && strpos($body, EXTENSION_CORPUS_ERROR_PAGE) === false)
		return;

	$reported = array();

	if (preg_match_all('#<p\b[^>]*>(.*?)</p>#is', $body, $matches))
		foreach ($matches[1] as $paragraph)
			$reported[] = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

	$reported = implode(' ', array_diff($reported, array(EXTENSION_CORPUS_ERROR_DESCRIPTION, '')));

	throw new UserFlowsFailure('HTTP '.$page['status'].': '.($reported !== '' ? $reported : user_flows_summary($body)));
}


function extension_corpus_installed($spec, $id)
{
	return extension_flows_rows($spec, 'SELECT id FROM %pextensions WHERE id = '.extension_corpus_sql_string($spec, $id)) !== array();
}


function extension_corpus_install(&$state, $spec, $id)
{
	$page = extension_corpus_get($state, 'admin', 'admin/extensions.php?install='.$id);

	user_flows_assert(strpos((string) $page['body'], 'name="install_comply"') !== false, user_flows_summary($page['body']));

	$page = extension_corpus_submit($state, 'admin', $page, 'name="install_comply"', array('install_comply' => '1'));

	user_flows_assert(extension_corpus_installed($spec, $id), user_flows_summary($page['body']));
}


function extension_corpus_uninstall(&$state, $spec, $id)
{
	$page = extension_corpus_get($state, 'admin', 'admin/extensions.php?section=manage&uninstall='.$id);

	user_flows_assert(strpos((string) $page['body'], 'name="uninstall_comply"') !== false, user_flows_summary($page['body']));

	$page = extension_corpus_submit($state, 'admin', $page, 'name="uninstall_comply"', array('uninstall_comply' => '1'));

	user_flows_assert(!extension_corpus_installed($spec, $id), user_flows_summary($page['body']));
}


/** The corpus copied into extensions/, as unpacked archives sit there: __FILE__ must resolve inside the forum. */
function extension_corpus_place($corpus, $ids)
{
	foreach ($ids as $id)
	{
		$target = INSTALL_MATRIX_ROOT.'extensions/'.$id;

		if (file_exists($target) || is_link($target))
			throw new RuntimeException($target.' already exists: move it away first');

		$GLOBALS['extension_corpus_placed'][] = $id;
		extension_corpus_copy_tree(rtrim($corpus, '/').'/'.$id, $target);
	}
}


function extension_corpus_remove_placed()
{
	foreach ($GLOBALS['extension_corpus_placed'] ?? array() as $id)
		extension_corpus_remove_tree(INSTALL_MATRIX_ROOT.'extensions/'.$id);

	$GLOBALS['extension_corpus_placed'] = array();
}


function extension_corpus_copy_tree($from, $to)
{
	if (!is_dir($from))
	{
		copy($from, $to);
		return;
	}

	mkdir($to, 0777);

	foreach (scandir($from) as $entry)
		if ($entry !== '.' && $entry !== '..')
			extension_corpus_copy_tree($from.'/'.$entry, $to.'/'.$entry);
}


function extension_corpus_remove_tree($path)
{
	if (is_link($path) || is_file($path))
	{
		unlink($path);
		return;
	}

	if (!is_dir($path))
		return;

	foreach (scandir($path) as $entry)
		if ($entry !== '.' && $entry !== '..')
			extension_corpus_remove_tree($path.'/'.$entry);

	rmdir($path);
}


/** Every table of the run's database, whatever its prefix. SQLite has a file of its own and answers none. */
function extension_corpus_database_tables($spec)
{
	if ($spec['backend'] === 'sqlite3')
		return array();

	return extension_flows_tables(array_merge($spec, array('prefix' => '')));
}


/**
 * The run's schema: every table of its prefix, and every table outside it that
 * is not in $before, which an extension created. Pass the tables present now to
 * drop the prefix alone.
 */
function extension_corpus_drop_tables($spec, $before)
{
	if ($spec['backend'] !== 'sqlite3' && $spec['prefix'] === '')
		throw new RuntimeException('a shared database needs a table prefix of the run\'s own');

	install_matrix_drop_schema($spec);

	foreach (extension_corpus_database_tables($spec) as $table)
	{
		if (strpos($table, $spec['prefix']) !== 0 && in_array($table, $before, true))
			continue;

		if ($spec['backend'] === 'mysql')
			extension_flows_rows($spec, 'DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
		else
			extension_flows_rows($spec, 'DROP TABLE IF EXISTS "'.str_replace('"', '""', $table).'" CASCADE');
	}
}


/** Poll until config.php has taken: a cached compile of the previous one may serve a request or two. */
function extension_corpus_await_gate_off(&$run, $refused)
{
	if ($refused === array())
		return;

	for ($attempt = 0; $attempt < 20; $attempt++)
	{
		$page = user_flows_get($run['state'], 'admin', 'admin/extensions.php?install='.$refused[0], EXTENSION_CORPUS_STATUSES);

		if (extension_corpus_gate_verdict($page['body'], $refused[0], $run['gate_message']) !== 'refused')
			return;

		usleep(250000);
	}

	throw new RuntimeException('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK never took effect after config.php was rewritten');
}


/** One driver: a forum, the corpus through it, and what the report is made of. */
function extension_corpus_run_driver($db_type, $spec, $base_url, $corpus)
{
	$manifests = extension_corpus_discover($corpus);
	$order = extension_corpus_install_order($manifests);

	@unlink(INSTALL_MATRIX_ROOT.'config.php');
	install_matrix_clear_cache();
	extension_corpus_drop_tables($spec, extension_corpus_database_tables($spec));
	$before = extension_corpus_database_tables($spec);
	extension_corpus_reset_log();

	$run = array(
		'spec' => $spec,
		'state' => array(
			'base_url' => $base_url,
			'jars' => array(
				'admin' => (string) tempnam(sys_get_temp_dir(), 'corpa'),
				'guest' => (string) tempnam(sys_get_temp_dir(), 'corpg'),
			),
			'diagnostics' => array(),
		),
		'gate_message' => extension_corpus_gate_message(),
		'manifests' => $manifests,
		'order' => $order,
		'tree' => extension_corpus_tree_points(),
		'offered' => extension_corpus_offered_points($corpus, $order),
		'extensions' => array(),
		'fired' => array(),
		'diagnostics' => array(),
		'pages' => array(),
	);

	try
	{
		$response = smoke_request($base_url.'/admin/install.php', $run['state']['jars']['admin'], install_matrix_form_fields($db_type, $spec, $base_url));

		if ($response['status'] !== 200 || !install_matrix_install_succeeded($response['body']))
			throw new RuntimeException('the forum did not install: HTTP '.$response['status'].', '.install_matrix_failure_reason($response['body']));

		$diagnostics = array();
		$reason = install_matrix_login($base_url, $run['state']['jars']['admin'], $diagnostics);

		if ($reason !== '')
			throw new RuntimeException('the administrator could not log in: '.$reason);

		// Admin pages would ask punbb.informer.com for updates on every visit.
		extension_flows_rows($spec, 'UPDATE %pconfig SET conf_value = \'0\' WHERE conf_name IN (\'o_check_for_updates\', \'o_check_for_versions\')');
		install_matrix_clear_cache();

		extension_corpus_place($corpus, $order);

		$refused = array();

		foreach ($order as $id)
		{
			$verdict = '-';
			extension_corpus_step($run, 'gate '.$id, $id, function () use (&$run, $id, &$verdict) {
				$page = user_flows_get($run['state'], 'admin', 'admin/extensions.php?install='.$id, EXTENSION_CORPUS_STATUSES);
				$verdict = extension_corpus_gate_verdict($page['body'], $id, $run['gate_message']);
			});

			$run['extensions'][$id]['gate'] = $verdict;

			if ($verdict === 'refused')
				$refused[] = $id;
		}

		$config = (string) file_get_contents(INSTALL_MATRIX_ROOT.'config.php');

		foreach (array('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK', 'FORUM_DEBUG') as $constant)
		{
			$switched = extension_corpus_switch_on($config, $constant);

			if ($switched === '')
				throw new RuntimeException('config.php carries no commented-out '.$constant.' line');

			$config = $switched;
		}

		extension_flows_write_config($config);
		extension_corpus_await_gate_off($run, $refused);

		foreach ($order as $id)
		{
			$run['extensions'][$id]['install'] = extension_corpus_step($run, 'install '.$id, $id, function () use (&$run, $id) {
				extension_corpus_install($run['state'], $run['spec'], $id);
			});

			// A failure after the row is written (the redirect page, say) still leaves the hooks live.
			$run['extensions'][$id]['installed'] = extension_corpus_installed($spec, $id);

			if ($run['extensions'][$id]['installed'])
				extension_corpus_instrument($spec, $id);
		}

		foreach (extension_corpus_walk() as $entry)
		{
			$label = $entry[1].(isset($entry[2]) ? ' submitted' : '').' as '.$entry[0];

			$failure = extension_corpus_step($run, $label, '', function () use (&$run, $entry) {
				extension_corpus_visit($run['state'], $entry);
			});

			if ($failure !== '')
				$run['pages'][$label] = $failure;
		}

		foreach (array_reverse($order) as $id)
		{
			if (!$run['extensions'][$id]['installed'])
				continue;

			$run['extensions'][$id]['uninstall'] = extension_corpus_step($run, 'uninstall '.$id, $id, function () use (&$run, $id) {
				extension_corpus_uninstall($run['state'], $run['spec'], $id);
			});
		}

		return $run;
	}
	finally
	{
		foreach ($run['state']['jars'] as $jar)
			@unlink($jar);

		extension_corpus_remove_placed();
		@unlink(INSTALL_MATRIX_ROOT.'config.php');
		extension_corpus_drop_tables($spec, $before);
		install_matrix_clear_cache();
	}
}


/** Up to three step labels, and how many more. */
function extension_corpus_steps_label($steps)
{
	$steps = array_keys($steps);

	return implode(', ', array_slice($steps, 0, 3)).(count($steps) > 3 ? ' and '.(count($steps) - 3).' more' : '');
}


/** The report: one row per extension in install order, then the detail behind each row. */
function extension_corpus_report($run)
{
	$format = '   %-22s %-9s %-8s %-8s %-6s %-12s %-12s %s';
	$lines = array(rtrim(sprintf($format, 'extension', 'version', 'gate', 'install', 'fired', 'not in tree', 'diagnostics', 'uninstall')));
	$details = array();
	$yes_no = static fn($failure): string => $failure === null ? '-' : ($failure === '' ? 'yes' : 'no');

	foreach ($run['order'] as $id)
	{
		$manifest = $run['manifests'][$id];
		$result = $run['extensions'][$id] ?? array();
		$fired = array_keys($run['fired'][$id] ?? array());
		$in_tree = array_values(array_filter($manifest['points'], static fn(string $point): bool => extension_corpus_in_tree($run['tree'], $point)));
		$not_in_tree = array_values(array_diff($manifest['points'], $in_tree));
		$diagnostics = $run['diagnostics'][$id] ?? array();
		$install = $result['install'] ?? null;
		$uninstall = $result['uninstall'] ?? null;
		$installed = $result['installed'] ?? ($install === null ? null : $install === '');

		$lines[] = rtrim(sprintf($format, $id, $manifest['version'], $result['gate'] ?? '-', $installed === null ? '-' : ($installed ? 'yes' : 'no'),
			count(array_intersect($manifest['points'], $fired)).'/'.count($manifest['points']), count($not_in_tree), count($diagnostics), $yes_no($uninstall)));

		$detail = array();

		if ($manifest['error'] !== '')
			$detail[] = '   manifest     '.$manifest['error'];

		if ($install !== null && $install !== '')
			$detail[] = '   install      '.$install;

		$not_fired = array_diff($in_tree, $fired);

		if ($installed && $not_fired)
			$detail[] = '   not fired    '.implode(', ', $not_fired);

		if ($not_in_tree)
			$detail[] = '   not in tree  '.implode(', ', array_map(static fn(string $point): string =>
				$point.(isset($run['offered'][$point]) ? ' (offered by '.implode(', ', $run['offered'][$point]).')' : ''), $not_in_tree));

		foreach ($diagnostics as $text => $steps)
			$detail[] = '   '.$text.' ['.extension_corpus_steps_label($steps).']';

		if ($uninstall !== null && $uninstall !== '')
			$detail[] = '   uninstall    '.$uninstall;

		if ($detail)
			$details = array_merge($details, array('', $id), $detail);
	}

	foreach (array_diff_key($run['diagnostics'], array_flip($run['order'])) as $owner => $diagnostics)
	{
		$details = array_merge($details, array('', $owner === '' ? 'forum code' : $owner));

		foreach ($diagnostics as $text => $steps)
			$details[] = '   '.$text.' ['.extension_corpus_steps_label($steps).']';
	}

	if ($run['pages'])
	{
		$details = array_merge($details, array('', 'pages that failed'));

		foreach ($run['pages'] as $label => $failure)
			$details[] = '   '.$label.': '.$failure;
	}

	return array_merge($lines, $details);
}


function extension_corpus_main($base_url, $corpus, $requested)
{
	$drivers = extension_corpus_drivers();
	$requested = $requested ?: array(EXTENSION_CORPUS_DRIVER);
	$unknown = array_diff($requested, array_keys($drivers));

	if ($unknown)
	{
		fwrite(STDERR, 'unknown driver(s): '.implode(', ', $unknown)."\n");
		return 2;
	}

	echo 'extension corpus '.$corpus.' on '.$base_url.' (PHP '.PHP_VERSION.")\n\n";

	$status = 0;

	foreach (array_intersect_key($drivers, array_flip($requested)) as $db_type => $spec)
	{
		echo '== '.$db_type." ==\n";

		try
		{
			foreach (extension_corpus_report(extension_corpus_run_driver($db_type, $spec, $base_url, $corpus)) as $line)
				echo $line."\n";
		}
		catch (Throwable $e)
		{
			echo '   FAIL  no report: '.get_class($e).': '.$e->getMessage()."\n";
			$status = 1;
		}

		echo "\n";
	}

	return $status;
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
{
	$corpus = rtrim((string) getenv('PUNBB_TEST_EXTENSIONS_DIR'), '/');
	$reason = extension_corpus_unusable($corpus);

	if ($reason !== '')
	{
		echo $reason.($corpus === '' ? ', skipped' : '')."\n";
		exit($corpus === '' ? 0 : 2);
	}

	install_matrix_stash_config();
	register_shutdown_function('extension_corpus_remove_placed');

	exit(extension_corpus_main(
		rtrim(getenv('PUNBB_TEST_BASE_URL') ?: 'http://localhost', '/'),
		$corpus,
		array_slice($argv, 1)
	));
}
