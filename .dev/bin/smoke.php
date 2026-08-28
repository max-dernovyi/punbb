<?php
/**
 * HTTP smoke sweep over every forum entry point.
 *
 * Reports the status code of each request and every PHP diagnostic the response
 * body contains. Exits non-zero on a fatal and on any Deprecated/Warning/Notice
 * line: the sweep is the deprecation gate for the migration.
 *
 * It is also the asset gate: every script/stylesheet URL a page renders must
 * resolve to a file in the checkout, and no page may still reference $LAB.
 *
 * Compile-time diagnostics surface only on a cold opcache, so restart the web
 * container before sweeping (`make smoke` does).
 *
 * Usage: php .dev/bin/smoke.php [base-url]
 *   base-url  defaults to $SMOKE_BASE_URL, then http://localhost
 *   $SMOKE_USER / $SMOKE_PASS  when set, the sweep runs a second, logged-in pass
 *   $SMOKE_RESOLVE  curl resolve entries ("host:port:addr", comma separated), for
 *                   sweeping a base URL the container cannot resolve itself
 *
 * The CSRF token is bound to $base_url, so the logged-in pass only works when the
 * sweep reaches the forum on that exact URL.
 */

// Ids match a fresh install: forum 1 "Test forum", topic 1 "Test post", user 2 admin.
function smoke_targets()
{
	return array(
		'index.php',
		'viewforum.php?id=1',
		'viewtopic.php?id=1',
		'post.php?fid=1',
		'post.php?tid=1',
		'login.php',
		'login.php?action=forget',
		'register.php',
		'profile.php?id=2',
		'search.php',
		'search.php?action=show_recent',
		'userlist.php',
		'misc.php?action=rules',
		'misc.php?action=markread',
		'moderate.php?fid=1',
		'extern.php?action=feed&type=rss',
		'help.php',
		'admin/index.php',
	);
}


function smoke_request($url, $jar, $post = null, $resolve = array())
{
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_USERAGENT => 'punbb-smoke',
		CURLOPT_COOKIEFILE => $jar,
		CURLOPT_COOKIEJAR => $jar,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => 0,
	));

	if (!empty($resolve))
		curl_setopt($ch, CURLOPT_RESOLVE, $resolve);

	if ($post !== null)
		curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post));

	// Response headers are captured because not every redirect in the forum
	// renders a page: search_functions.php sends a bare Location and no body.
	$headers = array();
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($handle, $line) use (&$headers): int {
		if (strpos($line, ':') !== false)
		{
			list($name, $value) = explode(':', $line, 2);
			$headers[strtolower(trim($name))] = trim($value);
		}

		return strlen($line);
	});

	$body = curl_exec($ch);
	$result = array(
		'body' => $body,
		'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
		'error' => curl_error($ch),
		'headers' => $headers,
	);

	return $result;
}


// Every PHP diagnostic in a response body, deduplicated and stripped of markup.
function smoke_diagnostics($body)
{
	$pattern = '#(?:<b>)?(Fatal error|Parse error|Warning|Deprecated|Notice|Strict Standards)(?:</b>)?\s*:\s*(.*?)(?:<br|\n|$)#i';
	$found = array();

	if (preg_match_all($pattern, (string) $body, $matches, PREG_SET_ORDER))
		foreach ($matches as $match)
			$found[] = ucfirst(strtolower($match[1])).': '.trim(strip_tags($match[2]));

	return array_values(array_unique($found));
}


// Every script/stylesheet URL a page renders, deduplicated and entity-decoded.
function smoke_asset_urls($body)
{
	$found = array();

	if (preg_match_all('#<script[^>]+src\s*=\s*"([^"]+)"#i', (string) $body, $matches))
		$found = $matches[1];

	if (preg_match_all('#<link[^>]+rel\s*=\s*"[^"]*stylesheet[^"]*"[^>]*>#i', (string) $body, $tags))
		foreach ($tags[0] as $tag)
			if (preg_match('#href\s*=\s*"([^"]+)"#i', $tag, $href))
				$found[] = $href[1];

	return array_values(array_unique(array_map(
		static fn(string $url): string => html_entity_decode($url, ENT_QUOTES, 'UTF-8'),
		$found
	)));
}


// The asset URLs of a page that have no file behind them in the checkout.
// Off-site URLs are somebody else's problem and are left alone. $bases lists the
// origins that map onto $root: the sweep URL and the forum's own $base_url, which
// are not the same string when the sweep reaches the forum on some other address.
function smoke_missing_assets($body, $bases, $root)
{
	// Each base also matches schemeless: a protocol-relative URL on our own
	// host is ours to resolve, and skipping it would blind the gate.
	$prefixes = array();
	foreach ((array) $bases as $base)
	{
		if (($base = rtrim((string) $base, '/')) === '')
			continue;

		$prefixes[] = $base.'/';

		$schemeless = preg_replace('#^[a-z][a-z0-9+.-]*:#i', '', $base);
		if ($schemeless !== $base && $schemeless !== '')
			$prefixes[] = $schemeless.'/';
	}

	$missing = array();

	foreach (smoke_asset_urls($body) as $url)
	{
		$path = strtok($url, '?#');

		foreach ($prefixes as $prefix)
			if (strpos((string) $path, $prefix) === 0)
			{
				$path = substr((string) $path, strlen($prefix));
				break;
			}

		// Anything still absolute points at another host: not ours to resolve.
		if ($path === false || $path === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path))
			continue;

		if (!is_file(rtrim($root, '/').'/'.ltrim($path, '/')))
			$missing[] = $url;
	}

	return array_values(array_unique($missing));
}


// LABjs is gone; a page still emitting its loader chain means a stale asset.
function smoke_labjs_references($body)
{
	return strpos((string) $body, '$LAB') !== false;
}


function smoke_is_fatal($diagnostic)
{
	return stripos($diagnostic, 'fatal error:') === 0 || stripos($diagnostic, 'parse error:') === 0;
}


// $base_url out of the forum's own config.php, or '' when it cannot be read.
// Parsed rather than included: config.php has side effects (constants, FORUM).
function smoke_forum_base_url()
{
	$config = dirname(__DIR__, 2).'/config.php';

	if (!is_readable($config) || !preg_match('/\$base_url\s*=\s*([\'"])(.*?)\1/', (string) file_get_contents($config), $match))
		return '';

	return rtrim($match[2], '/');
}


function smoke_login($base, $jar, $user, $pass, $resolve, &$diagnostics, &$fatals)
{
	// The form token is hashed over $base_url, the check hashes the URL the
	// server observes. A sweep reaching the forum on some other URL therefore
	// cannot produce an accepted token — the one tolerated environment gap.
	$forum_base = smoke_forum_base_url();

	if ($forum_base === '')
		return 'no $base_url in config.php: cannot tell a CSRF regression from a base-URL mismatch';

	if ($forum_base !== rtrim($base, '/'))
		return 'CSRF: the sweep URL is not $base_url ('.$forum_base.')';

	$form = smoke_request($base.'/login.php', $jar, null, $resolve);
	if (!preg_match('/name="csrf_token" value="([^"]+)"/', (string) $form['body'], $match))
		return 'login.php rendered no csrf token';

	$response = smoke_request($base.'/login.php', $jar, array(
		'form_sent' => '1',
		'csrf_token' => $match[1],
		'req_username' => $user,
		'req_password' => $pass,
		'redirect_url' => $base.'/index.php',
	), $resolve);

	// The POST response is swept like any other request: a fatal on the login
	// path must fail the run even though the pass itself may not start.
	foreach (smoke_diagnostics($response['body']) as $line)
	{
		echo '      '.$line."\n";
		$diagnostics[$line][] = 'login POST';

		if (smoke_is_fatal($line))
			$fatals[] = 'login POST -> '.$line;
	}

	if (!in_array($response['status'], array(200, 302), true))
		return 'login returned '.$response['status'];

	// A logged-in page carries a logout link; a rejected login leaves the guest nav.
	$index = smoke_request($base.'/index.php', $jar, null, $resolve);
	if (strpos((string) $index['body'], 'logout') !== false)
		return '';

	// The confirm form posts back to get_current_url(), which is now $base_url's
	// origin plus REQUEST_URI. A mismatch with the URL the sweep posted to means
	// config.php's $base_url is not the address the sweep used, not a forum bug.
	// Same URL on both sides means token generation, persistence or validation
	// really is broken.
	if (strpos((string) $response['body'], 'name="prev_url"') !== false)
	{
		$observed = preg_match('/<form[^>]*class="frm-form"[^>]*action="([^"]*)"/', (string) $response['body'], $action)
			? html_entity_decode($action[1], ENT_QUOTES, 'UTF-8')
			: '';

		if ($observed !== '' && $observed !== $base.'/login.php')
			return 'CSRF: the forum observes '.$observed.', not '.$base.'/login.php';

		return 'login POST hit the CSRF confirm form: the token was rejected';
	}

	return 'login rejected: still anonymous after posting the login form';
}


function smoke_pass($label, $base, $jar, $resolve, &$diagnostics, &$fatals)
{
	$targets = smoke_targets();
	$width = max(array_map('strlen', $targets)) + 2;

	echo '== '.$label." ==\n";

	foreach ($targets as $target)
	{
		$response = smoke_request($base.'/'.$target, $jar, null, $resolve);

		if ($response['body'] === false)
		{
			printf("%-{$width}s  ---  transport error: %s\n", $target, $response['error']);
			$fatals[] = $label.' '.$target.': '.$response['error'];
			continue;
		}

		$found = smoke_diagnostics($response['body']);
		printf("%-{$width}s  %3d  %s\n", $target, $response['status'], $found ? count($found).' diagnostic(s)' : 'clean');

		// With display_errors off a fatal is an empty 500, so the body carries no
		// diagnostic to match — the status is the only signal left.
		if (!in_array($response['status'], array(200, 302), true))
		{
			echo '      unexpected HTTP status '.$response['status']."\n";
			$fatals[] = $label.' '.$target.' -> HTTP '.$response['status'];
		}

		foreach ($found as $line)
		{
			echo '      '.$line."\n";
			$diagnostics[$line][] = $label.' '.$target;

			if (smoke_is_fatal($line))
				$fatals[] = $label.' '.$target.' -> '.$line;
		}

		foreach (smoke_missing_assets($response['body'], array($base, smoke_forum_base_url()), dirname(__DIR__, 2)) as $url)
		{
			echo '      missing asset: '.$url."\n";
			$fatals[] = $label.' '.$target.' -> missing asset '.$url;
		}

		if (smoke_labjs_references($response['body']))
		{
			echo "      \$LAB reference in the rendered page\n";
			$fatals[] = $label.' '.$target.' -> $LAB reference';
		}
	}

	echo "\n";
}


// The sweep gates deprecations, so any diagnostic fails it, not just a fatal.
function smoke_exit_code($diagnostics, $fatals)
{
	return ($diagnostics || $fatals) ? 1 : 0;
}


function smoke_main($base, $user, $pass, $resolve)
{
	$diagnostics = array();
	$fatals = array();
	$jar = tempnam(sys_get_temp_dir(), 'smoke');

	smoke_pass('guest', $base, $jar, $resolve, $diagnostics, $fatals);

	if ($user !== '')
	{
		$failure = smoke_login($base, $jar, $user, $pass, $resolve, $diagnostics, $fatals);
		if ($failure === '')
			smoke_pass('user '.$user, $base, $jar, $resolve, $diagnostics, $fatals);
		else if (strpos($failure, 'CSRF:') === 0)
			// The only tolerated skip, and only because it is an environment gap.
			echo 'skipping the authenticated pass: '.$failure."\n\n";
		else
		{
			echo 'authentication failed: '.$failure."\n\n";
			$fatals[] = 'login: '.$failure;
		}
	}

	@unlink($jar);

	if ($diagnostics)
	{
		echo count($diagnostics)." distinct diagnostic(s):\n";
		foreach ($diagnostics as $line => $where)
			echo '  - '.$line.'  ['.implode(', ', array_unique($where)).']'."\n";
	}
	else
		echo "no diagnostics\n";

	if ($fatals)
	{
		echo "\nFATAL on ".count($fatals)." entry point(s):\n";
		foreach ($fatals as $fatal)
			echo '  - '.$fatal."\n";

		return smoke_exit_code($diagnostics, $fatals);
	}

	echo "\nno fatals\n";

	return smoke_exit_code($diagnostics, $fatals);
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
	exit(smoke_main(
		rtrim($argv[1] ?? getenv('SMOKE_BASE_URL') ?: 'http://localhost', '/'),
		getenv('SMOKE_USER') ?: '',
		getenv('SMOKE_PASS') ?: '',
		array_values(array_filter(array_map('trim', explode(',', (string) getenv('SMOKE_RESOLVE')))))
	));
