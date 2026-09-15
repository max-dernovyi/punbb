<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\Userlist\Api\Data\MemberInterface;

/**
 * The rows the member list's query returned, as userlist.php handed them to
 * extension code: an array of columns each, including any column extension
 * code added to the query.
 */
final class MemberRows {
	/** @var array<int, array<array-key, mixed>> member id => row */
	private array $rows = array();

	/** @param array<array-key, mixed> $row */
	public function keep(int $id, array $row): void {
		$this->rows[$id] = $row;
	}

	/** @return array<array-key, mixed> the row kept for $member, or one built from it */
	public function row(MemberInterface $member): array {
		return $this->rows[$member->id()] ?? self::of($member);
	}

	/** @return array<string, mixed> $member as a row of the query */
	public static function of(MemberInterface $member): array {
		return array(
			'id'			=> $member->id(),
			'username'		=> $member->username(),
			'title'			=> $member->title() !== '' ? $member->title() : null,
			'num_posts'		=> $member->postCount(),
			'registered'	=> $member->registered(),
			'g_id'			=> $member->groupId(),
			'g_user_title'	=> $member->groupTitle(),
		);
	}
}
