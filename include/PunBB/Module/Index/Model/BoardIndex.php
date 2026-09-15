<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Api\Data\StatisticsInterface;

/**
 * The board index's data, read from the categories, forums, topics, users and
 * online tables with the reading permission of the group asking.
 */
final class BoardIndex implements BoardIndexInterface {
	/** The group of accounts that have not confirmed their address. */
	public const UNVERIFIED_GROUP = 0;

	public function __construct(private readonly Connection $db) {}

	public function forums(int $groupId): array {
		$rows = $this->db->select('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster'.
			' FROM '.$this->db->table('categories').' AS c'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE fp.read_forum IS NULL OR fp.read_forum=1'.
			' ORDER BY c.disp_position, c.id, f.disp_position', $groupId);

		return array_map(static fn (Row $row): Forum => new Forum(
			$row->int('cid'),
			$row->string('cat_name'),
			$row->int('fid'),
			$row->string('forum_name'),
			$row->nullableString('forum_desc') ?? '',
			$row->nullableString('redirect_url') ?? '',
			Moderator::listOf($row->nullableString('moderators')),
			$row->int('num_topics'),
			$row->int('num_posts'),
			$row->nullableInt('last_post'),
			$row->nullableInt('last_post_id'),
			$row->nullableString('last_poster')
		), $rows);
	}

	public function activeTopics(int $groupId, int $since): array {
		$rows = $this->db->select('SELECT t.forum_id, t.id, t.last_post'.
			' FROM '.$this->db->table('topics').' AS t'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>? AND t.moved_to IS NULL', $groupId, $since);

		return array_map(static fn (Row $row): TopicActivity => new TopicActivity($row->int('forum_id'), $row->int('id'), $row->int('last_post')), $rows);
	}

	public function statistics(): StatisticsInterface {
		$users = (int) $this->db->selectValue('SELECT COUNT(u.id) - 1 FROM '.$this->db->table('users').' AS u WHERE u.group_id != ?', self::UNVERIFIED_GROUP);
		$newest = $this->db->selectRow('SELECT u.id, u.username FROM '.$this->db->table('users').' AS u WHERE u.group_id != ? ORDER BY u.registered DESC LIMIT 1', self::UNVERIFIED_GROUP);
		$posts = $this->db->selectRow('SELECT SUM(f.num_topics) AS num_topics, SUM(f.num_posts) AS num_posts FROM '.$this->db->table('forums').' AS f');

		return new Statistics($users, $newest?->int('id') ?? 0, $newest?->string('username') ?? '', $posts?->nullableInt('num_topics') ?? 0, $posts?->nullableInt('num_posts') ?? 0);
	}

	public function onlineVisitors(): array {
		return array_map(static fn (Row $row): OnlineVisitor => new OnlineVisitor($row->int('user_id'), $row->string('ident')),
			$this->db->select('SELECT o.user_id, o.ident FROM '.$this->db->table('online').' AS o WHERE o.idle=? ORDER BY o.ident', 0));
	}
}
