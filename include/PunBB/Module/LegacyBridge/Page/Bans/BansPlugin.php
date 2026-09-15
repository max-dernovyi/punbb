<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Api\BansInterface;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Bans\Model\Ban;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The bans page's query points on the bans, with the query arrays
 * admin/bans.php built. A ban to edit is left in $banned_user_info and the
 * variables the page script listed it into; a statement a point changed runs
 * instead, and the repository is handed no ban to store.
 */
final class BansPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterFind(BansInterface $subject, ?BanInterface $result, int $id): ?BanInterface {
		$query = array(
			'SELECT'	=> 'b.username, b.ip, b.email, b.message, b.expire',
			'FROM'		=> 'bans AS b',
			'WHERE'		=> 'b.id='.$id
		);

		if ($this->queries->changed('aba_edit_ban_qr_get_ban_data', BansInterface::class.'::find', $query))
		{
			$values = PluggedQuery::listed($query);
			$result = $values !== null ? new Ban($id, self::nullable($values[0] ?? null), self::nullable($values[1] ?? null), self::nullable($values[2] ?? null), self::nullable($values[3] ?? null),
				isset($values[4]) && Markers::markup($values[4]) !== '' ? (int) Markers::markup($values[4]) : null, 0) : null;
		}

		$GLOBALS['banned_user_info'] = $result !== null ? array($result->username(), $result->ip(), $result->email(), $result->message(), $result->expire()) : false;
		if ($result !== null)
		{
			$GLOBALS['ban_user'] = $result->username();
			$GLOBALS['ban_ip'] = $result->ip();
			$GLOBALS['ban_email'] = $result->email();
			$GLOBALS['ban_message'] = $result->message();
			$GLOBALS['ban_expire'] = $result->expire() !== null ? gmdate('Y-m-d', $result->expire()) : '';
		}

		return $result;
	}

	/** @return list<BanInterface>|null */
	public function beforeAdd(BansInterface $subject, BanInterface ...$bans): ?array {
		$kept = array();
		foreach ($bans as $ban)
		{
			$query = array(
				'INSERT'	=> 'username, ip, email, message, expire, ban_creator',
				'INTO'		=> 'bans',
				'VALUES'	=> BanRows::quoted($ban->username()).', '.BanRows::quoted($ban->ip()).', '.BanRows::quoted($ban->email()).', '.BanRows::quoted($ban->message()).', '.($ban->expire() ?? 'NULL').', '.$ban->creatorId()
			);

			if ($this->queries->changed('aba_add_edit_ban_qr_add_ban', BansInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $ban;
		}

		return count($kept) !== count($bans) ? $kept : null;
	}

	/** @return list<BanInterface>|null */
	public function beforeUpdate(BansInterface $subject, BanInterface ...$bans): ?array {
		$kept = array();
		foreach ($bans as $ban)
		{
			$query = array(
				'UPDATE'	=> 'bans',
				'SET'		=> 'username='.BanRows::quoted($ban->username()).', ip='.BanRows::quoted($ban->ip()).', email='.BanRows::quoted($ban->email()).', message='.BanRows::quoted($ban->message()).', expire='.($ban->expire() ?? 'NULL'),
				'WHERE'		=> 'id='.$ban->id()
			);

			if ($this->queries->changed('aba_qr_update_ban', BansInterface::class.'::update', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $ban;
		}

		return count($kept) !== count($bans) ? $kept : null;
	}

	/** @return list<int>|null */
	public function beforeRemove(BansInterface $subject, int ...$ids): ?array {
		$kept = array();
		foreach ($ids as $id)
		{
			$query = array(
				'DELETE'	=> 'bans',
				'WHERE'		=> 'id='.$id
			);

			if ($this->queries->changed('aba_del_ban_qr_delete_ban', BansInterface::class.'::remove', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $id;
		}

		return count($kept) !== count($ids) ? $kept : null;
	}

	private static function nullable(mixed $value): ?string {
		return $value !== null ? Markers::markup($value) : null;
	}
}
