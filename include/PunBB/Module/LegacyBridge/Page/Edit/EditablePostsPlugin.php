<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Edit;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Api\Data\PostEditInterface;
use PunBB\Module\Edit\Api\EditablePostsInterface;
use PunBB\Module\Edit\Model\EditablePost;
use PunBB\Module\Edit\Model\Moderator;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;

/**
 * The edit page's query points, with the query arrays edit.php built. The post
 * is left in $id and $cur_post, with any column a point added; a statement a
 * point changed runs instead, and the repository is handed no edit to store.
 */
final class EditablePostsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterFind(EditablePostsInterface $subject, ?EditablePostInterface $result, int $postId, int $groupId): ?EditablePostInterface {
		$GLOBALS['id'] = $postId;

		$query = array(
			'SELECT'	=> 'f.id AS fid, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.id AS tid, t.subject, t.posted, t.first_post_id, t.closed, p.poster, p.poster_id, p.message, p.hide_smilies',
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

		if ($this->queries->changed('ed_qr_get_post_info', EditablePostsInterface::class.'::find', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$GLOBALS['cur_post'] = $row ?? false;

			return $row !== null ? self::post($postId, $row) : null;
		}

		$GLOBALS['cur_post'] = $result !== null ? self::row($result) : false;

		return $result;
	}

	/** @return list<PostEditInterface>|null */
	public function beforeRenameTopic(EditablePostsInterface $subject, PostEditInterface ...$edits): ?array {
		$kept = array();
		foreach ($edits as $edit)
		{
			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'subject=\''.Markers::markup(LegacyConnection::legacy()->escape($edit->subject() ?? '')).'\'',
				'WHERE'		=> 'id='.$edit->topicId().' OR moved_to='.$edit->topicId()
			);

			if ($this->queries->changed('ed_qr_update_subject', EditablePostsInterface::class.'::renameTopic', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $edit;
		}

		return count($kept) !== count($edits) ? $kept : null;
	}

	/** @return list<PostEditInterface>|null */
	public function beforeSaveMessage(EditablePostsInterface $subject, PostEditInterface ...$edits): ?array {
		$db = LegacyConnection::legacy();

		$kept = array();
		foreach ($edits as $edit)
		{
			$query = array(
				'UPDATE'	=> 'posts',
				'SET'		=> 'message=\''.Markers::markup($db->escape($edit->message())).'\', hide_smilies=\''.($edit->hidesSmilies() ? 1 : 0).'\'',
				'WHERE'		=> 'id='.$edit->postId()
			);

			if ($edit->editedAt() !== null)
				$query['SET'] .= ', edited='.$edit->editedAt().', edited_by=\''.Markers::markup($db->escape($edit->editedBy() ?? '')).'\'';

			if ($this->queries->changed('ed_qr_update_post', EditablePostsInterface::class.'::saveMessage', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $edit;
		}

		return count($kept) !== count($edits) ? $kept : null;
	}

	/** @return array<string, mixed> $post as a row of the page's query */
	private static function row(EditablePostInterface $post): array {
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
		);
	}

	/** @param array<array-key, mixed> $row */
	private static function post(int $postId, array $row): EditablePost {
		return new EditablePost(
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
			Markers::markup($row['hide_smilies'] ?? 0) === '1'
		);
	}
}
