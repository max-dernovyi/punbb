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
// PHPMailer, judging an address the way the forum does
//
// forum_mail_recipients() decides what may enter an envelope. Without this the
// library would apply filter_var() on top and drop addresses the forum
// accepts, or keep ones an extension's em_fn_is_valid_email_start refuses.
//
class ForumMailer extends \PHPMailer\PHPMailer\PHPMailer
{
	public static function validateAddress($address, $patternselect = null)
	{
		return (bool) is_valid_email($address);
	}
}


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
// Send a message through the configured SMTP relay, or through PHP's mail()
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

	// The headers em_fn_forum_mail_pre_send has always been shown. The
	// library encodes the message itself, $subject included.
	$from = "=?UTF-8?B?".base64_encode($from_name)."?=".' <'.$from_email.'>';

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

	// The hook may have rewritten $to, so the envelope is filtered again. A
	// hook that leaves no recipient suppresses the mail.
	$recipients = forum_mail_recipients($to);
	if (empty($recipients))
		return true;

	$mail = new ForumMailer(true);
	$mail->CharSet = 'UTF-8';
	$mail->XMailer = 'PunBB Mailer';
	$mail->AllowEmpty = true;

	if ($forum_config['o_smtp_host'] != '')
	{
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

		$mail->isSMTP();
		$mail->Host = $smtp_host;
		$mail->Port = $smtp_port;
		$mail->Timeout = 15;

		// The relay was never asked for STARTTLS: upgrading on its own offer
		// would verify a certificate nobody configured and stop working mail.
		$mail->SMTPAutoTLS = false;

		if ($forum_config['o_smtp_ssl'] == '1')
			$mail->SMTPSecure = ForumMailer::ENCRYPTION_SMTPS;

		if ($forum_config['o_smtp_user'] != '' && $forum_config['o_smtp_pass'] != '')
		{
			$mail->SMTPAuth = true;
			$mail->Username = $forum_config['o_smtp_user'];
			$mail->Password = $forum_config['o_smtp_pass'];
		}
	}

	try
	{
		$mail->setFrom($from_email, $from_name, false);

		foreach ($recipients as $cur_recipient)
			$mail->addAddress($cur_recipient);

		if ($reply_to_email !== '')
			$mail->addReplyTo($reply_to_email, $reply_to_name);

		// The hook saw the hand-built headers. The library writes its own, so
		// Content-type, From and Reply-To reach it as settings, what the hook
		// added is carried over, and nothing naming a recipient: the envelope
		// is $to's, and sendmail -t reads Resent-To/Cc/Bcc.
		foreach (preg_split('#\r?\n(?![ \t])#', $headers) as $cur_header)
		{
			$cur_header = explode(':', preg_replace('#\r?\n[ \t]+#', ' ', $cur_header), 2);
			$cur_name = count($cur_header) == 2 ? strtolower(trim($cur_header[0])) : '';
			$cur_value = count($cur_header) == 2 ? trim($cur_header[1]) : '';

			if ($cur_name === 'content-type' && strcasecmp($cur_value, 'text/plain; charset=utf-8') !== 0)
			{
				// The library appends the charset, so it is taken out of the
				// value; anything else in it, a multipart boundary, stays.
				if (preg_match('#;\s*charset\s*=\s*"?([^";\s]+)"?#i', $cur_value, $matches))
					$mail->CharSet = $matches[1];

				$content_type = trim(preg_replace('#;\s*charset\s*=\s*"?[^";\s]+"?#i', '', $cur_value));
				if ($content_type !== '')
					$mail->ContentType = $content_type;
			}
			else if (($cur_name === 'from' && $cur_value !== $from) || ($cur_name === 'reply-to' && $cur_value !== ($reply_to ?? null)))
			{
				// A rewritten sender is taken only when it is an address the
				// forum accepts.
				if (preg_match('#^(.*?)<([^<>]*)>$#', $cur_value, $matches))
					list($address_name, $address) = array(mb_decode_mimeheader(trim($matches[1], " \t\"")), trim($matches[2]));
				else
					list($address_name, $address) = array('', $cur_value);

				if (!is_valid_email($address))
					continue;

				if ($cur_name === 'from')
				{
					// The SMTP envelope keeps the forum's sender, as it always had.
					if ($mail->Mailer == 'smtp')
						$mail->Sender = $from_email;

					$mail->setFrom($address, $address_name, false);
				}
				else
				{
					$mail->clearReplyTos();
					$mail->addReplyTo($address, $address_name);
				}
			}
			else if ($cur_name !== '' && !in_array($cur_name, array('from', 'date', 'mime-version', 'content-transfer-encoding', 'content-type', 'x-mailer', 'reply-to', 'to', 'cc', 'bcc', 'resent-to', 'resent-cc', 'resent-bcc', 'subject', 'message-id'), true))
				$mail->addCustomHeader($cur_header[0], $cur_header[1]);
		}

		$mail->Subject = $subject;
		$mail->Body = $message;

		$mail->send();
	}
	catch (\PHPMailer\PHPMailer\Exception $e)
	{
		// A quiet caller answers the same for every address, so a relay that
		// is down must not turn the send into the tell. mail() failing never
		// rendered a page: the local mailer is the host's to report.
		if ($quiet || $mail->Mailer != 'smtp')
			return false;

		error('Unable to send e-mail.<br />Please contact the forum administrator.'.(defined('FORUM_DEBUG') ? ' The SMTP server "'.forum_htmlencode($forum_config['o_smtp_host']).'" reported: "'.forum_htmlencode($mail->ErrorInfo).'".' : ''), __FILE__, __LINE__);
	}

	return true;
}

define('FORUM_EMAIL_FUNCTIONS_LOADED', 1);
