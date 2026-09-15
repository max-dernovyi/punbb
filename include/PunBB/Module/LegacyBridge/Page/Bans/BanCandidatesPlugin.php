<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Api\BanCandidatesInterface;
use PunBB\Module\Bans\Api\Data\BanCandidateInterface;
use PunBB\Module\Bans\Model\BanCandidate;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The bans page's query points on the member a ban is made for, with the
 * query arrays admin/bans.php built. The member is left in $banned_user_info
 * and the variables the page script listed it into, the address they last
 * posted from in $ban_ip_from_db.
 */
final class BanCandidatesPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterById(BanCandidatesInterface $subject, ?BanCandidateInterface $result, int $userId): ?BanCandidateInterface {
		$query = array(
			'SELECT'	=> 'u.group_id, u.username, u.email, u.registration_ip',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id='.$userId
		);

		if ($this->queries->changed('aba_add_ban_qr_get_user_by_id', BanCandidatesInterface::class.'::byId', $query))
		{
			$values = PluggedQuery::listed($query);
			$result = $values !== null ? self::candidate($userId, $values[0] ?? 0, $values[1] ?? '', $values[2] ?? '', $values[3] ?? '') : null;
		}

		$GLOBALS['banned_user_info'] = $result !== null ? array($result->groupId(), $result->username(), $result->email(), $result->registrationIp()) : false;
		self::publish($result);

		return $result;
	}

	public function afterByUsername(BanCandidatesInterface $subject, ?BanCandidateInterface $result, string $username): ?BanCandidateInterface {
		$query = array(
			'SELECT'	=> 'u.id, u.group_id, u.username, u.email, u.registration_ip',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.username=\''.Markers::markup(LegacyConnection::legacy()->escape($username)).'\' AND u.id>1'
		);

		if ($this->queries->changed('aba_add_ban_qr_get_user_by_username', BanCandidatesInterface::class.'::byUsername', $query))
		{
			$values = PluggedQuery::listed($query);
			$result = $values !== null ? self::candidate((int) Markers::markup($values[0] ?? 0), $values[1] ?? 0, $values[2] ?? '', $values[3] ?? '', $values[4] ?? '') : null;
		}

		$GLOBALS['banned_user_info'] = $result !== null ? array($result->id(), $result->groupId(), $result->username(), $result->email(), $result->registrationIp()) : false;
		self::publish($result);

		return $result;
	}

	public function afterLastKnownIp(BanCandidatesInterface $subject, ?string $result, int $userId): ?string {
		$query = array(
			'SELECT'	=> 'p.poster_ip',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.poster_id='.$userId,
			'ORDER BY'	=> 'p.posted DESC',
			'LIMIT'		=> '1'
		);

		if ($this->queries->changed('aba_add_ban_qr_get_last_known_ip', BanCandidatesInterface::class.'::lastKnownIp', $query))
		{
			$value = PluggedQuery::value($query);
			$result = $value !== null && $value !== false ? Markers::markup($value) : null;
		}

		$GLOBALS['ban_ip_from_db'] = $result ?? false;
		if ($result !== null && $result !== '')
			$GLOBALS['ban_ip'] = $result;

		return $result;
	}

	private static function candidate(int $userId, mixed $groupId, mixed $username, mixed $email, mixed $registrationIp): BanCandidate {
		return new BanCandidate($userId, (int) Markers::markup($groupId), Markers::markup($username), Markers::markup($email), Markers::markup($registrationIp));
	}

	private static function publish(?BanCandidateInterface $candidate): void {
		if ($candidate === null)
			return;

		$GLOBALS['user_id'] = $candidate->id();
		$GLOBALS['group_id'] = $candidate->groupId();
		$GLOBALS['ban_user'] = $candidate->username();
		$GLOBALS['ban_email'] = $candidate->email();
		$GLOBALS['ban_ip'] = $candidate->registrationIp();
	}
}
