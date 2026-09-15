<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Page;

/**
 * Where a redirect may send the browser: somewhere on this forum. Callers hand
 * it destinations taken straight from the request, so anything that would
 * leave the forum lands on its index instead.
 */
final class RedirectTarget {
	/** $destination without control characters, relative to $baseUrl unless it is a path or on the forum's own host. */
	public static function normalise(string $destination, string $baseUrl): string {
		// Browsers drop control characters from a Location URL before they parse it, so the test runs on what is left
		$destination = (string) preg_replace('/([\x00-\x1f\x7f])|(%0[09ad])|(%7f)|(;[\s]*data[\s]*:)/i', '', $destination);

		$local = !str_starts_with($destination, 'http://') && !str_starts_with($destination, 'https://') && !str_starts_with($destination, '/');
		if ($local)
			$destination = $baseUrl.'/'.$destination;

		// Browsers fold a backslash to a slash in the authority; after the ? or # it is payload
		$pathLength = strcspn($destination, '?#');
		$destination = str_replace('\\', '/', substr($destination, 0, $pathLength)).substr($destination, $pathLength);

		// A destination built from the base URL above is local by construction, also where the base URL carries no scheme
		if ($local)
			return $destination;

		if (str_starts_with($destination, '//'))
			return $baseUrl.'/';

		if (str_starts_with($destination, '/'))
			return $destination;

		// Hosts, not prefixes: a forum served on a scheme or port its base URL does not name still honours its own URLs
		$host = parse_url($destination, PHP_URL_HOST);
		$baseHost = self::host($baseUrl);

		return is_string($host) && $baseHost !== null && strcasecmp($host, $baseHost) === 0 ? $destination : $baseUrl.'/';
	}

	/** The host of $url, also of one written without a scheme; null when it has none. */
	private static function host(string $url): ?string {
		$host = parse_url($url, PHP_URL_HOST);
		if (is_string($host))
			return $host;

		$host = parse_url('//'.ltrim($url, '/'), PHP_URL_HOST);

		return is_string($host) ? $host : null;
	}
}
