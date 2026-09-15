<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\AdminIndex;

use PunBB\Module\AdminIndex\Api\BoardInformationInterface;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The administration index's query points, with the query arrays
 * admin/index.php built; what the page kept of each answer is left in its global.
 */
final class BoardInformationPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterOnlineCount(BoardInformationInterface $subject, int $result): int {
		$query = array(
			'SELECT'	=> 'COUNT(o.user_id)',
			'FROM'		=> 'online AS o',
			'WHERE'		=> 'o.idle=0'
		);

		if ($this->queries->changed('ain_qr_get_users_online', BoardInformationInterface::class.'::onlineCount', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		$GLOBALS['num_online'] = $result;

		return $result;
	}

	/**
	 * @param list<string> $result
	 * @return list<string>
	 */
	public function afterHotfixes(BoardInformationInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id LIKE \'hotfix_%\''
		);

		if ($this->queries->changed('ain_update_check_qr_get_hotfixes', BoardInformationInterface::class.'::hotfixes', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
				$result[] = Markers::markup($row['id'] ?? '');
		}

		$GLOBALS['hotfixes'] = array_map(urlencode(...), $result);

		return $result;
	}
}
