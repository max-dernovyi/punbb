<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Account;

use PunBB\Module\Layout\View\Html;

/**
 * What a username must be for a member to take it, or a guest to post under it.
 */
interface UsernameRulesInterface {
	/**
	 * Everything that stops $username from being taken: too short or too long,
	 * reserved, censored, or another member's already.
	 *
	 * @param ?int $exceptUserId the member whose own name it may be
	 * @return list<Html>
	 */
	public function validate(string $username, ?int $exceptUserId = null): array;
}
