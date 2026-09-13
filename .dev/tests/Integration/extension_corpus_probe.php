<?php
/**
 * The corpus runner's probe, loaded by the forum itself: the runner prepends
 * extension_corpus_probe_prefix() to every stored hook body of a corpus
 * extension. Each call appends the point that fired to a JSON-lines log, and
 * the first call in a request arms a handler that logs every later PHP
 * diagnostic with the extension whose code raised it.
 *
 * Nothing here runs on include: the runner and the unit suite load it for the
 * pure functions.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */


/** One per checkout: the runner truncates it at the start of a run. */
function extension_corpus_probe_log()
{
	return dirname(__DIR__, 3).'/.dev/tmp/extension_corpus/probe.jsonl';
}


/** The statement the runner puts in front of a stored hook body, on its first line so line numbers hold. */
function extension_corpus_probe_prefix($extension, $point)
{
	return 'require_once '.var_export(__FILE__, true).'; extension_corpus_probe('.var_export((string) $extension, true).', '.var_export((string) $point, true).'); ';
}


function extension_corpus_probe($extension, $point)
{
	static $armed = false;

	if (!$armed)
	{
		$armed = true;
		set_error_handler('extension_corpus_probe_error');
		register_shutdown_function('extension_corpus_probe_shutdown');
	}

	extension_corpus_probe_write(array('extension' => $extension, 'point' => $point));
}


/** Records the diagnostic and lets PHP display and log it as it would have. */
function extension_corpus_probe_error($errno, $errstr, $errfile, $errline)
{
	if (error_reporting() & $errno)
	{
		$frames = array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'file');
		extension_corpus_probe_diagnostic($errno, $errstr, $errfile, $errline, $frames);
	}

	return false;
}


/** A fatal never reaches the error handler; an uncaught throwable carries its frames in the message. */
function extension_corpus_probe_shutdown()
{
	$error = error_get_last();

	if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true))
		extension_corpus_probe_diagnostic($error['type'], $error['message'], $error['file'], $error['line'], extension_corpus_probe_trace_files($error['message']));
}


/** The call-site files of the "Stack trace:" PHP appends to an uncaught throwable, innermost first. */
function extension_corpus_probe_trace_files($message)
{
	$trace = strstr((string) $message, "\nStack trace:\n");

	return $trace !== false && preg_match_all('/^#\d+ (.+?)\(\d+\): /m', $trace, $matches) ? $matches[1] : array();
}


function extension_corpus_probe_diagnostic($errno, $errstr, $errfile, $errline, $frames)
{
	extension_corpus_probe_write(array(
		'extension' => extension_corpus_probe_owner($errfile, $frames, $GLOBALS['ext_info_stack'] ?? array(), $GLOBALS['ext_info'] ?? array(), dirname(__DIR__, 3).'/extensions/'),
		'text' => extension_corpus_probe_text($errno, $errstr, $errfile, $errline),
	));
}


/**
 * The extension a diagnostic belongs to, or '' for forum code. The innermost
 * frame that is extension code decides: an eval()'d body belongs to the
 * extension on top of ext_info_stack (or, for <install>/<uninstall>, to
 * $ext_info), a file under extensions/ to its folder.
 */
function extension_corpus_probe_owner($file, $frames, $ext_info_stack, $ext_info, $extensions_dir)
{
	foreach (array_merge(array($file), $frames) as $cur_file)
	{
		$cur_file = (string) $cur_file;

		if (strpos($cur_file, 'eval()\'d code') !== false)
		{
			$top = is_array($ext_info_stack) && $ext_info_stack !== array() ? end($ext_info_stack) : $ext_info;

			return is_array($top) ? (string) ($top['id'] ?? '') : '';
		}

		if (strpos($cur_file, $extensions_dir) === 0 && preg_match('#^([0-9a-z_]+)/#', substr($cur_file, strlen($extensions_dir)), $match))
			return $match[1];
	}

	return '';
}


/** The first line of the diagnostic as PHP displays it, which is what smoke_diagnostics() reads off a page. */
function extension_corpus_probe_text($errno, $errstr, $errfile, $errline)
{
	switch ($errno)
	{
		case E_WARNING:
		case E_USER_WARNING:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
			$label = 'Warning';
			break;

		case E_NOTICE:
		case E_USER_NOTICE:
			$label = 'Notice';
			break;

		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			$label = 'Deprecated';
			break;

		case E_PARSE:
			$label = 'Parse error';
			break;

		default:
			$label = 'Fatal error';
	}

	return explode("\n", $label.': '.$errstr.' in '.$errfile.' on line '.$errline, 2)[0];
}


function extension_corpus_probe_write($record)
{
	@file_put_contents(extension_corpus_probe_log(), json_encode($record, JSON_INVALID_UTF8_SUBSTITUTE)."\n", FILE_APPEND | LOCK_EX);
}
