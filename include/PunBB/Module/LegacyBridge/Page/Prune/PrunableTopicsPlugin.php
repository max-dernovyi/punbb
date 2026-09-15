<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Prune;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Prune\Api\Data\PrunableForumInterface;
use PunBB\Module\Prune\Api\PrunableTopicsInterface;
use PunBB\Module\Prune\Model\PrunableForum;

/**
 * The pruning page's query points, with the query arrays admin/prune.php
 * built; the forum's name is left in $forum and the count in $num_topics, as
 * the page script kept them.
 */
final class PrunableTopicsPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly KeptRows $rows) {}

	/**
	 * @param list<PrunableForumInterface> $result
	 * @return list<PrunableForumInterface>
	 */
	public function afterForums(PrunableTopicsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name',
			'FROM'		=> 'categories AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'c.id=f.cat_id'
				)
			),
			'WHERE'		=> 'f.redirect_url IS NULL',
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		$GLOBALS['cur_category'] = 0;

		if (!$this->queries->changed('apr_qr_get_forum_list', PrunableTopicsInterface::class.'::forums', $query))
			return $result;

		$forums = array();
		foreach (PluggedQuery::rows($query) as $row)
		{
			$forum = new PrunableForum((int) Markers::markup($row['cid'] ?? 0), Markers::markup($row['cat_name'] ?? ''), (int) Markers::markup($row['fid'] ?? 0), Markers::markup($row['forum_name'] ?? ''));
			$this->rows->keep($forum, $row);
			$forums[] = $forum;
		}

		return $forums;
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterForumIds(PrunableTopicsInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'f.id',
			'FROM'		=> 'forums AS f'
		);

		if (!$this->queries->changed('apr_prune_comply_qr_get_all_forums', PrunableTopicsInterface::class.'::forumIds', $query))
			return $result;

		$ids = array();
		foreach (PluggedQuery::rows($query) as $row)
			$ids[] = (int) Markers::markup($row['id'] ?? 0);

		return $ids;
	}

	public function afterForumName(PrunableTopicsInterface $subject, ?string $result, int $forumId): ?string {
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$forumId
		);

		if ($this->queries->changed('apr_prune_comply_qr_get_forum_name', PrunableTopicsInterface::class.'::forumName', $query))
		{
			$value = PluggedQuery::value($query);
			$result = $value !== null && $value !== false ? Markers::markup($value) : null;
		}

		$GLOBALS['forum'] = htmlspecialchars($result ?? '', ENT_QUOTES, 'UTF-8');

		return $result;
	}

	public function afterCount(PrunableTopicsInterface $subject, int $result, ?int $forumId, int $lastPostBefore, bool $sticky): int {
		$query = array(
			'SELECT'	=> 'COUNT(t.id)',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.last_post<'.$lastPostBefore.' AND t.moved_to IS NULL'
		);

		if ($forumId !== null)
			$query['WHERE'] .= ' AND t.forum_id='.$forumId;
		if (!$sticky)
			$query['WHERE'] .= ' AND t.sticky=0';

		$GLOBALS['prune_date'] = $lastPostBefore;
		$GLOBALS['prune_from'] = $forumId ?? 'all';

		if ($forumId === null)
			$GLOBALS['forum'] = 'all forums';

		if ($this->queries->changed('apr_prune_comply_qr_get_topic_count', PrunableTopicsInterface::class.'::count', $query))
			$result = (int) Markers::markup(PluggedQuery::value($query));

		$GLOBALS['num_topics'] = $result;

		return $result;
	}
}
