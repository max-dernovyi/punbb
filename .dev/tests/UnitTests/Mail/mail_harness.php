<?php
/**
 * Sends one message through the real forum_mail() and prints what it returned.
 *
 * Out of process because error() exits, and because the relay a test points
 * it at answers from another process while this one waits. $argv[1] is a JSON
 * object: smtp_host ('' for mail()), smtp_user, smtp_pass, quiet, debug, to,
 * reply_to, and pre_send, extension code for em_fn_forum_mail_pre_send.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

define('FORUM_ROOT', dirname(__DIR__, 4).'/');
define('FORUM', 1);

$options = json_decode($argv[1], true) + array(
	'smtp_host' => '',
	'smtp_user' => '',
	'smtp_pass' => '',
	'quiet' => false,
	'debug' => false,
	'to' => 'someone@example.com',
	'reply_to' => '',
	'pre_send' => '',
);

if ($options['debug'])
	define('FORUM_DEBUG', 1);

require FORUM_ROOT.'include/autoload.php';
require FORUM_ROOT.'include/constants.php';
require FORUM_ROOT.'include/functions.php';
require FORUM_ROOT.'include/utf8.php';
require FORUM_ROOT.'lang/English/common.php';
require FORUM_ROOT.'include/email.php';

$forum_config = array(
	'o_board_title'		=> 'Harness board',
	'o_webmaster_email'	=> 'forum@example.com',
	'o_smtp_host'		=> $options['smtp_host'],
	'o_smtp_user'		=> $options['smtp_user'],
	'o_smtp_pass'		=> $options['smtp_pass'],
	'o_smtp_ssl'		=> '0',
);

if ($options['pre_send'] !== '')
{
	$forum_hooks['em_fn_forum_mail_pre_send'] = array($options['pre_send']);

	// get_hook() announces that the point is deprecated; that is not this harness's subject.
	set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);
}

$result = forum_mail($options['to'], 'Subject Ümlaut', "Line one\n.Line two", $options['reply_to'], 'Replier', $options['quiet']);

echo 'RESULT=', var_export($result, true), "\n";
