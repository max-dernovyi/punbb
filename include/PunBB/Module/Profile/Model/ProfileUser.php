<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A member read from their row of the users table joined with their group:
 * each column as the database returned it, by name.
 */
final readonly class ProfileUser implements ProfileUserInterface {
	/** @param array<array-key, mixed> $columns */
	public function __construct(private array $columns) {}

	public function id(): int {
		return $this->integer('id');
	}

	public function username(): string {
		return $this->text('username');
	}

	public function email(): string {
		return $this->text('email');
	}

	public function title(): string {
		return $this->text('title');
	}

	public function realname(): string {
		return $this->text('realname');
	}

	public function url(): string {
		return $this->text('url');
	}

	public function facebook(): string {
		return $this->text('facebook');
	}

	public function twitter(): string {
		return $this->text('twitter');
	}

	public function linkedin(): string {
		return $this->text('linkedin');
	}

	public function skype(): string {
		return $this->text('skype');
	}

	public function jabber(): string {
		return $this->text('jabber');
	}

	public function icq(): string {
		return $this->text('icq');
	}

	public function msn(): string {
		return $this->text('msn');
	}

	public function aim(): string {
		return $this->text('aim');
	}

	public function yahoo(): string {
		return $this->text('yahoo');
	}

	public function location(): string {
		return $this->text('location');
	}

	public function signature(): string {
		return $this->text('signature');
	}

	public function adminNote(): string {
		return $this->text('admin_note');
	}

	public function topicsPerPage(): ?int {
		return $this->nullableInteger('disp_topics');
	}

	public function postsPerPage(): ?int {
		return $this->nullableInteger('disp_posts');
	}

	public function emailSetting(): int {
		return $this->integer('email_setting');
	}

	public function notifiesWithPost(): bool {
		return $this->integer('notify_with_post') === 1;
	}

	public function subscribesAutomatically(): bool {
		return $this->integer('auto_notify') === 1;
	}

	public function showsSmilies(): bool {
		return $this->integer('show_smilies') === 1;
	}

	public function showsImages(): bool {
		return $this->integer('show_img') === 1;
	}

	public function showsSignatureImages(): bool {
		return $this->integer('show_img_sig') === 1;
	}

	public function showsAvatars(): bool {
		return $this->integer('show_avatars') === 1;
	}

	public function showsSignatures(): bool {
		return $this->integer('show_sig') === 1;
	}

	public function timezone(): string {
		return $this->text('timezone');
	}

	public function daylightSaving(): bool {
		return $this->integer('dst') === 1;
	}

	public function timeFormat(): int {
		return $this->integer('time_format');
	}

	public function dateFormat(): int {
		return $this->integer('date_format');
	}

	public function language(): string {
		return $this->text('language');
	}

	public function style(): string {
		return $this->text('style');
	}

	public function posts(): int {
		return $this->integer('num_posts');
	}

	public function lastPost(): ?int {
		return $this->nullableInteger('last_post');
	}

	public function lastVisit(): int {
		return $this->integer('last_visit');
	}

	public function registered(): int {
		return $this->integer('registered');
	}

	public function registrationIp(): string {
		return $this->text('registration_ip');
	}

	public function avatarType(): int {
		return $this->integer('avatar');
	}

	public function avatarWidth(): int {
		return $this->integer('avatar_width');
	}

	public function avatarHeight(): int {
		return $this->integer('avatar_height');
	}

	public function passwordHash(): string {
		return $this->text('password');
	}

	public function salt(): string {
		return $this->text('salt');
	}

	public function activateKey(): string {
		return $this->text('activate_key');
	}

	public function lastEmailSent(): ?int {
		return $this->nullableInteger('last_email_sent');
	}

	public function groupId(): ?int {
		return $this->nullableInteger('g_id');
	}

	public function groupTitle(): ?string {
		$title = $this->columns['g_user_title'] ?? null;

		return is_scalar($title) ? (string) $title : null;
	}

	public function moderates(): bool {
		return $this->integer('g_moderator') === 1;
	}

	public function isAdministrator(): bool {
		return $this->groupId() === self::ADMINISTRATORS;
	}

	public function withAvatar(int $type, int $width, int $height): self {
		return new self(array('avatar' => $type, 'avatar_width' => $width, 'avatar_height' => $height) + $this->columns);
	}

	/** @return array<array-key, mixed> every column read, by name */
	public function columns(): array {
		return $this->columns;
	}

	private function text(string $column): string {
		$value = $this->columns[$column] ?? null;

		return is_scalar($value) ? (string) $value : '';
	}

	private function integer(string $column): int {
		return $this->nullableInteger($column) ?? 0;
	}

	private function nullableInteger(string $column): ?int {
		$value = $this->columns[$column] ?? null;

		return is_scalar($value) && $value !== '' ? (int) $value : null;
	}
}
