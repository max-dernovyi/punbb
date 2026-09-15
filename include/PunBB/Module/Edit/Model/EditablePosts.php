<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Api\Data\PostEditInterface;
use PunBB\Module\Edit\Api\EditablePostsInterface;

/**
 * The posts, read from the posts, topics and forums tables with the reading
 * permission of the group asking, and written back with their topics.
 */
final class EditablePosts implements EditablePostsInterface {
	public function __construct(private readonly Connection $db) {}

	public function find(int $postId, int $groupId): ?EditablePostInterface {
		$row = $this->db->selectRow('SELECT f.id AS fid, f.forum_name, f.moderators, t.id AS tid, t.subject, t.first_post_id, t.closed, p.poster, p.poster_id, p.message, p.hide_smilies'.
			' FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.id=?', $groupId, $postId);

		if ($row === null)
			return null;

		return new EditablePost(
			$postId,
			$row->int('fid'),
			$row->string('forum_name'),
			Moderator::listOf($row->nullableString('moderators')),
			$row->int('tid'),
			$row->string('subject'),
			$row->int('first_post_id'),
			$row->int('closed') === 1,
			$row->string('poster'),
			$row->int('poster_id'),
			$row->nullableString('message') ?? '',
			$row->int('hide_smilies') === 1
		);
	}

	public function renameTopic(PostEditInterface ...$edits): void {
		foreach ($edits as $edit)
			if ($edit->subject() !== null)
				$this->db->execute('UPDATE '.$this->db->table('topics').' SET subject=? WHERE id=? OR moved_to=?', $edit->subject(), $edit->topicId(), $edit->topicId());
	}

	public function saveMessage(PostEditInterface ...$edits): void {
		foreach ($edits as $edit)
		{
			if ($edit->editedAt() !== null)
				$this->db->execute('UPDATE '.$this->db->table('posts').' SET message=?, hide_smilies=?, edited=?, edited_by=? WHERE id=?',
					$edit->message(), $edit->hidesSmilies(), $edit->editedAt(), $edit->editedBy() ?? '', $edit->postId());
			else
				$this->db->execute('UPDATE '.$this->db->table('posts').' SET message=?, hide_smilies=? WHERE id=?', $edit->message(), $edit->hidesSmilies(), $edit->postId());
		}
	}
}
