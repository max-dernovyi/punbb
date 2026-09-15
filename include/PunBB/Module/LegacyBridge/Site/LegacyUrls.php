<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Url\WebAddress;

/**
 * The URL scheme include/common.php loaded into $forum_url, through the helpers
 * of include/functions.php and the extension code attached to them.
 */
final class LegacyUrls implements UrlsInterface {
	public function base(): string {
		return Markers::markup($GLOBALS['base_url'] ?? '');
	}

	public function link(string $name, array $arguments = array()): Html {
		return new Html(Markers::markup(\forum_link($this->url($name), $arguments !== array() ? $arguments : null)));
	}

	public function has(string $name): bool {
		$urls = $GLOBALS['forum_url'] ?? null;

		return is_array($urls) && isset($urls[$name]);
	}

	public function sublink(string $name, string $sub, int $subArgument, array $arguments = array()): Html {
		return new Html(Markers::markup(\forum_sublink($this->url($name), $this->url($sub), $subArgument, $arguments !== array() ? $arguments : null)));
	}

	public function pagination(int $pages, int $current, string $name, array $arguments = array(), ?Html $separator = null, bool $pageInQuery = false, string $query = ''): Html {
		$common = $GLOBALS['lang_common'] ?? null;
		$glue = $separator !== null ? $separator->html : (is_array($common) ? Markers::markup($common['Paging separator'] ?? '') : '');

		return new Html(Markers::markup(\paginate($pages, $current, $this->url($name).$query, $glue, $arguments !== array() ? $arguments : null, $pageInQuery ? true : null)));
	}

	/** config.php enables the conversion with FORUM_ENABLE_IDNA. */
	public function webAddress(string $url): WebAddress {
		if (!defined('FORUM_SUPPORT_PCRE_UNICODE') || !defined('FORUM_ENABLE_IDNA'))
			return new WebAddress($url, $url);

		if (preg_match('!^(https?|ftp|news){1}'.preg_quote('://xn--', '!').'!', $url) === 1)
			return new WebAddress($url, Markers::markup(\forum_idna_decode($url)));

		return new WebAddress(Markers::markup(\forum_idna_encode($url)), $url);
	}

	public function slug(string $text): string {
		return Markers::markup(\sef_friendly($text));
	}

	public function current(): string {
		return Markers::markup(\get_current_url());
	}

	private function url(string $name): string {
		$urls = $GLOBALS['forum_url'] ?? null;

		return is_array($urls) ? Markers::markup($urls[$name] ?? '') : '';
	}
}
