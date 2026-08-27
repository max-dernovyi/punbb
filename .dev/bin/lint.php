<?php
/**
 * Lint gate: syntax errors *and* compile-time diagnostics.
 *
 * `php -l` prints diagnostics such as "'continue' targeting switch is
 * equivalent to 'break'" but still exits 0, and parallel-lint only reports the
 * exit code — so a whole class of compile-time problems passes both. This
 * wrapper runs parallel-lint for syntax, then a second `php -l` pass that fails
 * on any Warning/Deprecated/Notice line.
 *
 * Usage: php .dev/bin/lint.php [path...]   (defaults to the whole repository)
 */

const LINT_EXCLUDE = array('vendor', '.git');
const LINT_JOBS = 10;

$root = dirname(__DIR__, 2);
chdir($root);

$targets = array_slice($argv, 1);
if (!$targets)
	$targets = array('.');

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/vendor/bin/parallel-lint').' --colors';
foreach (LINT_EXCLUDE as $dir)
	$command .= ' --exclude '.escapeshellarg($dir);
foreach ($targets as $target)
	$command .= ' '.escapeshellarg($target);

passthru($command, $status);
if ($status !== 0)
	exit($status);

$files = lint_files($targets);
$problems = lint_diagnostics($files);

if ($problems)
{
	fwrite(STDERR, PHP_EOL.'Compile-time diagnostics (php -l reports these but exits 0):'.PHP_EOL);
	foreach ($problems as $problem)
		fwrite(STDERR, '  '.$problem.PHP_EOL);

	fwrite(STDERR, PHP_EOL.count($problems).' compile-time diagnostic(s) found'.PHP_EOL);
	exit(1);
}

echo 'No compile-time diagnostics in '.count($files).' files'.PHP_EOL;

/** @return list<string> every .php file below $targets, minus the excluded trees */
function lint_files($targets)
{
	$files = array();

	foreach ($targets as $target)
	{
		if (!is_dir($target))
		{
			$files[] = $target;
			continue;
		}

		$tree = new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
			function ($file) {
				return !$file->isDir() || !in_array($file->getFilename(), LINT_EXCLUDE, true);
			}
		);

		foreach (new RecursiveIteratorIterator($tree) as $file)
			if ($file->isFile() && strtolower($file->getExtension()) === 'php')
				$files[] = $file->getPathname();
	}

	sort($files);

	return $files;
}

/** @return list<string> the diagnostic lines `php -l` printed for $files */
function lint_diagnostics($files)
{
	$problems = array();
	$queue = $files;

	while ($queue)
	{
		$batch = array_splice($queue, 0, LINT_JOBS);
		$jobs = array();

		foreach ($batch as $path)
		{
			$pipes = array();
			// log_errors=0: without it every diagnostic is printed twice.
			$process = proc_open(
				array(PHP_BINARY, '-d', 'log_errors=0', '-d', 'display_errors=1', '-l', $path),
				array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
				$pipes
			);

			// A file that could not be linted must fail the gate, not vanish
			// from the count of files checked.
			if (!is_resource($process))
				$problems[] = $path.': could not be linted - proc_open() failed';
			else
				$jobs[] = array($process, $pipes);
		}

		foreach ($jobs as list($process, $pipes))
		{
			$output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($process);

			foreach (preg_split('/\R/', $output) as $line)
				if (preg_match('/\b(?:Warning|Deprecated|Notice|Strict Standards):/', $line))
					$problems[] = rtrim($line);
		}
	}

	return $problems;
}
