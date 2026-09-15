<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Censoring;

use PunBB\Module\Censoring\Api\CensorsInterface;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The censoring page's statement points, with the query arrays
 * admin/censoring.php built. A statement a point changed runs instead, and the
 * repository is handed no word to store. The words listed are left in
 * $forum_censors, as the page script read them from the cache.
 */
final class CensorsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	/**
	 * @param list<CensorInterface> $result
	 * @return list<CensorInterface>
	 */
	public function afterAll(CensorsInterface $subject, array $result): array {
		$GLOBALS['forum_censors'] = array_map(static fn (CensorInterface $censor): array => array(
			'id'			=> $censor->id(),
			'search_for'	=> $censor->searchFor(),
			'replace_with'	=> $censor->replaceWith(),
		), $result);

		return $result;
	}

	/** @return list<CensorInterface>|null */
	public function beforeAdd(CensorsInterface $subject, CensorInterface ...$censors): ?array {
		$kept = array();
		foreach ($censors as $censor)
		{
			$query = array(
				'INSERT'	=> 'search_for, replace_with',
				'INTO'		=> 'censoring',
				'VALUES'	=> '\''.self::escape($censor->searchFor()).'\', \''.self::escape($censor->replaceWith()).'\''
			);

			if ($this->queries->changed('acs_add_word_qr_add_censor', CensorsInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $censor;
		}

		return count($kept) !== count($censors) ? $kept : null;
	}

	/** @return list<CensorInterface>|null */
	public function beforeUpdate(CensorsInterface $subject, CensorInterface ...$censors): ?array {
		$kept = array();
		foreach ($censors as $censor)
		{
			$query = array(
				'UPDATE'	=> 'censoring',
				'SET'		=> 'search_for=\''.self::escape($censor->searchFor()).'\', replace_with=\''.self::escape($censor->replaceWith()).'\'',
				'WHERE'		=> 'id='.$censor->id()
			);

			if ($this->queries->changed('acs_update_qr_update_censor', CensorsInterface::class.'::update', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $censor;
		}

		return count($kept) !== count($censors) ? $kept : null;
	}

	/** @return list<int>|null */
	public function beforeRemove(CensorsInterface $subject, int ...$ids): ?array {
		$kept = array();
		foreach ($ids as $id)
		{
			$query = array(
				'DELETE'	=> 'censoring',
				'WHERE'		=> 'id='.$id
			);

			if ($this->queries->changed('acs_remove_qr_delete_censor', CensorsInterface::class.'::remove', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $id;
		}

		return count($kept) !== count($ids) ? $kept : null;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
