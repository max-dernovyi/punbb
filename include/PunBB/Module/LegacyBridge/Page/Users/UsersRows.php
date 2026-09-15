<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\Users\Api\Data\AddressUseInterface;
use PunBB\Module\Users\Api\Data\BanTargetInterface;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\PosterInterface;
use PunBB\Module\Users\Model\FoundUser;

/**
 * The addresses, posters and users as admin/users.php handed them to extension
 * code: the rows of their queries, with any column a query point added. A
 * user to ban is also kept by id, as the statement banning them is built from it.
 */
final class UsersRows {
	/** @var array<int, array<array-key, mixed>> user id => the row a user to ban was read as */
	private array $targets = array();

	public function __construct(private readonly KeptRows $rows) {}

	/** @param array<array-key, mixed> $row */
	public function keep(object $answer, array $row): void {
		$this->rows->keep($answer, $row);

		if ($answer instanceof BanTargetInterface)
			$this->targets[$answer->id()] = $row;
	}

	/** @return array<array-key, mixed> */
	public function address(AddressUseInterface $address): array {
		return $this->rows->row($address) ?? array('poster_ip' => $address->address(), 'last_used' => $address->lastUsed(), 'used_times' => $address->timesUsed());
	}

	/** @return array<array-key, mixed> */
	public function poster(PosterInterface $poster): array {
		return $this->rows->row($poster) ?? array('poster_id' => $poster->id(), 'poster' => $poster->name());
	}

	/** @return array<array-key, mixed> */
	public function user(FoundUserInterface $user): array {
		return $this->rows->row($user) ?? array(
			'id'			=> $user->id(),
			'username'		=> $user->username(),
			'email'			=> $user->email(),
			'title'			=> $user->title() !== '' ? $user->title() : null,
			'num_posts'		=> $user->postCount(),
			'admin_note'	=> $user->adminNote() !== '' ? $user->adminNote() : null,
			'g_id'			=> $user->groupId(),
			'g_user_title'	=> $user->groupTitle(),
		);
	}

	/** @return array<array-key, mixed> the row user $id was read as for a ban, or one built from $target */
	public function target(int $id, ?BanTargetInterface $target = null): array {
		return $this->targets[$id] ?? ($target !== null ? array('id' => $target->id(), 'username' => $target->username(), 'email' => $target->email(), 'registration_ip' => $target->registrationIp()) : array('id' => $id));
	}

	/** @param array<array-key, mixed> $row a row of the users found */
	public static function userOf(array $row): FoundUser {
		return new FoundUser(
			(int) Markers::markup($row['id'] ?? 0),
			Markers::markup($row['username'] ?? ''),
			Markers::markup($row['email'] ?? ''),
			Markers::markup($row['title'] ?? ''),
			(int) Markers::markup($row['num_posts'] ?? 0),
			Markers::markup($row['admin_note'] ?? ''),
			isset($row['g_id']) && Markers::markup($row['g_id']) !== '' ? (int) Markers::markup($row['g_id']) : null,
			isset($row['g_user_title']) ? Markers::markup($row['g_user_title']) : null
		);
	}
}
