<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Ranks;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Ranks\Api\Data\RankInterface;
use PunBB\Module\Ranks\Api\RanksInterface;

/**
 * The ranks page's query points, with the query arrays admin/ranks.php built.
 * A statement a point changed runs instead, and the repository is handed no
 * rank to store. The ranks listed are left in $forum_ranks, as the page script
 * read them from the cache.
 */
final class RanksPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	/**
	 * @param list<RankInterface> $result
	 * @return list<RankInterface>
	 */
	public function afterAll(RanksInterface $subject, array $result): array {
		$GLOBALS['forum_ranks'] = array_map(static fn (RankInterface $rank): array => array(
			'id'		=> $rank->id(),
			'rank'		=> $rank->title(),
			'min_posts'	=> $rank->minPosts(),
		), $result);

		return $result;
	}

	public function afterMinPostsTaken(RanksInterface $subject, bool $result, int $minPosts, ?int $exceptId): bool {
		$query = array(
			'SELECT'	=> 'COUNT(r.id)',
			'FROM'		=> 'ranks AS r',
			'WHERE'		=> ($exceptId !== null ? 'id!='.$exceptId.' AND ' : '').'min_posts='.$minPosts
		);

		$point = $exceptId !== null ? 'ark_update_qr_check_rank_collision' : 'ark_add_rank_qr_check_rank_collision';
		if ($this->queries->changed($point, RanksInterface::class.'::minPostsTaken', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query)) > 0;

		return $result;
	}

	/** @return list<RankInterface>|null */
	public function beforeAdd(RanksInterface $subject, RankInterface ...$ranks): ?array {
		$db = LegacyConnection::legacy();

		$kept = array();
		foreach ($ranks as $rank)
		{
			$query = array(
				'INSERT'	=> Markers::markup($db->quote_identifier('rank')).', min_posts',
				'INTO'		=> 'ranks',
				'VALUES'	=> '\''.Markers::markup($db->escape($rank->title())).'\', '.$rank->minPosts()
			);

			if ($this->queries->changed('ark_add_rank_qr_add_rank', RanksInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $rank;
		}

		return count($kept) !== count($ranks) ? $kept : null;
	}

	/** @return list<RankInterface>|null */
	public function beforeUpdate(RanksInterface $subject, RankInterface ...$ranks): ?array {
		$db = LegacyConnection::legacy();

		$kept = array();
		foreach ($ranks as $rank)
		{
			$query = array(
				'UPDATE'	=> 'ranks',
				'SET'		=> Markers::markup($db->quote_identifier('rank')).'=\''.Markers::markup($db->escape($rank->title())).'\', min_posts='.$rank->minPosts(),
				'WHERE'		=> 'id='.$rank->id()
			);

			if ($this->queries->changed('ark_update_qr_update_rank', RanksInterface::class.'::update', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $rank;
		}

		return count($kept) !== count($ranks) ? $kept : null;
	}

	/** @return list<int>|null */
	public function beforeRemove(RanksInterface $subject, int ...$ids): ?array {
		$kept = array();
		foreach ($ids as $id)
		{
			$query = array(
				'DELETE'	=> 'ranks',
				'WHERE'		=> 'id='.$id
			);

			if ($this->queries->changed('ark_remove_qr_delete_rank', RanksInterface::class.'::remove', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $id;
		}

		return count($kept) !== count($ids) ? $kept : null;
	}
}
