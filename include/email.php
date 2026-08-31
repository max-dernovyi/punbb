<?php
/**
 * Loads functions used in dealing with email addresses and email sending.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */


// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;


//
// Raised when the SMTP conversation fails
//
// forum_mail() decides what a failure looks like: the error page normally, a
// silent false when the caller has to answer the same whether the address is
// registered or not.
//
class ForumMailException extends Exception {}


//
// Validate an e-mail address
//
function is_valid_email($email)
{
	$return = ($hook = get_hook('em_fn_is_valid_email_start')) ? eval($hook) : null;
	if ($return !== null)
		return $return;

	if (strlen($email) > 80)
		return false;

	// The quoted local part excludes what an envelope and a header read: the
	// control characters, the recipient separator and the address delimiters.
	// A space stays legal - it is the one of them SMTP quotes for. /D anchors $
	// to the end of the subject, so a trailing newline is not an address either.
	return preg_match('/^(([^<>()[\]\\.,;:\s@"\']+(\.[^<>()[\]\\.,;:\s@"\']+)*)|("[^"\'\\\\\x00-\x1F\x7F,<>;:@]+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/D', $email);
}


//
// Check if $email is banned
//
function is_banned_email($email)
{
	global $forum_db, $forum_bans;

	$return = ($hook = get_hook('em_fn_is_banned_email_start')) ? eval($hook) : null;
	if ($return !== null)
		return $return;

	foreach ($forum_bans as $cur_ban)
	{
		if ($cur_ban['email'] != '' &&
			($email == $cur_ban['email'] ||
			(strpos($cur_ban['email'], '@') === false && stristr($email, '@'.$cur_ban['email']))))
			return true;
	}

	return false;
}


//
// The addresses of a recipient list that are safe to put in an envelope
//
// o_mailing_list is a comma-separated list an administrator types by hand and
// nothing validates on the way in, so what forum_mail() is handed is not known
// to be addresses at all. Filtering here is what keeps the "RCPT TO" loop and
// the To: header carrying nothing but a validated address.
//
function forum_mail_recipients($to)
{
	$return = ($hook = get_hook('em_fn_forum_mail_recipients_start')) ? eval($hook) : null;
	if ($return !== null)
		return $return;

	$recipients = array();

	foreach (explode(',', is_scalar($to) ? (string) $to : '') as $cur_recipient)
	{
		$cur_recipient = forum_trim($cur_recipient);

		if ($cur_recipient !== '' && is_valid_email($cur_recipient))
			$recipients[] = $cur_recipient;
	}

	return $recipients;
}


//
// Wrapper for PHP's mail()
//
function forum_mail($to, $subject, $message, $reply_to_email = '', $reply_to_name = '', $quiet = false)
{
	global $forum_config, $lang_common;

	// Default sender address
	$from_name = sprintf($lang_common['Forum mailer'], $forum_config['o_board_title']);
	$from_email = $forum_config['o_webmaster_email'];

	($hook = get_hook('em_fn_forum_mail_start')) ? eval($hook) : null;

	// Do a little spring cleaning
	$to = forum_trim(preg_replace('#[\n\r]+#s', '', $to));
	$subject = forum_trim(preg_replace('#[\n\r]+#s', '', $subject));
	$from_email = forum_trim(preg_replace('#[\n\r:]+#s', '', $from_email));
	$from_name = forum_trim(preg_replace('#[\n\r:]+#s', '', str_replace('"', '', $from_name)));
	$reply_to_email = forum_trim(preg_replace('#[\n\r:]+#s', '', $reply_to_email));
	$reply_to_name = forum_trim(preg_replace('#[\n\r:]+#s', '', str_replace('"', '', $reply_to_name)));

	// Nothing but a validated address goes into the envelope or into Reply-To.
	// With no recipient left there is no mail to send.
	$recipients = forum_mail_recipients($to);
	if (empty($recipients))
		return;

	$to = implode(',', $recipients);

	if ($reply_to_email !== '' && !is_valid_email($reply_to_email))
		$reply_to_email = '';

	// Set up some headers to take advantage of UTF-8
	$from = "=?UTF-8?B?".base64_encode($from_name)."?=".' <'.$from_email.'>';
	$subject = "=?UTF-8?B?".base64_encode($subject)."?=";

	$headers = 'From: '.$from."\r\n".'Date: '.gmdate('r')."\r\n".'MIME-Version: 1.0'."\r\n".'Content-transfer-encoding: 8bit'."\r\n".'Content-type: text/plain; charset=utf-8'."\r\n".'X-Mailer: PunBB Mailer';

	// If we specified a reply-to email, we deal with it here
	if (!empty($reply_to_email))
	{
		$reply_to = "=?UTF-8?B?".base64_encode($reply_to_name)."?=".' <'.$reply_to_email.'>';

		$headers .= "\r\n".'Reply-To: '.$reply_to;
	}

	// Make sure all linebreaks are CRLF in message (and strip out any NULL bytes)
	$message = str_replace(array("\n", "\0"), array("\r\n", ''), forum_linebreaks($message));

	($hook = get_hook('em_fn_forum_mail_pre_send')) ? eval($hook) : null;

	if ($forum_config['o_smtp_host'] != '')
	{
		try
		{
			smtp_mail($to, $subject, $message, $headers);
		}
		catch (ForumMailException $e)
		{
			// A quiet caller answers the same for every address, so a relay
			// that is down must not turn the send into the tell.
			if ($quiet)
				return false;

			error($e->getMessage(), __FILE__, __LINE__);
		}
	}
	else
	{
		// Change the linebreaks used in the headers according to OS
		if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN')
			$headers = str_replace("\r\n", "\n", $headers);

		// mail() warns when the handoff fails, and with display_errors on that
		// warning is the tell a quiet caller must not give.
		if ($quiet)
			return (bool) @mail($to, $subject, $message, $headers);

		mail($to, $subject, $message, $headers);
	}
}


//
// This function was originally a part of the phpBB Group forum software phpBB2 (http://www.phpbb.com).
// They deserve all the credit for writing it. I made small modifications for it to suit PunBB and it's coding standards.
//
function server_parse($socket, $expected_response)
{
	$server_response = '';
	while (substr($server_response, 3, 1) != ' ')
	{
		if (!($server_response = @fgets($socket, 256)))
			throw new ForumMailException('Unable to send e-mail.<br />Please contact the forum administrator.'.(defined('FORUM_DEBUG') ? ' No response to the '.forum_htmlencode($expected_response).' command.' : ''));
	}

	if (!(substr($server_response, 0, 3) == $expected_response))
		throw new ForumMailException('Unable to send e-mail.<br />Please contact the forum administrator.'.(defined('FORUM_DEBUG') ? ' Expected '.forum_htmlencode($expected_response).', the SMTP server reported: "'.forum_htmlencode($server_response).'".' : ''));
}


//
// This function was originally a part of the phpBB Group forum software phpBB2 (http://www.phpbb.com).
// They deserve all the credit for writing it. I made small modifications for it to suit PunBB and it's coding standards.
//
function smtp_mail($to, $subject, $message, $headers = '')
{
	global $forum_config;

	$recipients = forum_mail_recipients($to);
	if (empty($recipients))
		return false;

	// Sanitize the message
	$message = str_replace("\r\n.", "\r\n..", $message);
	$message = (substr($message, 0, 1) == '.' ? '.'.$message : $message);

	// Are we using port 25 or a custom port?
	if (strpos($forum_config['o_smtp_host'], ':') !== false)
	{
		list($smtp_host, $smtp_port) = explode(':', $forum_config['o_smtp_host']);
		$smtp_port = (int) $smtp_port;
	}
	else
	{
		$smtp_host = $forum_config['o_smtp_host'];
		$smtp_port = 25;
	}

	if ($forum_config['o_smtp_ssl'] == '1')
		$smtp_host = 'ssl://'.$smtp_host;

	if (!($socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 15)))
		throw new ForumMailException('Unable to send e-mail.<br />Please contact the forum administrator.'.(defined('FORUM_DEBUG') ? ' Could not connect to smtp host "'.forum_htmlencode($forum_config['o_smtp_host']).'" ('.forum_htmlencode((string) $errno).') ('.forum_htmlencode($errstr).').' : ''));

	server_parse($socket, '220');

	if ($forum_config['o_smtp_user'] != '' && $forum_config['o_smtp_pass'] != '')
	{
		@fwrite($socket, 'EHLO '.$smtp_host."\r\n");
		server_parse($socket, '250');

		@fwrite($socket, 'AUTH LOGIN'."\r\n");
		server_parse($socket, '334');

		@fwrite($socket, base64_encode($forum_config['o_smtp_user'])."\r\n");
		server_parse($socket, '334');

		@fwrite($socket, base64_encode($forum_config['o_smtp_pass'])."\r\n");
		server_parse($socket, '235');
	}
	else
	{
		@fwrite($socket, 'HELO '.$smtp_host."\r\n");
		server_parse($socket, '250');
	}

	@fwrite($socket, 'MAIL FROM: <'.$forum_config['o_webmaster_email'].'>'."\r\n");
	server_parse($socket, '250');

	foreach ($recipients as $email)
	{
		@fwrite($socket, 'RCPT TO: <'.$email.'>'."\r\n");
		server_parse($socket, '250');
	}

	@fwrite($socket, 'DATA'."\r\n");
	server_parse($socket, '354');

	@fwrite($socket, 'Subject: '.$subject."\r\n".'To: <'.implode('>, <', $recipients).'>'."\r\n".$headers."\r\n\r\n".$message."\r\n");

	@fwrite($socket, '.'."\r\n");
	server_parse($socket, '250');

	@fwrite($socket, 'QUIT'."\r\n");
	@fclose($socket);

	return true;
}

define('FORUM_EMAIL_FUNCTIONS_LOADED', 1);
