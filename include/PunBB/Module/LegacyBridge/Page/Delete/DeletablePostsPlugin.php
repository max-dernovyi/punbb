<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Delete;

use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Delete\Api\DeletablePostsInterface;
use PunBB\Module\Delete\Model\DeletablePost;
use PunBB\Module\Delete\Model\Moderator;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The deletion page's query points, with the query arrays delete.php built;
 * the post is left in $cur_post and the post before it in $prev_post, as the
 * page script kept them.
 */
final class DeletablePostsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterFind(DeletablePostsInterface $subject, ?DeletablePostInterface $result, int $postId, int $groupId): ?DeletablePostInterface {
		$GLOBALS['id'] = $postId;

		$query = array(
			'SELECT'	=> 'f.id AS fid, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.id AS tid, t.subject, t.first_post_id, t.closed, p.poster, p.poster_id, p.message, p.hide_smilies, p.posted',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				),
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id='.$postId
		);

		if ($this->queries->changed('dl_qr_get_post_info', DeletablePostsInterface::class.'::find', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? self::post($postId, $row) : null;
			$GLOBALS['cur_post'] = $row ?? false;

			return $result;
		}

		$GLOBALS['cur_post'] = $result !== null ? self::row($result) : false;

		return $result;
	}

	public function afterPreviousPostId(DeletablePostsInterface $subject, ?int $result, int $topicId, int $postId): ?int {
		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id = '.$topicId.' AND p.id < '.$postId,
			'ORDER BY'	=> 'p.id DESC',
			'LIMIT'		=> '1'
		);

		if ($this->queries->changed('dl_post_deleted_get_prev_post_id', DeletablePostsInterface::class.'::previousPostId', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = isset($row['id']) ? (int) Markers::markup($row['id']) : null;
		}

		$GLOBALS['prev_post'] = $result !== null ? array('id' => $result) : false;

		return $result;
	}

	/** @return array<string, mixed> $post as a row of the page's query */
	private static function row(DeletablePostInterface $post): array {
		return array(
			'fid'			=> $post->forumId(),
			'forum_name'	=> $post->forumName(),
			'moderators'	=> Moderator::stored($post->moderators()),
			'tid'			=> $post->topicId(),
			'subject'		=> $post->subject(),
			'first_post_id'	=> $post->firstPostId(),
			'closed'		=> $post->topicClosed() ? 1 : 0,
			'poster'		=> $post->poster(),
			'poster_id'		=> $post->posterId(),
			'message'		=> $post->message(),
			'hide_smilies'	=> $post->hidesSmilies() ? 1 : 0,
			'posted'		=> $post->posted(),
		);
	}

	/** @param array<array-key, mixed> $row */
	private static function post(int $postId, array $row): DeletablePost {
		return new DeletablePost(
			$postId,
			(int) Markers::markup($row['fid'] ?? 0),
			Markers::markup($row['forum_name'] ?? ''),
			Moderator::listOf(isset($row['moderators']) ? Markers::markup($row['moderators']) : null),
			(int) Markers::markup($row['tid'] ?? 0),
			Markers::markup($row['subject'] ?? ''),
			(int) Markers::markup($row['first_post_id'] ?? 0),
			Markers::markup($row['closed'] ?? 0) === '1',
			Markers::markup($row['poster'] ?? ''),
			(int) Markers::markup($row['poster_id'] ?? 0),
			Markers::markup($row['message'] ?? ''),
			Markers::markup($row['hide_smilies'] ?? 0) === '1',
			(int) Markers::markup($row['posted'] ?? 0)
		);
	}
}
