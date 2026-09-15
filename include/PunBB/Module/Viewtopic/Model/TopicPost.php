<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Model;

use PunBB\Module\Viewtopic\Api\Data\TopicPostInterface;

final readonly class TopicPost implements TopicPostInterface {
	public function __construct(
		private int $id,
		private int $posterId,
		private string $poster,
		private string $posterIp,
		private ?string $posterEmail,
		private string $message,
		private bool $hidesSmilies,
		private int $posted,
		private ?int $edited,
		private ?string $editedBy,
		private string $email,
		private ?string $title,
		private ?string $url,
		private ?string $location,
		private ?string $signature,
		private int $emailSetting,
		private int $postCount,
		private int $registered,
		private ?string $adminNote,
		private int $avatar,
		private int $avatarWidth,
		private int $avatarHeight,
		private int $groupId,
		private ?string $groupTitle,
		private bool $online
	) {}

	public function id(): int {
		return $this->id;
	}

	public function posterId(): int {
		return $this->posterId;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function posterIp(): string {
		return $this->posterIp;
	}

	public function posterEmail(): ?string {
		return $this->posterEmail;
	}

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}

	public function posted(): int {
		return $this->posted;
	}

	public function edited(): ?int {
		return $this->edited;
	}

	public function editedBy(): ?string {
		return $this->editedBy;
	}

	public function email(): string {
		return $this->email;
	}

	public function title(): ?string {
		return $this->title;
	}

	public function url(): ?string {
		return $this->url;
	}

	public function location(): ?string {
		return $this->location;
	}

	public function signature(): ?string {
		return $this->signature;
	}

	public function emailSetting(): int {
		return $this->emailSetting;
	}

	public function postCount(): int {
		return $this->postCount;
	}

	public function registered(): int {
		return $this->registered;
	}

	public function adminNote(): ?string {
		return $this->adminNote;
	}

	public function avatar(): int {
		return $this->avatar;
	}

	public function avatarWidth(): int {
		return $this->avatarWidth;
	}

	public function avatarHeight(): int {
		return $this->avatarHeight;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function groupTitle(): ?string {
		return $this->groupTitle;
	}

	public function isOnline(): bool {
		return $this->online;
	}
}
