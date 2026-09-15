<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Api;

use PunBB\Module\Login\Api\Data\CredentialsInterface;
use PunBB\Module\Login\Api\Data\LastVisitInterface;
use PunBB\Module\Login\Api\Data\ResetKeyInterface;
use PunBB\Module\Login\Api\Data\ResettableAccountInterface;

/**
 * The accounts members sign in to, and ask a new password for.
 */
interface AccountsInterface {
	/** The credentials of the account named $username, ignoring case where the database does not already; null when there is none. */
	public function credentials(string $username): ?CredentialsInterface;

	/** Stores the password hash and the salt of each of $credentials. */
	public function storePassword(CredentialsInterface ...$credentials): void;

	/** Moves each of the accounts $userIds into group $groupId, as a first login verifies an account. */
	public function activate(int $groupId, int ...$userIds): void;

	/** Stores when each of $visits ended, as the member's last visit. */
	public function recordLastVisit(LastVisitInterface ...$visits): void;

	/** @return list<ResettableAccountInterface> every account registered with $email */
	public function resettable(string $email): array;

	/** Stores each of $keys as the key its account's password is reset with, and when it was mailed. */
	public function issueResetKey(ResetKeyInterface ...$keys): void;
}
