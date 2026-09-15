<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Api;

use PunBB\Module\Bans\Api\Data\BanCandidateInterface;

/**
 * The members a ban can be made for.
 */
interface BanCandidatesInterface {
	/** Member $userId; null when there is none. */
	public function byId(int $userId): ?BanCandidateInterface;

	/** The member named $username; null when there is none. The guest account is nobody's. */
	public function byUsername(string $username): ?BanCandidateInterface;

	/** The address member $userId last posted from; null when they never posted. */
	public function lastKnownIp(int $userId): ?string;
}
