<?php
/**
 * Functional pass over the user-facing and administrative flows.
 *
 * Installs a forum of its own over HTTP, then walks it the way a browser would:
 * register, log in, post, quote, edit, delete, search, change the profile,
 * upload an avatar, log out — and, as the administrator, moderate the topic,
 * save settings, create and delete a forum, ban and unban, flip maintenance
 * mode, rebuild the search index and read every syndication format. Every step
 * asserts the status code, a marker only the intended page carries, and the
 * absence of any PHP diagnostic.
 *
 * Run it from inside the web container — like the other integration runs it
 * needs the forum both as files (it rewrites config.php) and as a running site.
 *
 *   php .dev/tests/Integration/user_flows.php
 *
 * Environment (all optional, defaults match a stock dev stack):
 *   PUNBB_TEST_BASE_URL          site URL the flows are driven on
 *   PUNBB_TEST_MYSQL_HOST/USER/PASSWORD/DBNAME
 *   PUNBB_TEST_ERROR_LOG         error log file to assert on, when there is one
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// The install matrix owns the shared pieces: the curl helpers it pulls in, the
// connection helper, the installer form and the config.php stash.
require_once __DIR__.'/install_matrix.php';

define('USER_FLOWS_ROOT', dirname(__DIR__, 3).'/');

// A prefix of its own, so the pass never touches the forum installed in the
// same database, nor the prefixes the other integration runs claim.
const USER_FLOWS_PREFIX = 'fl1_';

// The member the pass registers. The administrator is the install matrix's.
const USER_FLOWS_USERNAME = 'flow-member';
const USER_FLOWS_PASSWORD = 'flow-password';
const USER_FLOWS_EMAIL = 'flow-member@example.invalid';

// Content markers. Every one of them is inside the BMP: a fresh install still
// declares its MySQL tables utf8mb3, so 4-byte characters are not storable.
// The emoji step asserts exactly that, and nothing else here depends on it.
const USER_FLOWS_KEYWORD = 'zwitterion';                  // unique, searchable
const USER_FLOWS_SUBJECT = 'Flow topic Ümlaut';
const USER_FLOWS_REPLY = 'Ответ на тему, ελληνικά';
const USER_FLOWS_EDITED = 'Отредактированное сообщение';
const USER_FLOWS_EMOJI = "\u{1F642}";
const USER_FLOWS_LOCATION = 'Кёльн';
const USER_FLOWS_SIGNATURE = '[b]Подпись участника[/b]';
const USER_FLOWS_BOARD_TITLE = 'Flow board Ärger';
const USER_FLOWS_FORUM_NAME = 'Flow forum Ümlaut';
const USER_FLOWS_BAN_MESSAGE = 'Flow ban message';

// A bare IDN URL in a post: the parser has to linkify it and store the host as
// punycode (plan 04's UTS-46 conversion), while still displaying the unicode form.
const USER_FLOWS_IDN_URL = 'http://bücher.example/';
const USER_FLOWS_IDN_PUNYCODE = 'xn--bcher-kva.example';

// 1x1 PNG, the smallest thing profile.php's avatar validation accepts.
const USER_FLOWS_AVATAR_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk'.
	'YPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';


/** How to reach MySQL, and where this run's tables go. */
function user_flows_spec()
{
	$drivers = install_matrix_drivers();

	return array_merge($drivers['mysqli'], array('prefix' => USER_FLOWS_PREFIX));
}


/**
 * An attribute of one HTML start tag, entity-decoded, or '' when it has none.
 */
function user_flows_attribute($tag, $name)
{
	if (!preg_match('#\b'.preg_quote($name, '#').'\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', (string) $tag, $match))
		return '';

	return html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? ''), ENT_QUOTES, 'UTF-8');
}


/**
 * The fields one form submits by default: text inputs, hidden inputs, the
 * checked checkboxes and radios, textareas and the selected option of every
 * select. Submit buttons are left out on purpose — a page like moderate.php
 * offers several, and which one is pressed decides what the request does, so
 * the caller names it explicitly.
 */
function user_flows_fields($html)
{
	$fields = array();

	if (preg_match_all('#<input\b[^>]*>#i', (string) $html, $inputs))
		foreach ($inputs[0] as $input)
		{
			$name = user_flows_attribute($input, 'name');
			$type = strtolower(user_flows_attribute($input, 'type'));

			if ($name === '' || in_array($type, array('submit', 'button', 'reset', 'image', 'file'), true))
				continue;

			if (in_array($type, array('checkbox', 'radio'), true) && !preg_match('#\bchecked\b#i', $input))
				continue;

			$fields[$name] = user_flows_attribute($input, 'value');
		}

	if (preg_match_all('#<textarea\b([^>]*)>(.*?)</textarea>#is', (string) $html, $areas, PREG_SET_ORDER))
		foreach ($areas as $area)
		{
			$name = user_flows_attribute($area[1], 'name');

			if ($name !== '')
				$fields[$name] = html_entity_decode($area[2], ENT_QUOTES, 'UTF-8');
		}

	if (preg_match_all('#<select\b([^>]*)>(.*?)</select>#is', (string) $html, $selects, PREG_SET_ORDER))
		foreach ($selects as $select)
		{
			$name = user_flows_attribute($select[1], 'name');

			if ($name === '' || !preg_match_all('#<option\b([^>]*)>(.*?)</option>#is', $select[2], $options, PREG_SET_ORDER))
				continue;

			$chosen = $options[0];
			foreach ($options as $option)
				if (preg_match('#\bselected\b#i', $option[1]))
					$chosen = $option;

			$fields[$name] = preg_match('#\bvalue\s*=#i', $chosen[1])
				? user_flows_attribute($chosen[1], 'value')
				: html_entity_decode(trim(strip_tags($chosen[2])), ENT_QUOTES, 'UTF-8');
		}

	return $fields;
}


/** Every form of a page, in document order. */
function user_flows_forms($body)
{
	$forms = array();

	if (!preg_match_all('#<form\b[^>]*>.*?</form>#is', (string) $body, $matches))
		return $forms;

	foreach ($matches[0] as $html)
	{
		preg_match('#<form\b([^>]*)>#i', $html, $open);
		$attributes = $open[1] ?? '';

		$forms[] = array(
			'html' => $html,
			'action' => user_flows_attribute($attributes, 'action'),
			'method' => strtolower(user_flows_attribute($attributes, 'method')) === 'get' ? 'get' : 'post',
			'fields' => user_flows_fields($html),
		);
	}

	return $forms;
}


/** The first form of a page carrying $needle, or null when there is none. */
function user_flows_find_form($body, $needle)
{
	foreach (user_flows_forms($body) as $form)
		if (strpos($form['html'], $needle) !== false)
			return $form;

	return null;
}


/** The validation errors a form page lists, in order. */
function user_flows_errors($body)
{
	if (!preg_match('#<ul class="error-list">(.*?)</ul>#is', (string) $body, $list))
		return array();

	if (!preg_match_all('#<li[^>]*>(.*?)</li>#is', $list[1], $items))
		return array();

	return array_map(static fn(string $item): string => trim(strip_tags($item)), $items[1]);
}


/** Shortened plain text of a page, for naming what came back instead. */
function user_flows_summary($body)
{
	$errors = user_flows_errors($body);

	if ($errors)
		return 'form errors: '.implode(' | ', $errors);

	$text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $body);
	$text = trim((string) preg_replace('#\s+#u', ' ', strip_tags((string) $text)));

	return mb_strlen($text) > 400 ? mb_substr($text, 0, 400).' …' : $text;
}


/** A relative URL resolved against the page it was found on. */
function user_flows_resolve($page_url, $url)
{
	$url = (string) $url;

	if ($url === '')
		return $page_url;

	if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url))
		return $url;

	$parts = parse_url($page_url);
	$origin = ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? 'localhost').
		(isset($parts['port']) ? ':'.$parts['port'] : '');

	if ($url[0] === '/')
		return $origin.$url;

	$directory = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

	return $origin.$directory.$url;
}


/** The destination of the forum's redirect page, or '' when it is not one. */
function user_flows_redirect_target($body)
{
	if (!preg_match('#<meta http-equiv="refresh" content="[^;"]*;URL=([^"]*)"#i', (string) $body, $match))
		return '';

	return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}


class UserFlowsFailure extends RuntimeException
{
}


function user_flows_assert($condition, $message)
{
	if (!$condition)
		throw new UserFlowsFailure($message);
}


/** One request, with its body swept for diagnostics and its status checked. */
function user_flows_request(&$state, $session, $url, $post = null, $allowed = array(200, 302))
{
	$response = smoke_request($url, $state['jars'][$session], $post);
	$response['url'] = $url;

	$state['diagnostics'] = array_merge($state['diagnostics'], smoke_diagnostics($response['body']));

	if ($response['body'] === false)
		throw new UserFlowsFailure($url.': transport error: '.$response['error']);

	if (!in_array($response['status'], $allowed, true))
		throw new UserFlowsFailure($url.' returned HTTP '.$response['status'].': '.user_flows_summary($response['body']));

	return $response;
}


function user_flows_get(&$state, $session, $path, $allowed = array(200, 302))
{
	return user_flows_request($state, $session, user_flows_resolve($state['base_url'].'/', $path), null, $allowed);
}


/**
 * Submit the form of $response carrying $needle, with $overrides merged in
 * (that is where the submit button belongs). GET forms are re-issued as a query
 * string, which is how the reindex and search forms work.
 */
function user_flows_submit(&$state, $session, $response, $needle, $overrides = array(), $allowed = array(200, 302))
{
	$form = user_flows_find_form($response['body'], $needle);

	user_flows_assert($form !== null, $response['url'].' rendered no form carrying '.$needle.': '.user_flows_summary($response['body']));

	$fields = array_merge($form['fields'], $overrides);
	$action = user_flows_resolve($response['url'], $form['action']);

	if ($form['method'] === 'get')
	{
		$query = http_build_query(array_filter($fields, static fn($value): bool => !is_object($value)));

		return user_flows_request($state, $session, $action.(strpos($action, '?') === false ? '?' : '&').$query, null, $allowed);
	}

	return user_flows_request($state, $session, $action, $fields, $allowed);
}


/**
 * Follow whatever the forum answered an action with: its redirect page, or the
 * bare Location a search sends. The page after it is where the result shows up.
 */
function user_flows_follow(&$state, $session, $response)
{
	$target = user_flows_redirect_target($response['body']);

	if ($target === '')
		$target = (string) ($response['headers']['location'] ?? '');

	if ($target === '')
		return $response;

	return user_flows_request($state, $session, user_flows_resolve($response['url'], $target));
}


/**
 * Answer the CSRF confirm form when a GET-triggered action raises one. The form
 * posts back to the same URL with the token, so the action goes through.
 */
function user_flows_confirm(&$state, $session, $response)
{
	if (strpos((string) $response['body'], 'name="prev_url"') === false)
		return $response;

	return user_flows_submit($state, $session, $response, 'name="prev_url"');
}


/** A GET-triggered action: confirm it if asked, then follow the redirect. */
function user_flows_act(&$state, $session, $path)
{
	$response = user_flows_get($state, $session, $path);
	$response = user_flows_confirm($state, $session, $response);

	return user_flows_follow($state, $session, $response);
}


/** One scalar out of this run's database, or null when it cannot be read. */
function user_flows_value($sql)
{
	$spec = user_flows_spec();
	$link = install_matrix_mysql($spec);
	mysqli_set_charset($link, 'utf8mb4');

	try
	{
		$result = mysqli_query($link, str_replace('%p', $spec['prefix'], $sql));
		$value = ($result && ($row = mysqli_fetch_row($result))) ? $row[0] : null;
	}
	catch (mysqli_sql_exception $e)
	{
		mysqli_close($link);
		throw new UserFlowsFailure('query failed: '.$e->getMessage());
	}

	mysqli_close($link);

	return $value;
}


function user_flows_count($table)
{
	return (int) user_flows_value('SELECT COUNT(*) FROM `%p'.$table.'`');
}


/** Every mod-option link a page renders, keyed by nothing: order is enough. */
function user_flows_mod_links($body)
{
	if (!preg_match_all('#<a class="mod-option" href="([^"]+)"[^>]*>(.*?)</a>#is', (string) $body, $matches, PREG_SET_ORDER))
		return array();

	$links = array();

	foreach ($matches as $match)
		$links[trim(strip_tags($match[2]))] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');

	return $links;
}


/** The mod-option link whose label starts with $word, or '' when there is none. */
function user_flows_mod_link($links, $word)
{
	foreach ($links as $label => $url)
		if (stripos($label, $word) === 0)
			return $url;

	return '';
}


// ---------------------------------------------------------------- the flows --

function user_flows_step_register(&$state)
{
	$response = user_flows_get($state, 'member', 'register.php');

	$response = user_flows_submit($state, 'member', $response, 'name="req_username"', array(
		'form_sent' => '1',
		'req_username' => USER_FLOWS_USERNAME,
		'req_email1' => USER_FLOWS_EMAIL,
		'req_email2' => USER_FLOWS_EMAIL,
		'req_password1' => USER_FLOWS_PASSWORD,
		'req_password2' => USER_FLOWS_PASSWORD,
		'register' => '1',
	));

	$index = user_flows_follow($state, 'member', $response);

	user_flows_assert(strpos((string) $index['body'], 'logout') !== false,
		'the registration did not log the new member in: '.user_flows_summary($response['body']));

	$id = user_flows_value('SELECT id FROM `%pusers` WHERE username = \''.USER_FLOWS_USERNAME.'\'');
	user_flows_assert($id !== null, 'the member was not stored');

	$state['user_id'] = (int) $id;
}


function user_flows_step_login(&$state)
{
	// Out first, so the password really is checked rather than the cookie the
	// registration set.
	$index = user_flows_get($state, 'member', 'index.php');
	$links = array();

	if (preg_match('#<a href="([^"]*login\.php\?action=out[^"]*)"#i', (string) $index['body'], $match))
		$links['logout'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');

	user_flows_assert(isset($links['logout']), 'index.php rendered no logout link for the member');
	user_flows_act($state, 'member', $links['logout']);

	$form = user_flows_get($state, 'member', 'login.php');
	$response = user_flows_submit($state, 'member', $form, 'name="req_password"', array(
		'form_sent' => '1',
		'req_username' => USER_FLOWS_USERNAME,
		'req_password' => USER_FLOWS_PASSWORD,
		'login' => '1',
	));

	$index = user_flows_follow($state, 'member', $response);

	user_flows_assert(strpos((string) $index['body'], 'logout') !== false,
		'the member could not log in again: '.user_flows_summary($response['body']));
}


function user_flows_step_new_topic(&$state)
{
	$form = user_flows_get($state, 'member', 'post.php?fid=1');

	$response = user_flows_submit($state, 'member', $form, 'name="req_subject"', array(
		'form_sent' => '1',
		'req_subject' => USER_FLOWS_SUBJECT,
		'req_message' => 'A topic about '.USER_FLOWS_KEYWORD.' with [b]bold[/b] text.',
		'submit' => '1',
	));

	user_flows_follow($state, 'member', $response);

	$tid = user_flows_value('SELECT id FROM `%ptopics` WHERE subject = \''.USER_FLOWS_SUBJECT.'\'');
	user_flows_assert($tid !== null, 'the topic was not stored: '.user_flows_summary($response['body']));

	$state['topic_id'] = (int) $tid;
	$state['first_post_id'] = (int) user_flows_value('SELECT first_post_id FROM `%ptopics` WHERE id = '.$state['topic_id']);

	$topic = user_flows_get($state, 'member', 'viewtopic.php?id='.$state['topic_id']);
	user_flows_assert(strpos((string) $topic['body'], '<strong>bold</strong>') !== false,
		'the topic does not render its BBCode');
}


function user_flows_step_reply(&$state)
{
	$form = user_flows_get($state, 'member', 'post.php?tid='.$state['topic_id']);

	$response = user_flows_submit($state, 'member', $form, 'name="req_message"', array(
		'form_sent' => '1',
		'req_message' => USER_FLOWS_REPLY,
		'submit' => '1',
	));

	user_flows_follow($state, 'member', $response);

	$id = user_flows_value('SELECT id FROM `%pposts` WHERE topic_id = '.$state['topic_id'].' ORDER BY id DESC LIMIT 1');
	user_flows_assert((int) $id !== $state['first_post_id'], 'the reply was not stored: '.user_flows_summary($response['body']));

	$state['reply_id'] = (int) $id;

	$topic = user_flows_get($state, 'member', 'viewtopic.php?id='.$state['topic_id']);
	user_flows_assert(strpos((string) $topic['body'], USER_FLOWS_REPLY) !== false,
		'the reply is not in the topic');
}


function user_flows_step_quote(&$state)
{
	$form = user_flows_get($state, 'member', 'post.php?tid='.$state['topic_id'].'&qid='.$state['reply_id']);

	$quoted = user_flows_find_form($form['body'], 'name="req_message"');
	user_flows_assert($quoted !== null, 'the quote form did not render');
	user_flows_assert(strpos($quoted['fields']['req_message'] ?? '', '[quote=') === 0,
		'the quote form did not prefill the quoted post: '.substr($quoted['fields']['req_message'] ?? '', 0, 80));

	$message = $quoted['fields']['req_message']."\n".
		'[b]Bold[/b] and [i]italic[/i] and a link: '.USER_FLOWS_IDN_URL;

	$response = user_flows_submit($state, 'member', $form, 'name="req_message"', array(
		'form_sent' => '1',
		'req_message' => $message,
		'submit' => '1',
	));

	user_flows_follow($state, 'member', $response);

	$id = user_flows_value('SELECT id FROM `%pposts` WHERE topic_id = '.$state['topic_id'].' ORDER BY id DESC LIMIT 1');
	user_flows_assert((int) $id !== $state['reply_id'], 'the quoting reply was not stored: '.user_flows_summary($response['body']));

	$state['quote_id'] = (int) $id;

	$topic = user_flows_get($state, 'member', 'viewtopic.php?id='.$state['topic_id']);

	user_flows_assert(strpos((string) $topic['body'], '<blockquote') !== false, 'the quote does not render as a blockquote');
	user_flows_assert(strpos((string) $topic['body'], '<em>italic</em>') !== false, 'the quoting post does not render its BBCode');
	user_flows_assert(strpos((string) $topic['body'], 'href="http://'.USER_FLOWS_IDN_PUNYCODE.'/"') !== false,
		'the IDN link was not converted to punycode');
}


/**
 * A 4-byte character on a MySQL install. The schema a fresh install creates is
 * utf8mb3, so the INSERT is rejected by the server: what this asserts is that
 * the driver turns that into the forum's own error page rather than a PHP fatal
 * or a half-written topic. See the ⚠️ note in the plan.
 */
function user_flows_step_emoji(&$state)
{
	$before = user_flows_count('posts');

	$form = user_flows_get($state, 'member', 'post.php?tid='.$state['topic_id']);

	$response = user_flows_submit($state, 'member', $form, 'name="req_message"', array(
		'form_sent' => '1',
		'req_message' => 'An emoji: '.USER_FLOWS_EMOJI,
		'submit' => '1',
	), array(200, 302, 503));

	if ($response['status'] === 503)
	{
		user_flows_assert(strpos((string) $response['body'], 'Sorry! The page could not be loaded.') !== false,
			'a rejected 4-byte character produced HTTP 503 without the forum error page: '.user_flows_summary($response['body']));

		user_flows_assert(user_flows_count('posts') === $before,
			'the rejected 4-byte post left a row behind');

		return;
	}

	// A server that stores it (utf8mb4 columns) must round-trip it intact.
	user_flows_follow($state, 'member', $response);

	$topic = user_flows_get($state, 'member', 'viewtopic.php?id='.$state['topic_id']);
	user_flows_assert(strpos((string) $topic['body'], USER_FLOWS_EMOJI) !== false,
		'the 4-byte character was accepted but is not in the topic');

	$state['emoji_stored'] = true;
}


function user_flows_step_edit(&$state)
{
	$form = user_flows_get($state, 'member', 'edit.php?id='.$state['quote_id']);

	$response = user_flows_submit($state, 'member', $form, 'name="req_message"', array(
		'form_sent' => '1',
		'req_message' => USER_FLOWS_EDITED.' '.USER_FLOWS_IDN_URL,
		'submit' => '1',
	));

	user_flows_follow($state, 'member', $response);

	$topic = user_flows_get($state, 'member', 'viewtopic.php?id='.$state['topic_id']);

	user_flows_assert(strpos((string) $topic['body'], USER_FLOWS_EDITED) !== false, 'the edit did not take');
	user_flows_assert(strpos((string) $topic['body'], '<blockquote') === false, 'the edited post still carries the old quote');
}


function user_flows_step_delete(&$state)
{
	$before = user_flows_count('posts');
	$form = user_flows_get($state, 'member', 'delete.php?id='.$state['quote_id']);

	$response = user_flows_submit($state, 'member', $form, 'name="req_confirm"', array(
		'req_confirm' => '1',
		'delete' => '1',
	));

	user_flows_follow($state, 'member', $response);

	user_flows_assert(user_flows_count('posts') === $before - 1,
		'the post was not deleted: '.user_flows_summary($response['body']));

	$topic = user_flows_get($state, 'member', 'viewtopic.php?id='.$state['topic_id']);
	user_flows_assert(strpos((string) $topic['body'], USER_FLOWS_EDITED) === false,
		'the deleted post is still in the topic');
}


function user_flows_step_search(&$state)
{
	$response = user_flows_get($state, 'member', 'search.php?action=search&keywords='.USER_FLOWS_KEYWORD.'&show_as=topics');
	$results = user_flows_follow($state, 'member', $response);

	user_flows_assert(strpos((string) $results['body'], USER_FLOWS_SUBJECT) !== false,
		'the search did not find the topic: '.user_flows_summary($results['body']));

	// The same word by author, which walks the other search branch.
	$response = user_flows_get($state, 'member', 'search.php?action=search&keywords='.USER_FLOWS_KEYWORD.
		'&author='.rawurlencode(USER_FLOWS_USERNAME).'&show_as=posts');
	$results = user_flows_follow($state, 'member', $response);

	user_flows_assert(strpos((string) $results['body'], USER_FLOWS_SUBJECT) !== false,
		'the search by author did not find the topic: '.user_flows_summary($results['body']));
}


function user_flows_step_profile_identity(&$state)
{
	$form = user_flows_get($state, 'member', 'profile.php?section=identity&id='.$state['user_id']);

	$response = user_flows_submit($state, 'member', $form, 'name="form[location]"', array(
		'form_sent' => '1',
		'form[location]' => USER_FLOWS_LOCATION,
		'form[realname]' => 'Flow Member',
		'update' => '1',
	));

	user_flows_follow($state, 'member', $response);

	$stored = (string) user_flows_value('SELECT location FROM `%pusers` WHERE id = '.$state['user_id']);
	user_flows_assert($stored === USER_FLOWS_LOCATION,
		'the location is \''.$stored.'\' after the save, expected \''.USER_FLOWS_LOCATION.'\'');

	$profile = user_flows_get($state, 'member', 'profile.php?id='.$state['user_id']);
	user_flows_assert(strpos((string) $profile['body'], USER_FLOWS_LOCATION) !== false,
		'the profile does not show the new location');
}


function user_flows_step_profile_settings(&$state)
{
	$form = user_flows_get($state, 'member', 'profile.php?section=settings&id='.$state['user_id']);

	$settings = user_flows_find_form($form['body'], 'name="form[disp_topics]"');
	user_flows_assert($settings !== null, 'the settings section rendered no settings form: '.user_flows_summary($form['body']));
	user_flows_assert(isset($settings['fields']['form[style]']), 'the settings section offers no style control');

	// The language selector only renders when more than one pack is installed;
	// the value is posted either way, and profile.php validates it against lang/.
	$packs = array_filter((array) glob(USER_FLOWS_ROOT.'lang/*/common.php'));

	user_flows_assert($packs !== array(), 'the checkout ships no language pack');
	user_flows_assert(count($packs) < 2 || isset($settings['fields']['form[language]']),
		'several language packs are installed but the settings section offers no language selector');

	$response = user_flows_submit($state, 'member', $form, 'name="form[disp_topics]"', array(
		'form_sent' => '1',
		'form[language]' => 'English',
		'form[style]' => 'Oxygen',
		'form[disp_topics]' => '25',
		'form[disp_posts]' => '10',
		'form[show_smilies]' => '1',
		'update' => '1',
	));

	user_flows_follow($state, 'member', $response);

	foreach (array('language' => 'English', 'style' => 'Oxygen', 'disp_topics' => '25', 'disp_posts' => '10') as $column => $expected)
	{
		$stored = (string) user_flows_value('SELECT `'.$column.'` FROM `%pusers` WHERE id = '.$state['user_id']);
		user_flows_assert($stored === $expected, $column.' is \''.$stored.'\' after the save, expected \''.$expected.'\'');
	}

	// A style the checkout does not ship must be refused, not stored.
	user_flows_submit($state, 'member', $form, 'name="form[disp_topics]"', array(
		'form_sent' => '1',
		'form[style]' => 'NoSuchStyle',
		'update' => '1',
	));

	user_flows_assert((string) user_flows_value('SELECT style FROM `%pusers` WHERE id = '.$state['user_id']) === 'Oxygen',
		'an unknown style was stored');

	// ...and neither may an unknown language pack.
	user_flows_submit($state, 'member', $form, 'name="form[disp_topics]"', array(
		'form_sent' => '1',
		'form[language]' => 'NoSuchLanguage',
		'update' => '1',
	));

	user_flows_assert((string) user_flows_value('SELECT language FROM `%pusers` WHERE id = '.$state['user_id']) === 'English',
		'an unknown language pack was stored');
}


function user_flows_step_signature(&$state)
{
	$form = user_flows_get($state, 'member', 'profile.php?section=signature&id='.$state['user_id']);

	$response = user_flows_submit($state, 'member', $form, 'name="signature"', array(
		'form_sent' => '1',
		'signature' => USER_FLOWS_SIGNATURE,
		'update' => '1',
	));

	user_flows_follow($state, 'member', $response);

	$stored = (string) user_flows_value('SELECT signature FROM `%pusers` WHERE id = '.$state['user_id']);
	user_flows_assert($stored === USER_FLOWS_SIGNATURE,
		'the signature is \''.$stored.'\' after the save, expected \''.USER_FLOWS_SIGNATURE.'\'');

	$topic = user_flows_get($state, 'member', 'viewtopic.php?id='.$state['topic_id']);
	user_flows_assert(strpos((string) $topic['body'], '<strong>Подпись участника</strong>') !== false,
		'the signature does not render under the member\'s posts');
}


function user_flows_step_avatar(&$state)
{
	// tempnam() creates its own file; the image goes to the suffixed name, so
	// both have to be removed.
	$stub = (string) tempnam(sys_get_temp_dir(), 'avatar');
	$file = $stub.'.png';
	file_put_contents($file, (string) base64_decode(USER_FLOWS_AVATAR_PNG));

	user_flows_assert(getimagesize($file) !== false, 'the avatar fixture is not a readable image');

	$form = user_flows_get($state, 'member', 'profile.php?section=avatar&id='.$state['user_id']);

	user_flows_assert(user_flows_find_form($form['body'], 'name="req_file"') !== null,
		'the avatar section offers no upload field: '.user_flows_summary($form['body']));

	$response = user_flows_submit($state, 'member', $form, 'name="req_file"', array(
		'form_sent' => '1',
		'req_file' => new CURLFile($file, 'image/png', 'avatar.png'),
		'upload' => '1',
	));

	user_flows_follow($state, 'member', $response);
	@unlink($file);
	@unlink($stub);

	$stored = USER_FLOWS_ROOT.'img/avatars/'.$state['user_id'].'.png';

	user_flows_assert(is_file($stored), 'the avatar file was not written: '.user_flows_summary($response['body']));
	// FORUM_AVATAR_PNG — the column stores the image type, not a flag.
	$flag = (string) user_flows_value('SELECT avatar FROM `%pusers` WHERE id = '.$state['user_id']);
	user_flows_assert($flag === '3', 'the avatar column is \''.$flag.'\' after the upload, expected \'3\' (PNG)');

	$profile = user_flows_get($state, 'member', 'profile.php?id='.$state['user_id']);
	user_flows_assert(strpos((string) $profile['body'], 'img/avatars/'.$state['user_id'].'.png') !== false,
		'the profile does not show the uploaded avatar');

	@unlink($stored);
}


function user_flows_step_logout(&$state)
{
	$index = user_flows_get($state, 'member', 'index.php');

	user_flows_assert(preg_match('#<a href="([^"]*login\.php\?action=out[^"]*)"#i', (string) $index['body'], $match) === 1,
		'index.php rendered no logout link');

	user_flows_act($state, 'member', html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));

	$index = user_flows_get($state, 'member', 'index.php');
	user_flows_assert(strpos((string) $index['body'], 'login.php?action=out') === false,
		'the member is still logged in after the logout');
}


// ------------------------------------------------------- administration --

function user_flows_step_admin_settings(&$state)
{
	$form = user_flows_get($state, 'admin', 'admin/settings.php?section=setup');

	$response = user_flows_submit($state, 'admin', $form, 'name="form[board_title]"', array(
		'form_sent' => '1',
		'form[board_title]' => USER_FLOWS_BOARD_TITLE,
		'save' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	user_flows_assert((string) user_flows_value('SELECT conf_value FROM `%pconfig` WHERE conf_name = \'o_board_title\'') === USER_FLOWS_BOARD_TITLE,
		'the board title was not saved');

	$index = user_flows_get($state, 'admin', 'index.php');
	user_flows_assert(strpos((string) $index['body'], USER_FLOWS_BOARD_TITLE) !== false,
		'the index still shows the old board title, so the config cache was not rebuilt');
}


function user_flows_step_forum_create(&$state)
{
	$form = user_flows_get($state, 'admin', 'admin/forums.php');

	$response = user_flows_submit($state, 'admin', $form, 'name="forum_name"', array(
		'forum_name' => USER_FLOWS_FORUM_NAME,
		'add_forum' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	$id = user_flows_value('SELECT id FROM `%pforums` WHERE forum_name = \''.USER_FLOWS_FORUM_NAME.'\'');
	user_flows_assert($id !== null, 'the forum was not created: '.user_flows_summary($response['body']));

	$state['new_forum_id'] = (int) $id;

	$index = user_flows_get($state, 'admin', 'index.php');
	user_flows_assert(strpos((string) $index['body'], USER_FLOWS_FORUM_NAME) !== false,
		'the new forum is not on the index');
}


function user_flows_step_moderate_move(&$state)
{
	$page = user_flows_get($state, 'admin', 'moderate.php?fid=1');

	$chooser = user_flows_submit($state, 'admin', $page, 'name="topics[]"', array(
		'topics[]' => (string) $state['topic_id'],
		'move_topics' => '1',
	));

	$response = user_flows_submit($state, 'admin', $chooser, 'name="move_to_forum"', array(
		'move_to_forum' => (string) $state['new_forum_id'],
		'move_topics_to' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	user_flows_assert((int) user_flows_value('SELECT forum_id FROM `%ptopics` WHERE id = '.$state['topic_id']) === $state['new_forum_id'],
		'the topic did not move: '.user_flows_summary($response['body']));

	// ...and back, so the rest of the pass keeps its forum.
	$page = user_flows_get($state, 'admin', 'moderate.php?fid='.$state['new_forum_id']);

	$chooser = user_flows_submit($state, 'admin', $page, 'name="topics[]"', array(
		'topics[]' => (string) $state['topic_id'],
		'move_topics' => '1',
	));

	$response = user_flows_submit($state, 'admin', $chooser, 'name="move_to_forum"', array(
		'move_to_forum' => '1',
		'move_topics_to' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	user_flows_assert((int) user_flows_value('SELECT forum_id FROM `%ptopics` WHERE id = '.$state['topic_id']) === 1,
		'the topic did not move back');
}


function user_flows_step_moderate_close(&$state)
{
	$topic = user_flows_get($state, 'admin', 'viewtopic.php?id='.$state['topic_id']);
	$links = user_flows_mod_links($topic['body']);
	$close = user_flows_mod_link($links, 'Close');

	user_flows_assert($close !== '', 'the topic offers no Close option: '.implode(', ', array_keys($links)));
	user_flows_act($state, 'admin', $close);

	user_flows_assert((string) user_flows_value('SELECT closed FROM `%ptopics` WHERE id = '.$state['topic_id']) === '1',
		'the topic was not closed');

	$topic = user_flows_get($state, 'admin', 'viewtopic.php?id='.$state['topic_id']);
	$links = user_flows_mod_links($topic['body']);
	$open = user_flows_mod_link($links, 'Open');

	user_flows_assert($open !== '', 'a closed topic offers no Open option: '.implode(', ', array_keys($links)));
	user_flows_act($state, 'admin', $open);

	user_flows_assert((string) user_flows_value('SELECT closed FROM `%ptopics` WHERE id = '.$state['topic_id']) === '0',
		'the topic was not reopened');
}


function user_flows_step_moderate_sticky(&$state)
{
	$topic = user_flows_get($state, 'admin', 'viewtopic.php?id='.$state['topic_id']);
	$links = user_flows_mod_links($topic['body']);
	$stick = user_flows_mod_link($links, 'Stick');

	user_flows_assert($stick !== '', 'the topic offers no Stick option: '.implode(', ', array_keys($links)));
	user_flows_act($state, 'admin', $stick);

	user_flows_assert((string) user_flows_value('SELECT sticky FROM `%ptopics` WHERE id = '.$state['topic_id']) === '1',
		'the topic was not stuck');

	$forum = user_flows_get($state, 'admin', 'viewforum.php?id=1');
	user_flows_assert(strpos((string) $forum['body'], 'sticky') !== false, 'the forum listing does not mark the sticky topic');

	$topic = user_flows_get($state, 'admin', 'viewtopic.php?id='.$state['topic_id']);
	$links = user_flows_mod_links($topic['body']);
	$unstick = user_flows_mod_link($links, 'Unstick');

	user_flows_assert($unstick !== '', 'a sticky topic offers no Unstick option: '.implode(', ', array_keys($links)));
	user_flows_act($state, 'admin', $unstick);

	user_flows_assert((string) user_flows_value('SELECT sticky FROM `%ptopics` WHERE id = '.$state['topic_id']) === '0',
		'the topic was not unstuck');
}


function user_flows_step_forum_delete(&$state)
{
	$form = user_flows_get($state, 'admin', 'admin/forums.php?del_forum='.$state['new_forum_id']);

	$response = user_flows_submit($state, 'admin', $form, 'name="del_forum_comply"', array(
		'del_forum_comply' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	user_flows_assert(user_flows_value('SELECT id FROM `%pforums` WHERE id = '.$state['new_forum_id']) === null,
		'the forum was not deleted: '.user_flows_summary($response['body']));

	// The topic was moved back before the delete: it must have survived it.
	user_flows_assert(user_flows_value('SELECT id FROM `%ptopics` WHERE id = '.$state['topic_id']) !== null,
		'deleting the empty forum took the topic with it');
}


function user_flows_step_ban(&$state)
{
	$page = user_flows_get($state, 'admin', 'admin/bans.php');

	$form = user_flows_submit($state, 'admin', $page, 'name="new_ban_user"', array(
		'new_ban_user' => USER_FLOWS_USERNAME,
		'add_ban' => '1',
	));

	$response = user_flows_submit($state, 'admin', $form, 'name="ban_message"', array(
		'ban_message' => USER_FLOWS_BAN_MESSAGE,
		'add_edit_ban' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	$ban = user_flows_value('SELECT id FROM `%pbans` WHERE username = \''.USER_FLOWS_USERNAME.'\'');
	user_flows_assert($ban !== null, 'the ban was not stored: '.user_flows_summary($response['body']));

	$page = user_flows_get($state, 'admin', 'admin/bans.php');
	user_flows_assert(strpos((string) $page['body'], USER_FLOWS_USERNAME) !== false, 'the ban is not listed');

	user_flows_assert(preg_match('#href="([^"]*del_ban='.$ban.'[^"]*)"#i', (string) $page['body'], $match) === 1,
		'the ban list offers no removal link');

	user_flows_act($state, 'admin', html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));

	user_flows_assert(user_flows_value('SELECT id FROM `%pbans` WHERE id = '.$ban) === null, 'the ban was not removed');
}


function user_flows_step_maintenance(&$state)
{
	$form = user_flows_get($state, 'admin', 'admin/settings.php?section=maintenance');

	$response = user_flows_submit($state, 'admin', $form, 'name="form[maintenance]"', array(
		'form_sent' => '1',
		'form[maintenance]' => '1',
		'save' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	user_flows_assert((string) user_flows_value('SELECT conf_value FROM `%pconfig` WHERE conf_name = \'o_maintenance\'') === '1',
		'maintenance mode was not switched on');

	// A guest must be turned away; the administrator must still get through.
	$guest = user_flows_get($state, 'guest', 'index.php', array(200, 302, 503));
	user_flows_assert(strpos((string) $guest['body'], 'Maintenance') !== false || strpos((string) $guest['body'], 'maintenance') !== false,
		'a guest was served the forum during maintenance: '.user_flows_summary($guest['body']));

	$admin = user_flows_get($state, 'admin', 'admin/index.php');
	user_flows_assert(strpos((string) $admin['body'], 'admin/settings.php') !== false,
		'the administrator was locked out by maintenance mode');

	$form = user_flows_get($state, 'admin', 'admin/settings.php?section=maintenance');
	$response = user_flows_submit($state, 'admin', $form, 'name="form[maintenance]"', array(
		'form_sent' => '1',
		'form[maintenance]' => '0',
		'save' => '1',
	));

	user_flows_follow($state, 'admin', $response);

	user_flows_assert((string) user_flows_value('SELECT conf_value FROM `%pconfig` WHERE conf_name = \'o_maintenance\'') === '0',
		'maintenance mode was not switched off again');

	$guest = user_flows_get($state, 'guest', 'index.php');
	user_flows_assert(strpos((string) $guest['body'], USER_FLOWS_BOARD_TITLE) !== false,
		'the forum did not come back after maintenance mode');
}


function user_flows_step_reindex(&$state)
{
	$form = user_flows_get($state, 'admin', 'admin/reindex.php');

	$response = user_flows_submit($state, 'admin', $form, 'name="i_per_page"', array(
		'i_per_page' => '50',
		'i_start_at' => '1',
		'i_empty_index' => '1',
	));

	// The rebuild hands itself on one batch at a time.
	for ($cycle = 0; $cycle < 50; ++$cycle)
	{
		$response = user_flows_confirm($state, 'admin', $response);
		$target = user_flows_redirect_target($response['body']);

		if ($target === '')
			break;

		$response = user_flows_request($state, 'admin', user_flows_resolve($response['url'], $target));
	}

	user_flows_assert(user_flows_redirect_target($response['body']) === '',
		'the rebuild never finished');
	user_flows_assert(user_flows_count('search_words') > 0, 'the rebuilt search index is empty');

	$search = user_flows_follow($state, 'admin',
		user_flows_get($state, 'admin', 'search.php?action=search&keywords='.USER_FLOWS_KEYWORD.'&show_as=topics'));

	user_flows_assert(strpos((string) $search['body'], USER_FLOWS_SUBJECT) !== false,
		'the topic is not findable after the rebuild');
}


function user_flows_step_feeds(&$state)
{
	$markers = array(
		'rss' => '<rss version="2.0"',
		'atom' => '<feed xmlns="http://www.w3.org/2005/Atom">',
		'xml' => '<source>',
		'html' => '<a href=',
	);

	foreach ($markers as $type => $marker)
	{
		$response = user_flows_get($state, 'guest', 'extern.php?action=feed&type='.$type);

		user_flows_assert(strpos((string) $response['body'], $marker) !== false,
			'the '.$type.' feed does not look like one: '.user_flows_summary($response['body']));

		user_flows_assert(strpos((string) $response['body'], USER_FLOWS_SUBJECT) !== false,
			'the '.$type.' feed does not carry the topic');
	}

	// A single topic's feed walks the other branch of extern.php.
	$response = user_flows_get($state, 'guest', 'extern.php?action=feed&tid='.$state['topic_id'].'&type=rss');
	user_flows_assert(strpos((string) $response['body'], USER_FLOWS_REPLY) !== false,
		'the topic feed does not carry its posts');
}


function user_flows_steps()
{
	return array(
		array('register', 'user_flows_step_register'),
		array('login', 'user_flows_step_login'),
		array('new topic', 'user_flows_step_new_topic'),
		array('reply', 'user_flows_step_reply'),
		array('quote, bbcode, idn link', 'user_flows_step_quote'),
		array('4-byte character', 'user_flows_step_emoji'),
		array('edit post', 'user_flows_step_edit'),
		array('delete post', 'user_flows_step_delete'),
		array('search', 'user_flows_step_search'),
		array('profile identity', 'user_flows_step_profile_identity'),
		array('profile settings', 'user_flows_step_profile_settings'),
		array('signature', 'user_flows_step_signature'),
		array('avatar upload', 'user_flows_step_avatar'),
		array('logout', 'user_flows_step_logout'),
		array('admin settings', 'user_flows_step_admin_settings'),
		array('admin forum create', 'user_flows_step_forum_create'),
		array('moderate move', 'user_flows_step_moderate_move'),
		array('moderate close/open', 'user_flows_step_moderate_close'),
		array('moderate stick/unstick', 'user_flows_step_moderate_sticky'),
		array('admin forum delete', 'user_flows_step_forum_delete'),
		array('admin ban', 'user_flows_step_ban'),
		array('maintenance mode', 'user_flows_step_maintenance'),
		array('search index rebuild', 'user_flows_step_reindex'),
		array('syndication feeds', 'user_flows_step_feeds'),
	);
}


/**
 * A forum of this run's own, installed over HTTP. Returns '' on success.
 */
function user_flows_install($base_url, &$diagnostics)
{
	$spec = user_flows_spec();

	@unlink(USER_FLOWS_ROOT.'config.php');
	install_matrix_clear_cache();
	install_matrix_drop_schema($spec);

	$jar = (string) tempnam(sys_get_temp_dir(), 'flows');
	$response = smoke_request($base_url.'/admin/install.php', $jar, install_matrix_form_fields('mysqli', $spec, $base_url));
	@unlink($jar);

	$diagnostics = array_merge($diagnostics, smoke_diagnostics($response['body']));

	if ($response['status'] !== 200)
		return 'the installer returned HTTP '.$response['status'].($response['error'] !== '' ? ' ('.$response['error'].')' : '');

	if (!install_matrix_install_succeeded($response['body']))
		return 'the install did not complete: '.install_matrix_failure_reason($response['body']);

	return '';
}


/**
 * IDNA is opt-in: admin/install.php writes the define into config.php commented
 * out. The pass turns it on the way that comment tells an administrator to, so
 * the UTS-46 conversion is actually walked. Returns '' on success.
 */
function user_flows_enable_idna()
{
	$config = USER_FLOWS_ROOT.'config.php';
	$body = (string) @file_get_contents($config);
	$commented = "//define('FORUM_ENABLE_IDNA', 1);";

	if (strpos($body, $commented) === false)
		return 'config.php carries no commented-out FORUM_ENABLE_IDNA line';

	file_put_contents($config, str_replace($commented, substr($commented, 2), $body));
	install_matrix_clear_cache();

	return '';
}


/**
 * The forum throttles by wall-clock time, which a scripted pass has none of:
 * flood control between posts and searches, and one registration per IP per
 * hour — the install itself created the administrator from the same address.
 * Relaxed in the database rather than through the admin interface: this is
 * setup, not one of the flows under test.
 */
function user_flows_relax_throttles()
{
	$spec = user_flows_spec();
	$link = install_matrix_mysql($spec);

	mysqli_query($link, 'UPDATE `'.$spec['prefix'].'groups` SET g_post_flood = 0, g_search_flood = 0, g_email_flood = 0');
	mysqli_query($link, 'UPDATE `'.$spec['prefix'].'users` SET registered = registered - 7200 WHERE registered > 7200');
	mysqli_close($link);
}


/** Whatever the run left in img/avatars for its member. */
function user_flows_clear_avatars($user_id)
{
	foreach (array('png', 'jpg', 'gif', 'tmp') as $extension)
		@unlink(USER_FLOWS_ROOT.'img/avatars/'.$user_id.'.'.$extension);
}


/** The whole pass. Returns the list of failures, empty when it went green. */
function user_flows_run($base_url, $log)
{
	$failures = array();
	$diagnostics = array();

	install_matrix_truncate_log($log);

	$reason = user_flows_install($base_url, $diagnostics);

	if ($reason !== '')
	{
		user_flows_teardown(0);
		return array_merge(array($reason), $diagnostics);
	}

	$reason = user_flows_enable_idna();

	if ($reason !== '')
	{
		user_flows_teardown(0);
		return array_merge(array($reason), $diagnostics);
	}

	user_flows_relax_throttles();

	$state = array(
		'base_url' => $base_url,
		'jars' => array(
			'member' => (string) tempnam(sys_get_temp_dir(), 'flowm'),
			'admin' => (string) tempnam(sys_get_temp_dir(), 'flowa'),
			'guest' => (string) tempnam(sys_get_temp_dir(), 'flowg'),
		),
		'diagnostics' => array(),
		'user_id' => 0,
		'topic_id' => 0,
		'first_post_id' => 0,
		'reply_id' => 0,
		'quote_id' => 0,
		'new_forum_id' => 0,
		'emoji_stored' => false,
	);

	$reason = install_matrix_login($base_url, $state['jars']['admin'], $state['diagnostics']);

	if ($reason !== '')
		$failures[] = 'the administrator could not log in: '.$reason;

	$steps = user_flows_steps();
	$width = max(array_map(static fn(array $step): int => strlen($step[0]), $steps)) + 2;

	foreach ($steps as $step)
	{
		list($label, $run) = $step;

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

	if ($state['emoji_stored'])
		echo "   note  the database stored a 4-byte character: the utf8mb3 limit is gone\n";

	foreach ($state['jars'] as $jar)
		@unlink($jar);

	$diagnostics = array_merge($diagnostics, $state['diagnostics'], install_matrix_log_diagnostics($log));

	foreach (array_unique($diagnostics) as $line)
		$failures[] = $line;

	user_flows_teardown($state['user_id']);

	return $failures;
}


/** Everything the run created, removed on every exit from user_flows_run(). */
function user_flows_teardown($user_id)
{
	user_flows_clear_avatars($user_id);
	// The schema goes; a config.php naming it would only serve the error page.
	@unlink(USER_FLOWS_ROOT.'config.php');
	install_matrix_drop_schema(user_flows_spec());
	install_matrix_clear_cache();
}


function user_flows_main($base_url, $log)
{
	echo 'user flows on '.$base_url.' (PHP '.PHP_VERSION.")\n\n";

	try
	{
		$failures = user_flows_run($base_url, $log);
	}
	catch (Throwable $e)
	{
		$failures = array(get_class($e).': '.$e->getMessage());
	}

	echo "\n";

	if ($failures)
	{
		echo count($failures)." failure(s):\n";
		foreach ($failures as $failure)
			echo '  - '.$failure."\n";

		return 1;
	}

	echo count(user_flows_steps())." flow(s) passed\n";

	return 0;
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
{
	install_matrix_stash_config();

	exit(user_flows_main(
		rtrim(getenv('PUNBB_TEST_BASE_URL') ?: 'http://localhost', '/'),
		(string) getenv('PUNBB_TEST_ERROR_LOG')
	));
}
