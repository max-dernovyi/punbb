<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * A member whose profile is shown or changed, as the users table stores them,
 * with what their group says about them. A column the table leaves NULL is
 * '' where the member gave nothing.
 */
interface ProfileUserInterface {
	public const ADMINISTRATORS = 1;

	public const GUESTS = 2;

	public function id(): int;

	public function username(): string;

	public function email(): string;

	/** The member's own title; '' for none. */
	public function title(): string;

	public function realname(): string;

	public function url(): string;

	public function facebook(): string;

	public function twitter(): string;

	public function linkedin(): string;

	public function skype(): string;

	public function jabber(): string;

	public function icq(): string;

	public function msn(): string;

	public function aim(): string;

	public function yahoo(): string;

	public function location(): string;

	/** The signature as the member wrote it, BBCode and all. */
	public function signature(): string;

	/** What the administrators noted about the member. */
	public function adminNote(): string;

	/** How many topics a page shows the member; null for the board's default. */
	public function topicsPerPage(): ?int;

	/** How many posts a page shows the member; null for the board's default. */
	public function postsPerPage(): ?int;

	/** 0 shows the address, 1 hides it and allows mail through the board, 2 hides it and allows none. */
	public function emailSetting(): int;

	public function notifiesWithPost(): bool;

	public function subscribesAutomatically(): bool;

	public function showsSmilies(): bool;

	public function showsImages(): bool;

	public function showsSignatureImages(): bool;

	public function showsAvatars(): bool;

	public function showsSignatures(): bool;

	/** The member's offset from UTC in hours, as stored: '5.5'. */
	public function timezone(): string;

	public function daylightSaving(): bool;

	/** The number of the time format the member chose; 0 for the board's. */
	public function timeFormat(): int;

	public function dateFormat(): int;

	public function language(): string;

	public function style(): string;

	public function posts(): int;

	/** When the member last posted; null when they never did. */
	public function lastPost(): ?int;

	public function lastVisit(): int;

	public function registered(): int;

	public function registrationIp(): string;

	/** The image type of the member's avatar; 0 for none. */
	public function avatarType(): int;

	public function avatarWidth(): int;

	public function avatarHeight(): int;

	public function passwordHash(): string;

	public function salt(): string;

	/** The key mailed to reset the password or confirm an address; '' for none. */
	public function activateKey(): string;

	/** When the board last mailed the member a key; null when it never did. */
	public function lastEmailSent(): ?int;

	/** The member's group; null when the group is gone. */
	public function groupId(): ?int;

	/** The title the member's group gives its members; null for none. */
	public function groupTitle(): ?string;

	/** Whether the member's group moderates. */
	public function moderates(): bool;

	public function isAdministrator(): bool;

	/** The member with the avatar of image type $type, at $width by $height, their account now records. */
	public function withAvatar(int $type, int $width, int $height): self;
}
