<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Http;

/**
 * What the client asked for, read once from the server's globals: the method,
 * the path below the forum root and the parameters.
 */
final readonly class Request {
	/**
	 * @param string $base the forum root's URL path with a trailing slash: '/', '/forum/'
	 * @param string $path below $base, URL-decoded, without the query: '', 'viewtopic.php', 'topic/1/'
	 * @param array<mixed> $query
	 * @param array<mixed> $post
	 * @param array<mixed> $cookies
	 * @param bool $xhr whether a script sent it as XMLHttpRequest, and expects JSON back
	 * @param ?string $authUser the username of HTTP Basic authentication, null when the client sent none
	 * @param ?string $authPassword the password sent with it
	 * @param array<mixed> $files the files uploaded, as PHP describes them
	 * @param string $host the host the client asked, with its port: 'forum.example.com', 'localhost:8080'
	 * @param bool $secure whether the client asked over HTTPS
	 */
	public function __construct(
		public string $method,
		public string $base,
		public string $path,
		public array $query = array(),
		public array $post = array(),
		public array $cookies = array(),
		public bool $xhr = false,
		public ?string $authUser = null,
		public ?string $authPassword = null,
		public array $files = array(),
		public string $host = '',
		public bool $secure = false
	) {}

	/**
	 * The base is the directory of the script the server ran, the front
	 * controller in the forum root; the path is what follows it in the URI.
	 *
	 * @param array<mixed> $server
	 * @param array<mixed> $query
	 * @param array<mixed> $post
	 * @param array<mixed> $cookies
	 * @param array<mixed> $files
	 */
	public static function fromGlobals(array $server, array $query, array $post, array $cookies, array $files = array()): self {
		$script = isset($server['SCRIPT_NAME']) && is_string($server['SCRIPT_NAME']) ? $server['SCRIPT_NAME'] : '/';
		$base = str_replace('\\', '/', dirname($script));
		if (!str_ends_with($base, '/'))
			$base .= '/';

		$uri = isset($server['REQUEST_URI']) && is_string($server['REQUEST_URI']) ? $server['REQUEST_URI'] : '/';
		$path = substr(urldecode($uri), strlen($base));
		$path = explode('?', $path, 2)[0];

		$method = isset($server['REQUEST_METHOD']) && is_string($server['REQUEST_METHOD']) ? strtoupper($server['REQUEST_METHOD']) : 'GET';

		$xhr = isset($server['HTTP_X_REQUESTED_WITH']) && is_string($server['HTTP_X_REQUESTED_WITH']) && strtolower($server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

		$authUser = isset($server['PHP_AUTH_USER']) && is_string($server['PHP_AUTH_USER']) ? $server['PHP_AUTH_USER'] : null;
		$authPassword = $authUser !== null && isset($server['PHP_AUTH_PW']) && is_string($server['PHP_AUTH_PW']) ? $server['PHP_AUTH_PW'] : null;

		$host = isset($server['HTTP_HOST']) && is_string($server['HTTP_HOST']) ? $server['HTTP_HOST'] : '';
		$secure = isset($server['HTTPS']) && $server['HTTPS'] === 'on';

		return new self($method, $base, $path, $query, $post, $cookies, $xhr, $authUser, $authPassword, $files, $host, $secure);
	}

	/**
	 * This request with the parameters as the bootstrap left them, which strips
	 * the characters that mess with a page from them.
	 *
	 * @param array<mixed> $query
	 * @param array<mixed> $post
	 * @param array<mixed> $cookies
	 */
	public function withParameters(array $query, array $post, array $cookies): self {
		return new self($this->method, $this->base, $this->path, $query, $post, $cookies, $this->xhr, $this->authUser, $this->authPassword, $this->files, $this->host, $this->secure);
	}

	/**
	 * The request a rewrite rule turned this one into: its target path, and its
	 * parameters over the query.
	 *
	 * @param array<array-key, string> $parameters
	 */
	public function rewritten(string $path, array $parameters): self {
		$query = $this->query;
		foreach ($parameters as $name => $value)
			$query[$name] = $value;

		return new self($this->method, $this->base, $path, $query, $this->post, $this->cookies, $this->xhr, $this->authUser, $this->authPassword, $this->files, $this->host, $this->secure);
	}
}
