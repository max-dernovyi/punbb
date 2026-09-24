<?php
/**
 * An SMTP relay for the mail tests: listens on 127.0.0.1 and records what the
 * forum hands it, so a test reads the envelope and the message a real relay
 * would have been given.
 *
 *   php .dev/tests/Integration/smtp_relay.php <transcript> [accept|refuse|reject]
 *
 * Prints the port it listens on and serves until it is terminated. Every
 * conversation is appended to <transcript> as one JSON line: the commands in
 * order, the AUTH LOGIN username, the envelope sender, the recipients the relay
 * accepted, and the data. It offers STARTTLS without being able to start it,
 * so a client that takes the offer fails. `refuse` greets with
 * SMTP_RELAY_REFUSAL instead of 220; `reject` answers every RCPT with
 * SMTP_RELAY_REJECTION. Both carry markup, so a test can see whether the forum
 * encodes what a server says.
 *
 * Run it in the container the forum runs in: the forum dials 127.0.0.1.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

const SMTP_RELAY_REFUSAL = '554 <b>relay</b> refuses "everyone"';
const SMTP_RELAY_REJECTION = '550 <i>no</i> such mailbox';


/**
 * A relay in a process of its own, ready to accept. Returns what the other
 * smtp_relay_* functions take.
 *
 * @return array{process: resource, pipes: array<int, resource>, port: int, transcript: string}
 */
function smtp_relay_start($mode = 'accept')
{
	$transcript = (string) tempnam(sys_get_temp_dir(), 'relay');
	$process = proc_open(array(PHP_BINARY, __FILE__, $transcript, $mode), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	if (!is_resource($process))
		throw new RuntimeException('the relay did not start');

	$port = (int) fgets($pipes[1]);

	if ($port <= 0)
	{
		$reason = stream_get_contents($pipes[2]);
		proc_terminate($process);
		throw new RuntimeException('the relay did not report a port: '.$reason);
	}

	return array('process' => $process, 'pipes' => $pipes, 'port' => $port, 'transcript' => $transcript);
}


/**
 * Every conversation the relay has finished, oldest first.
 *
 * @return list<array{commands: list<string>, user: string, from: string, to: list<string>, data: string, refused: bool}>
 */
function smtp_relay_records($relay)
{
	$records = array();

	foreach (file($relay['transcript'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array() as $line)
		$records[] = json_decode($line, true);

	return $records;
}


/**
 * The records once there are at least $count of them, or whatever there is
 * after $seconds: the forum may send after the response is finished.
 */
function smtp_relay_wait($relay, $count, $seconds = 10)
{
	$deadline = microtime(true) + $seconds;

	while (count($records = smtp_relay_records($relay)) < $count && microtime(true) < $deadline)
		usleep(50000);

	return $records;
}


function smtp_relay_stop($relay)
{
	proc_terminate($relay['process']);

	foreach ($relay['pipes'] as $pipe)
		fclose($pipe);

	proc_close($relay['process']);
	@unlink($relay['transcript']);
}


/** A header of a message the relay recorded, unfolded, or '' when it has none. */
function smtp_relay_header($data, $name)
{
	$head = substr($data, 0, (int) strpos($data."\r\n\r\n", "\r\n\r\n"));
	$head = preg_replace('#\r\n[ \t]+#', ' ', $head);

	return preg_match('#^'.preg_quote($name, '#').':[ \t]*(.*)$#mi', $head, $match) ? rtrim($match[1]) : '';
}


/** The body of a message the relay recorded, as the forum wrote it. */
function smtp_relay_body($data)
{
	return (string) substr($data, (int) strpos($data, "\r\n\r\n") + 4);
}


/** One conversation, recorded when it ends. */
function smtp_relay_session($connection, $mode)
{
	$record = array('commands' => array(), 'user' => '', 'from' => '', 'to' => array(), 'data' => '', 'refused' => false);

	if ($mode === 'refuse')
	{
		fwrite($connection, SMTP_RELAY_REFUSAL."\r\n");
		$record['refused'] = true;

		// RFC 5321 3.1: after a 554 greeting the server waits for QUIT.
		if (($line = fgets($connection)) !== false && strtoupper(rtrim($line)) === 'QUIT')
		{
			$record['commands'][] = 'QUIT';
			fwrite($connection, "221 bye\r\n");
		}

		return $record;
	}

	fwrite($connection, "220 relay.test ESMTP\r\n");

	while (($line = fgets($connection)) !== false)
	{
		$command = strtoupper((string) strtok(rtrim($line, "\r\n"), ' '));
		$record['commands'][] = $command;

		if ($command === 'EHLO')
			fwrite($connection, "250-relay.test\r\n250-STARTTLS\r\n250-AUTH LOGIN\r\n250 8BITMIME\r\n");
		else if ($command === 'AUTH')
		{
			fwrite($connection, "334 VXNlcm5hbWU6\r\n");
			$record['user'] = (string) base64_decode(rtrim((string) fgets($connection)));
			fwrite($connection, "334 UGFzc3dvcmQ6\r\n");
			fgets($connection);
			fwrite($connection, "235 ok\r\n");
		}
		else if ($command === 'MAIL')
		{
			$record['from'] = preg_match('#<([^>]*)>#', $line, $match) ? $match[1] : '';
			fwrite($connection, "250 ok\r\n");
		}
		else if ($command === 'RCPT')
		{
			if ($mode === 'reject')
				fwrite($connection, SMTP_RELAY_REJECTION."\r\n");
			else
			{
				$record['to'][] = preg_match('#<([^>]*)>#', $line, $match) ? $match[1] : '';
				fwrite($connection, "250 ok\r\n");
			}
		}
		else if ($command === 'DATA')
		{
			fwrite($connection, "354 go on\r\n");

			$data = '';
			while (($line = fgets($connection)) !== false && $line !== ".\r\n")
				$data .= (substr($line, 0, 1) === '.') ? substr($line, 1) : $line;

			$record['data'] = $data;
			fwrite($connection, "250 queued\r\n");
		}
		else if ($command === 'QUIT')
		{
			fwrite($connection, "221 bye\r\n");
			break;
		}
		else
			fwrite($connection, "250 ok\r\n");
	}

	return $record;
}


function smtp_relay_serve($transcript, $mode)
{
	$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

	if ($server === false)
	{
		fwrite(STDERR, 'cannot listen: '.$errstr."\n");
		exit(1);
	}

	$name = (string) stream_socket_get_name($server, false);
	echo substr($name, strrpos($name, ':') + 1), "\n";
	fflush(STDOUT);

	while (true)
	{
		$connection = @stream_socket_accept($server, 3600);

		if ($connection === false)
			continue;

		stream_set_timeout($connection, 30);
		$record = smtp_relay_session($connection, $mode);
		fclose($connection);

		file_put_contents($transcript, json_encode($record)."\n", FILE_APPEND | LOCK_EX);
	}
}


if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
	smtp_relay_serve($argv[1], $argv[2] ?? 'accept');
