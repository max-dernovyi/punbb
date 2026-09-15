<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\DatabaseException;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Profile\Api\Data\AvatarInterface;
use PunBB\Module\Profile\Api\Data\DetailsInterface;
use PunBB\Module\Profile\Api\Data\EmailActivationInterface;
use PunBB\Module\Profile\Api\Data\EmailChangeInterface;
use PunBB\Module\Profile\Api\Data\ForumModeratorsInterface;
use PunBB\Module\Profile\Api\Data\ModeratorInterface;
use PunBB\Module\Profile\Api\Data\PasswordInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\RenameInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;

/**
 * The profiles, read from and written to the users table, and the names and
 * moderators the posts, topics, forums and online tables carry.
 */
final class Profiles implements ProfilesInterface {
	private const COLUMN = '/^[a-z_][a-z0-9_]*$/';

	public function __construct(private readonly Connection $db) {}

	public function user(int $id): ?ProfileUserInterface {
		$row = $this->db->selectRow('SELECT u.*, g.g_id, g.g_user_title, g.g_moderator FROM '.$this->db->table('users').' AS u LEFT JOIN '.$this->db->table('groups').' AS g ON g.g_id=u.group_id WHERE u.id=?', $id);

		return $row !== null ? new ProfileUser($row->values()) : null;
	}

	public function resetPassword(PasswordInterface ...$passwords): void {
		foreach ($passwords as $password)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET password=?, activate_key=NULL WHERE id=?', $password->hash(), $password->userId());
	}

	public function changePassword(PasswordInterface ...$passwords): void {
		foreach ($passwords as $password)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET password=? WHERE id=?', $password->hash(), $password->userId());
	}

	public function confirmEmail(int ...$userIds): void {
		foreach ($userIds as $userId)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET email=activate_string, activate_string=NULL, activate_key=NULL WHERE id=?', $userId);
	}

	public function usernamesWithEmail(string $email): array {
		return array_map(static fn (Row $row): string => $row->string('username'),
			$this->db->select('SELECT u.id, u.username FROM '.$this->db->table('users').' AS u WHERE u.email=?', $email));
	}

	public function changeEmail(EmailChangeInterface ...$changes): void {
		foreach ($changes as $change)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET email=? WHERE id=?', $change->email(), $change->userId());
	}

	public function requestEmailChange(EmailActivationInterface ...$activations): void {
		foreach ($activations as $activation)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET activate_string=?, activate_key=? WHERE id=?', $activation->email(), $activation->key(), $activation->userId());
	}

	public function moveToGroup(int $groupId, int ...$userIds): void {
		foreach ($userIds as $userId)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET group_id=? WHERE id=?', $groupId, $userId);
	}

	public function groupModerates(int $groupId): bool {
		return (int) $this->db->selectValue('SELECT g.g_moderator FROM '.$this->db->table('groups').' AS g WHERE g.g_id=?', $groupId) === 1;
	}

	public function forumModerators(): array {
		return array_map(static fn (Row $row): ForumModerators => new ForumModerators($row->int('id'), self::moderators($row->nullableString('moderators'))),
			$this->db->select('SELECT f.id, f.moderators FROM '.$this->db->table('forums').' AS f'));
	}

	public function storeModerators(ForumModeratorsInterface ...$forums): void {
		foreach ($forums as $forum)
			$this->db->execute('UPDATE '.$this->db->table('forums').' SET moderators=? WHERE id=?', self::serialized($forum->moderators()), $forum->forumId());
	}

	public function storeAvatar(AvatarInterface ...$avatars): void {
		foreach ($avatars as $avatar)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET avatar=?, avatar_height=?, avatar_width=? WHERE id=?', $avatar->type(), $avatar->height(), $avatar->width(), $avatar->userId());
	}

	public function updateDetails(DetailsInterface ...$details): void {
		foreach ($details as $section)
		{
			$set = array();
			$values = array();
			foreach ($section->columns() as $column)
			{
				if (preg_match(self::COLUMN, $column) !== 1)
					throw new DatabaseException(sprintf('"%s" is not a column of the users table', $column));

				$set[] = $this->db->platform()->quoteIdentifier($column).'=?';
				$values[] = $section->value($column);
			}

			if ($set !== array())
				$this->db->execute('UPDATE '.$this->db->table('users').' SET '.implode(', ', $set).' WHERE id=?', ...array_merge($values, array($section->userId())));
		}
	}

	public function renamePosts(RenameInterface ...$renames): void {
		foreach ($renames as $rename)
			$this->db->execute('UPDATE '.$this->db->table('posts').' SET poster=? WHERE poster_id=?', $rename->newName(), $rename->userId());
	}

	public function renameTopics(RenameInterface ...$renames): void {
		$this->rename('topics', 'poster', $renames);
	}

	public function renameTopicLastPosters(RenameInterface ...$renames): void {
		$this->rename('topics', 'last_poster', $renames);
	}

	public function renameForumLastPosters(RenameInterface ...$renames): void {
		$this->rename('forums', 'last_poster', $renames);
	}

	public function renameOnline(RenameInterface ...$renames): void {
		$this->rename('online', 'ident', $renames);
	}

	public function renameEditors(RenameInterface ...$renames): void {
		$this->rename('posts', 'edited_by', $renames);
	}

	public function groups(): array {
		return array_map(static fn (Row $row): ListedGroup => new ListedGroup($row->int('g_id'), $row->string('g_title')),
			$this->db->select('SELECT g.g_id, g.g_title FROM '.$this->db->table('groups').' AS g WHERE g.g_id!=? ORDER BY g.g_title', ProfileUserInterface::GUESTS));
	}

	public function moderatableForums(): array {
		return array_map(static fn (Row $row): ModeratableForum => new ModeratableForum($row->int('cid'), $row->string('cat_name'), $row->int('fid'), $row->string('forum_name'), self::moderators($row->nullableString('moderators'))),
			$this->db->select('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.moderators FROM '.$this->db->table('categories').' AS c INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id WHERE f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position'));
	}

	/**
	 * A moderators' list as a forum stores it: serialized, username => id.
	 *
	 * @return list<Moderator>
	 */
	public static function moderators(?string $stored): array {
		$moderators = $stored !== null && $stored !== '' ? unserialize($stored, array('allowed_classes' => false)) : array();

		$listed = array();
		foreach (is_array($moderators) ? $moderators : array() as $username => $id)
			$listed[] = new Moderator(is_scalar($id) ? (int) $id : 0, (string) $username);

		return $listed;
	}

	/**
	 * The list as a forum stores it; NULL for none.
	 *
	 * @param list<ModeratorInterface> $moderators
	 */
	public static function serialized(array $moderators): ?string {
		return $moderators !== array() ? serialize(Moderator::stored($moderators)) : null;
	}

	/** @param array<RenameInterface> $renames */
	private function rename(string $table, string $column, array $renames): void {
		foreach ($renames as $rename)
			$this->db->execute('UPDATE '.$this->db->table($table).' SET '.$column.'=? WHERE '.$column.'=?', $rename->newName(), $rename->oldName());
	}
}
