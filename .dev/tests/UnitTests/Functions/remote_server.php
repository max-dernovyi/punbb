<?php
/**
 * An HTTP server for RemoteFileTest: listens on 127.0.0.1, over TLS when it is
 * given a certificate, and answers by path.
 *
 *   php remote_server.php [certificate.pem]
 *
 * Prints the port it listens on and serves one connection at a time until it
 * is terminated.
 *
 *   /ok          200, REMOTE_SERVER_BODY
 *   /echo        200, the request line and headers it was sent
 *   /moved       302 to /ok, relative
 *   /moved-file  302 to file:///etc/passwd
 *   /moved-loop  302 to itself
 *   /silent      reads the request and never answers
 *   anything     404
 *
 * A plain server answers a TLS handshake with a cleartext 200, so a client
 * that gave up on TLS would read a body.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

const REMOTE_SERVER_BODY = 'remote body';


/** The response to a request head, or null for one that is never answered. */
function remote_server_response($head)
{
	if (!preg_match('#\A([A-Z]+) (\S+) HTTP/1\.[01]\r\n#', $head, $matches))
		return "HTTP/1.0 200 OK\r\nContent-Length: 9\r\n\r\ncleartext";

	list(, $method, $path) = $matches;

	switch ($path)
	{
		case '/ok':
			$status = '200 OK';
			$body = REMOTE_SERVER_BODY;
			break;

		case '/echo':
			$status = '200 OK';
			$body = $head;
			break;

		case '/moved':
			return "HTTP/1.1 302 Found\r\nLocation: /ok\r\nContent-Length: 0\r\n\r\n";

		case '/moved-file':
			return "HTTP/1.1 302 Found\r\nLocation: file:///etc/passwd\r\nContent-Length: 0\r\n\r\n";

		case '/moved-loop':
			return "HTTP/1.1 302 Found\r\nLocation: /moved-loop\r\nContent-Length: 0\r\n\r\n";

		case '/silent':
			return null;

		default:
			$status = '404 Not Found';
			$body = 'missing';
	}

	return 'HTTP/1.1 '.$status."\r\nContent-Type: text/plain\r\nContent-Length: ".strlen($body)."\r\n\r\n".($method === 'HEAD' ? '' : $body);
}


/** Reads a request head, or whatever a client that does not speak HTTP sent first. */
function remote_server_read_head($client)
{
	$head = '';

	while (!str_contains($head, "\r\n\r\n") && !feof($client))
	{
		$chunk = fread($client, 8192);
		if ($chunk === false || $chunk === '')
			break;

		$head .= $chunk;

		if (!preg_match('#\A[A-Z]#', $head))
			break;
	}

	return $head;
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
{
	$certificate = $argv[1] ?? '';
	$context = stream_context_create($certificate !== '' ? array('ssl' => array('local_cert' => $certificate)) : array());

	$server = stream_socket_server(($certificate !== '' ? 'ssl' : 'tcp').'://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
	if ($server === false)
	{
		fwrite(STDERR, $errstr."\n");
		exit(1);
	}

	$name = (string) stream_socket_get_name($server, false);
	echo substr($name, strrpos($name, ':') + 1), "\n";
	fflush(STDOUT);

	while (true)
	{
		// A client that refuses the certificate fails the handshake here.
		$client = @stream_socket_accept($server, 3600);
		if ($client === false)
			continue;

		stream_set_timeout($client, 10);
		$response = remote_server_response(remote_server_read_head($client));

		if ($response === null)
		{
			while (!feof($client) && @fread($client, 8192) !== false && !stream_get_meta_data($client)['timed_out']);
		}
		else
			@fwrite($client, $response);

		@fclose($client);
	}
}
