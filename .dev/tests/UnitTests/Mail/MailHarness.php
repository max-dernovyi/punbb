<?php
/**
 * Runs mail_harness.php, the real forum_mail() in a process of its own,
 * against a relay of smtp_relay.php in another.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

require_once FORUM_ROOT.'.dev/tests/Integration/smtp_relay.php';

final class MailHarness
{
	/**
	 * What one forum_mail() call printed, with every PHP diagnostic displayed.
	 * $sendmail replaces sendmail_path for the mail() transport; what the shell
	 * says on stderr about it is not the page, so only stdout is returned.
	 *
	 * @param array<string, mixed> $options
	 */
	public static function send(array $options, string $sendmail = ''): string
	{
		$command = array(PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1');

		if ($sendmail !== '')
			array_push($command, '-d', 'sendmail_path='.$sendmail);

		array_push($command, __DIR__.'/mail_harness.php', (string) json_encode($options));

		$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		$output = (string) stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		return $output;
	}

	/**
	 * Sends through a relay in $mode: what forum_mail() printed, and what the
	 * relay recorded.
	 *
	 * @param array<string, mixed> $options
	 * @return array{string, list<array<string, mixed>>}
	 */
	public static function relay(string $mode, array $options): array
	{
		$relay = smtp_relay_start($mode);

		try
		{
			$output = self::send($options + array('smtp_host' => '127.0.0.1:'.$relay['port']));

			// The relay records a conversation once the client has hung up.
			return array($output, smtp_relay_wait($relay, 1, 2));
		}
		finally
		{
			smtp_relay_stop($relay);
		}
	}

	/** A port on 127.0.0.1 nothing listens on. */
	public static function closedPort(): int
	{
		$server = stream_socket_server('tcp://127.0.0.1:0');
		$name = (string) stream_socket_get_name($server, false);
		fclose($server);

		return (int) substr($name, strrpos($name, ':') + 1);
	}
}
