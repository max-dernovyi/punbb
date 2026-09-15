<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Api\Data;

/**
 * What an account signs in with.
 */
interface CredentialsInterface {
	public function userId(): int;

	public function groupId(): int;

	/** The stored password hash; '' for an account that has none. */
	public function passwordHash(): string;

	public function salt(): string;
}
