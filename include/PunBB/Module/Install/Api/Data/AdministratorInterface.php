<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Api\Data;

/**
 * The account of the board's first administrator.
 */
interface AdministratorInterface {
	public function username(): string;

	/** The password as the board stores it. */
	public function passwordHash(): string;

	public function salt(): string;

	public function email(): string;

	public function language(): string;

	/** When the account registered, as a Unix timestamp; its first post is from then too. */
	public function registered(): int;
}
