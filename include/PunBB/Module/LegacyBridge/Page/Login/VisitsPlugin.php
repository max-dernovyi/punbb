<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Login\Api\VisitsInterface;

/**
 * The login page's online list points, with the statements login.php built. A
 * statement a point changed runs instead, and the repository is handed nothing
 * to remove.
 */
final class VisitsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	/** @return list<string>|null */
	public function beforeEndGuestVisit(VisitsInterface $subject, string ...$addresses): ?array {
		$kept = array();
		foreach ($addresses as $address)
		{
			$query = array(
				'DELETE'	=> 'online',
				'WHERE'		=> 'ident=\''.Markers::markup(LegacyConnection::legacy()->escape($address)).'\''
			);

			if ($this->queries->changed('li_login_qr_delete_online_user', VisitsInterface::class.'::endGuestVisit', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $address;
		}

		return count($kept) !== count($addresses) ? $kept : null;
	}

	/** @return list<int>|null */
	public function beforeEndVisit(VisitsInterface $subject, int ...$userIds): ?array {
		$kept = array();
		foreach ($userIds as $userId)
		{
			$query = array(
				'DELETE'	=> 'online',
				'WHERE'		=> 'user_id='.$userId
			);

			if ($this->queries->changed('li_logout_qr_delete_online_user', VisitsInterface::class.'::endVisit', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $userId;
		}

		return count($kept) !== count($userIds) ? $kept : null;
	}
}
