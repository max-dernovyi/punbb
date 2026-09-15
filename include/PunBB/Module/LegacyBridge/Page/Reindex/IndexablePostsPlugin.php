<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reindex;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Reindex\Api\Data\IndexablePostInterface;
use PunBB\Module\Reindex\Api\IndexablePostsInterface;
use PunBB\Module\Reindex\Model\IndexablePost;

/**
 * The rebuild's query points, with the query arrays admin/reindex.php built;
 * the first post's id is left in $first_id, as the page script kept it.
 */
final class IndexablePostsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterFirstId(IndexablePostsInterface $subject, ?int $result): ?int {
		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'ORDER BY'	=> 'p.id',
			'LIMIT'		=> '1'
		);

		if ($this->queries->changed('ari_qr_find_lowest_post_id', IndexablePostsInterface::class.'::firstId', $query))
			$result = self::id(PluggedQuery::value($query));

		if ($result !== null)
			$GLOBALS['first_id'] = $result;

		return $result;
	}

	/**
	 * @param list<IndexablePostInterface> $result
	 * @return list<IndexablePostInterface>
	 */
	public function afterBatch(IndexablePostsInterface $subject, array $result, int $startAt, int $limit): array {
		$query = array(
			'SELECT'	=> 'p.id, p.message, t.id, t.subject, t.first_post_id',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				)
			),
			'WHERE'		=> 'p.id >= '.$startAt,
			'ORDER BY'	=> 'p.id',
			'LIMIT'		=> $limit
		);

		if (!$this->queries->changed('ari_cycle_qr_fetch_posts', IndexablePostsInterface::class.'::batch', $query))
			return $result;

		// The page read the rows by position: the post's id, its text, its topic's id, subject and first post
		$posts = array();
		foreach (PluggedQuery::rows($query) as $row)
		{
			$values = array_values($row);
			$posts[] = new IndexablePost((int) Markers::markup($values[0] ?? 0), Markers::markup($values[1] ?? ''), (int) Markers::markup($values[2] ?? 0),
				Markers::markup($values[3] ?? ''), (int) Markers::markup($values[4] ?? 0));
		}

		return $posts;
	}

	public function afterNextId(IndexablePostsInterface $subject, ?int $result, int $postId): ?int {
		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.id > '.$postId,
			'ORDER BY'	=> 'p.id',
			'LIMIT'		=> '1'
		);

		if ($this->queries->changed('ari_cycle_qr_find_next_post', IndexablePostsInterface::class.'::nextId', $query))
			$result = self::id(PluggedQuery::value($query));

		return $result;
	}

	private static function id(mixed $value): ?int {
		return $value !== null && $value !== false ? (int) Markers::markup($value) : null;
	}
}
