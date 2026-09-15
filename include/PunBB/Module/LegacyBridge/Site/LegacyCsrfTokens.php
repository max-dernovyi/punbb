<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Site\Security\CsrfTokensInterface;

/**
 * The tokens generate_form_token() mints over the visit's secret, with the
 * extension code attached to it.
 */
final class LegacyCsrfTokens implements CsrfTokensInterface {
	public function token(string $target): string {
		return Markers::markup(\generate_form_token($target));
	}

	public function matches(mixed $submitted, string $target): bool {
		return (bool) \csrf_token_matches($submitted, $target);
	}

	public function confirms(): bool {
		return !defined('FORUM_DISABLE_CSRF_CONFIRM');
	}
}
