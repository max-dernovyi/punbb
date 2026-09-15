<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * A ban as admin/bans.php handed it to extension code: a row of its query, and
 * the values its statements carried, quoted for SQL.
 */
final class BanRows {
	/** @return array<string, mixed> the ban as a row of b.* with its creator's username */
	public static function row(BanInterface $ban): array {
		return array(
			'id'					=> $ban->id(),
			'username'				=> $ban->username(),
			'ip'					=> $ban->ip(),
			'email'					=> $ban->email(),
			'message'				=> $ban->message(),
			'expire'				=> $ban->expire(),
			'ban_creator'			=> $ban->creatorId(),
			'ban_creator_username'	=> $ban->creatorName(),
		);
	}

	/** $value as the page script put it into a statement: quoted and escaped, or NULL. */
	public static function quoted(?string $value): string {
		return $value !== null && $value !== '' ? '\''.Markers::markup(LegacyConnection::legacy()->escape($value)).'\'' : 'NULL';
	}
}
