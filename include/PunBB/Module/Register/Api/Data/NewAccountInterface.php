<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Api\Data;

/**
 * An account about to be registered.
 */
interface NewAccountInterface {
	public function username(): string;

	public function groupId(): int;

	public function salt(): string;

	/** The password as the visitor chose it, or as the board generated it for a verified registration. */
	public function password(): string;

	public function passwordHash(): string;

	public function email(): string;

	/** Who may email the member: 0 anyone, 1 members through the form, 2 nobody. */
	public function emailSetting(): int;

	/** The member's offset from UTC, in hours. */
	public function timezone(): float;

	/** Whether daylight saving time is added to the offset: 1 or 0. */
	public function dst(): int;

	public function language(): string;

	public function style(): string;

	public function registeredAt(): int;

	public function registrationIp(): string;

	/** The key the account is verified with, mailed to the member; null when it needs no verifying. */
	public function activationKey(): ?string;

	/** Whether the account is verified by mail before the member may sign in. */
	public function requiresVerification(): bool;

	/** Whether the board's mailing list is told of the registration. */
	public function notifiesAdmins(): bool;
}
