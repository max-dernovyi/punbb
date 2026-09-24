<?php
/**
 * Runs remote_harness.php, the real get_remote_file() in a process of its own,
 * against remote_server.php in another, with a CA of its own trusted.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

final class RemoteHarness
{
	/** The name every certificate but wrong-host.pem is issued for; the server listens on 127.0.0.1. */
	public const HOST = 'localhost';

	/** The functions each transport setting takes away from the client. */
	public const TRANSPORTS = array(
		'curl'		=> '',
		'socket'	=> 'curl_init,curl_exec',
		'none'		=> 'curl_init,curl_exec,stream_socket_client',
		'no-tls'	=> 'curl_init,curl_exec,openssl_x509_parse',
	);

	private const EXTENSIONS = <<<'CNF'
[req]
distinguished_name = dn
[dn]
[ca]
basicConstraints = critical, CA:true
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash
[leaf]
basicConstraints = critical, CA:false
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always
subjectAltName = DNS:localhost
[wrong]
basicConstraints = critical, CA:false
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always
subjectAltName = DNS:wrong.example
CNF;

	private static ?string $directory = null;

	/**
	 * The directory holding ca.pem and one server certificate per case, each
	 * with its key: trusted.pem is issued by the CA for HOST, wrong-host.pem for
	 * another name, expired.pem for HOST with no validity left, self-signed.pem
	 * has no issuer but itself. Returns once expired.pem has lapsed.
	 */
	public static function certificates(): string
	{
		if (self::$directory !== null)
			return self::$directory;

		$directory = sys_get_temp_dir().'/remote_harness_'.getmypid();
		@mkdir($directory);
		register_shutdown_function(static fn () => array_map('unlink', (array) glob($directory.'/*')) && @rmdir($directory));
		file_put_contents($directory.'/openssl.cnf', self::EXTENSIONS);

		$config = array('config' => $directory.'/openssl.cnf', 'digest_alg' => 'sha256');
		$key = static fn () => openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1') + $config);

		$caKey = $key();
		$ca = openssl_csr_sign(openssl_csr_new(array('commonName' => 'PunBB test CA'), $caKey, $config), null, $caKey, 2, $config + array('x509_extensions' => 'ca'), 1);
		openssl_x509_export($ca, $caPem);
		file_put_contents($directory.'/ca.pem', $caPem);

		$servers = array(
			'trusted'		=> array(self::HOST, true, 'leaf', 2),
			'wrong-host'	=> array('wrong.example', true, 'wrong', 2),
			'expired'		=> array(self::HOST, true, 'leaf', 0),
			'self-signed'	=> array(self::HOST, false, 'leaf', 2),
		);

		$serial = 2;
		foreach ($servers as $name => list($commonName, $issued, $extensions, $days))
		{
			$serverKey = $key();
			$csr = openssl_csr_new(array('commonName' => $commonName), $serverKey, $config);
			$certificate = openssl_csr_sign($csr, $issued ? $ca : null, $issued ? $caKey : $serverKey, $days, $config + array('x509_extensions' => $extensions), $serial++);

			openssl_x509_export($certificate, $certificatePem);
			openssl_pkey_export($serverKey, $keyPem, null, $config);
			file_put_contents($directory.'/'.$name.'.pem', $certificatePem.$keyPem);
		}

		// Zero days ends the validity the second it began.
		$lapses = (int) openssl_x509_parse((string) file_get_contents($directory.'/expired.pem'))['validTo_time_t'];
		while (time() <= $lapses)
			usleep(100000);

		return self::$directory = $directory;
	}

	/**
	 * A server of remote_server.php, over TLS with the certificate $certificate
	 * names, or in cleartext when it is ''.
	 *
	 * @return array{process: resource, pipes: array<int, resource>, port: int}
	 */
	public static function server(string $certificate = ''): array
	{
		$command = array(PHP_BINARY, __DIR__.'/remote_server.php');
		if ($certificate !== '')
			$command[] = self::certificates().'/'.$certificate.'.pem';

		$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($process))
			throw new RuntimeException('the server did not start');

		$port = (int) fgets($pipes[1]);
		if ($port <= 0)
		{
			$reason = stream_get_contents($pipes[2]);
			proc_terminate($process);
			throw new RuntimeException('the server did not report a port: '.$reason);
		}

		return array('process' => $process, 'pipes' => $pipes, 'port' => $port);
	}

	/** A port on 127.0.0.1 nothing listens on. */
	public static function closedPort(): int
	{
		$server = stream_socket_server('tcp://127.0.0.1:0');
		$name = (string) stream_socket_get_name($server, false);
		fclose($server);

		return (int) substr($name, strrpos($name, ':') + 1);
	}

	/** @param array{process: resource, pipes: array<int, resource>, port: int} $server */
	public static function stop(array $server): void
	{
		proc_terminate($server['process']);
		foreach ($server['pipes'] as $pipe)
			fclose($pipe);
		proc_close($server['process']);
	}

	/**
	 * What get_remote_file($url, $timeout, $headOnly) returned on $transport,
	 * whether fetchesRemoteFiles() agreed, and anything else the process
	 * printed, PHP diagnostics included.
	 *
	 * @return array{result: mixed, fetches: bool, output: string}
	 */
	public static function fetch(string $url, string $transport = 'curl', int $timeout = 2, bool $headOnly = false): array
	{
		$ca = self::certificates().'/ca.pem';
		$command = array(PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-d', 'curl.cainfo='.$ca, '-d', 'openssl.cafile='.$ca);

		if (self::TRANSPORTS[$transport] !== '')
			array_push($command, '-d', 'disable_functions='.self::TRANSPORTS[$transport]);

		array_push($command, __DIR__.'/remote_harness.php', (string) json_encode(array('url' => $url, 'timeout' => $timeout, 'head_only' => $headOnly)));

		$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		$output = (string) stream_get_contents($pipes[1]);
		$output .= (string) stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = preg_match('/^RESULT=(.*)$/m', $output, $matches) ? json_decode($matches[1], true) : 'no result';
		$fetches = preg_match('/^FETCHES=(true|false)$/m', $output, $flag) === 1 && $flag[1] === 'true';

		return array(
			'result'	=> $result,
			'fetches'	=> $fetches,
			'output'	=> trim((string) preg_replace('/^(RESULT|FETCHES)=.*$/m', '', $output)),
		);
	}
}
