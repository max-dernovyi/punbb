<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use Closure;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Profile\Api\Data\AvatarInterface;
use PunBB\Module\Profile\Api\Data\DetailsInterface;
use PunBB\Module\Profile\Api\Data\EmailActivationInterface;
use PunBB\Module\Profile\Api\Data\EmailChangeInterface;
use PunBB\Module\Profile\Api\Data\ForumModeratorsInterface;
use PunBB\Module\Profile\Api\Data\ListedGroupInterface;
use PunBB\Module\Profile\Api\Data\ModeratableForumInterface;
use PunBB\Module\Profile\Api\Data\PasswordInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\RenameInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Model\ForumModerators;
use PunBB\Module\Profile\Model\ListedGroup;
use PunBB\Module\Profile\Model\ModeratableForum;
use PunBB\Module\Profile\Model\ProfileUser;
use PunBB\Module\Profile\Model\Profiles;

/**
 * The profile's query points, with the query arrays profile.php built. A
 * query a point changed answers instead; a statement a point changed runs
 * instead, and the repository is handed nothing to store. What was read is
 * left where the page script kept it: the member in $user, the other members
 * with an address in $dupe_list, whether a group moderates in $new_group_mod.
 */
final class ProfilesPlugin {
	public function __construct(private readonly PluggedQuery $queries, private readonly KeptRows $rows, private readonly ProfileFlow $flow) {}

	public function afterUser(ProfilesInterface $subject, ?ProfileUserInterface $result, int $id): ?ProfileUserInterface {
		$query = array(
			'SELECT'	=> 'u.*, g.g_id, g.g_user_title, g.g_moderator',
			'FROM'		=> 'users AS u',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'	=> 'groups AS g',
					'ON'		=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> 'u.id='.$id
		);

		$GLOBALS['id'] = $id;

		if ($this->queries->changed('pf_qr_get_user_info', ProfilesInterface::class.'::user', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? new ProfileUser($row) : null;
		}

		if ($result !== null)
			ProfileState::publish($result);
		else
			$GLOBALS['user'] = false;

		return $result;
	}

	/** @return list<PasswordInterface>|null */
	public function beforeResetPassword(ProfilesInterface $subject, PasswordInterface ...$passwords): ?array {
		return $this->statements($passwords, 'pf_change_pass_key_qr_update_password', 'resetPassword', fn (PasswordInterface $password): array => array(
			array('UPDATE' => 'users', 'SET' => 'password=\''.self::escape($password->hash()).'\', activate_key=NULL', 'WHERE' => 'id='.$password->userId()),
			array('new_password_hash' => $password->hash()),
		));
	}

	/** @return list<PasswordInterface>|null */
	public function beforeChangePassword(ProfilesInterface $subject, PasswordInterface ...$passwords): ?array {
		return $this->statements($passwords, 'pf_change_pass_normal_qr_update_password', 'changePassword', fn (PasswordInterface $password): array => array(
			array('UPDATE' => 'users', 'SET' => 'password=\''.self::escape($password->hash()).'\'', 'WHERE' => 'id='.$password->userId()),
			array('new_password_hash' => $password->hash()),
		));
	}

	/** @return list<int>|null */
	public function beforeConfirmEmail(ProfilesInterface $subject, int ...$userIds): ?array {
		return $this->statements($userIds, 'pf_change_email_key_qr_update_email', 'confirmEmail', static fn (int $userId): array => array(
			array('UPDATE' => 'users', 'SET' => 'email=activate_string, activate_string=NULL, activate_key=NULL', 'WHERE' => 'id='.$userId),
			array(),
		));
	}

	/**
	 * @param list<string> $result
	 * @return list<string>
	 */
	public function afterUsernamesWithEmail(ProfilesInterface $subject, array $result, string $email): array {
		$query = array(
			'SELECT'	=> 'u.id, u.username',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.email=\''.self::escape($email).'\''
		);

		$GLOBALS['new_email'] = $email;

		if ($this->queries->changed('pf_change_email_normal_qr_check_email_dupe', ProfilesInterface::class.'::usernamesWithEmail', $query))
			$result = array_map(static fn (array $row): string => Markers::markup($row['username'] ?? ''), PluggedQuery::rows($query));

		$GLOBALS['dupe_list'] = $result;

		return $result;
	}

	/** @return list<EmailChangeInterface>|null */
	public function beforeChangeEmail(ProfilesInterface $subject, EmailChangeInterface ...$changes): ?array {
		return $this->statements($changes, 'pf_change_email_key_qr_update_email', 'changeEmail', fn (EmailChangeInterface $change): array => array(
			array('UPDATE' => 'users', 'SET' => 'email=\''.self::escape($change->email()).'\'', 'WHERE' => 'id='.$change->userId()),
			array('new_email' => $change->email()),
		));
	}

	/** @return list<EmailActivationInterface>|null */
	public function beforeRequestEmailChange(ProfilesInterface $subject, EmailActivationInterface ...$activations): ?array {
		return $this->statements($activations, 'pf_change_email_normal_qr_update_email_activation', 'requestEmailChange', fn (EmailActivationInterface $activation): array => array(
			array('UPDATE' => 'users', 'SET' => 'activate_string=\''.self::escape($activation->email()).'\', activate_key=\''.self::escape($activation->key()).'\'', 'WHERE' => 'id='.$activation->userId()),
			array('new_email' => $activation->email(), 'new_email_key' => $activation->key()),
		));
	}

	/** @return list<int>|null */
	public function beforeMoveToGroup(ProfilesInterface $subject, int $groupId, int ...$userIds): ?array {
		$kept = $this->statements($userIds, 'pf_change_group_qr_update_group', 'moveToGroup', static fn (int $userId): array => array(
			array('UPDATE' => 'users', 'SET' => 'group_id='.$groupId, 'WHERE' => 'id='.$userId),
			array('new_group_id' => $groupId),
		));

		return $kept !== null ? array_merge(array($groupId), $kept) : null;
	}

	public function afterGroupModerates(ProfilesInterface $subject, bool $result, int $groupId): bool {
		$query = array(
			'SELECT'	=> 'g.g_moderator',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id='.$groupId
		);

		if ($this->queries->changed('pf_change_group_qr_check_new_group_mod', ProfilesInterface::class.'::groupModerates', $query))
			$result = Markers::markup(PluggedQuery::value($query)) === '1';

		$GLOBALS['new_group_mod'] = $result ? '1' : '0';

		return $result;
	}

	/**
	 * @param list<ForumModeratorsInterface> $result
	 * @return list<ForumModeratorsInterface>
	 */
	public function afterForumModerators(ProfilesInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'f.id, f.moderators',
			'FROM'		=> 'forums AS f'
		);

		if ($this->queries->changed($this->flow->prefix().'_qr_get_all_forum_mods', ProfilesInterface::class.'::forumModerators', $query))
			$result = array_map(static fn (array $row): ForumModerators => new ForumModerators((int) Markers::markup($row['id'] ?? 0), Profiles::moderators(isset($row['moderators']) ? Markers::markup($row['moderators']) : null)), PluggedQuery::rows($query));

		return $result;
	}

	/** @return list<ForumModeratorsInterface>|null */
	public function beforeStoreModerators(ProfilesInterface $subject, ForumModeratorsInterface ...$forums): ?array {
		return $this->statements($forums, $this->flow->prefix().'_qr_update_forum_moderators', 'storeModerators', function (ForumModeratorsInterface $forum): array {
			$serialized = Profiles::serialized($forum->moderators());
			$moderators = $serialized !== null ? '\''.self::escape($serialized).'\'' : 'NULL';

			return array(
				array('UPDATE' => 'forums', 'SET' => 'moderators='.$moderators, 'WHERE' => 'id='.$forum->forumId()),
				array('cur_moderators' => $moderators, 'cur_forum' => array('id' => $forum->forumId())),
			);
		});
	}

	/** @return list<AvatarInterface>|null */
	public function beforeStoreAvatar(ProfilesInterface $subject, AvatarInterface ...$avatars): ?array {
		return $this->statements($avatars, 'pf_change_details_avatar_qr_update_avatar', 'storeAvatar', static fn (AvatarInterface $avatar): array => array(
			array('UPDATE' => 'users', 'SET' => 'avatar=\''.$avatar->type().'\', avatar_height=\''.$avatar->height().'\', avatar_width=\''.$avatar->width().'\'', 'WHERE' => 'id='.$avatar->userId()),
			array('avatar_type' => $avatar->type(), 'avatar_width' => $avatar->width(), 'avatar_height' => $avatar->height()),
		));
	}

	/** @return list<DetailsInterface>|null */
	public function beforeUpdateDetails(ProfilesInterface $subject, DetailsInterface ...$details): ?array {
		return $this->statements($details, 'pf_change_details_qr_update_user', 'updateDetails', function (DetailsInterface $section): array {
			$values = array();
			foreach ($section->columns() as $column)
			{
				$value = $section->value($column);
				$values[] = $column.'='.($value !== null ? '\''.self::escape($value).'\'' : 'NULL');
			}

			return array(
				array('UPDATE' => 'users', 'SET' => implode(',', $values), 'WHERE' => 'id='.$section->userId()),
				array('new_values' => $values),
			);
		});
	}

	/** @return list<RenameInterface>|null */
	public function beforeRenamePosts(ProfilesInterface $subject, RenameInterface ...$renames): ?array {
		return $this->renames($renames, 'pf_change_details_qr_update_posts_poster', 'renamePosts', 'posts', 'poster', static fn (RenameInterface $rename): string => 'poster_id='.$rename->userId());
	}

	/** @return list<RenameInterface>|null */
	public function beforeRenameTopics(ProfilesInterface $subject, RenameInterface ...$renames): ?array {
		return $this->renames($renames, 'pf_change_details_qr_update_topics_poster', 'renameTopics', 'topics', 'poster');
	}

	/** @return list<RenameInterface>|null */
	public function beforeRenameTopicLastPosters(ProfilesInterface $subject, RenameInterface ...$renames): ?array {
		return $this->renames($renames, 'pf_change_details_qr_update_topics_last_poster', 'renameTopicLastPosters', 'topics', 'last_poster');
	}

	/** @return list<RenameInterface>|null */
	public function beforeRenameForumLastPosters(ProfilesInterface $subject, RenameInterface ...$renames): ?array {
		return $this->renames($renames, 'pf_change_details_qr_update_forums_last_poster', 'renameForumLastPosters', 'forums', 'last_poster');
	}

	/** @return list<RenameInterface>|null */
	public function beforeRenameOnline(ProfilesInterface $subject, RenameInterface ...$renames): ?array {
		return $this->renames($renames, 'pf_change_details_qr_update_online_ident', 'renameOnline', 'online', 'ident');
	}

	/** @return list<RenameInterface>|null */
	public function beforeRenameEditors(ProfilesInterface $subject, RenameInterface ...$renames): ?array {
		return $this->renames($renames, 'pf_change_details_qr_update_posts_edited_by', 'renameEditors', 'posts', 'edited_by');
	}

	/**
	 * @param list<ListedGroupInterface> $result
	 * @return list<ListedGroupInterface>
	 */
	public function afterGroups(ProfilesInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'g.g_id, g.g_title',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id!='.ProfileUserInterface::GUESTS,
			'ORDER BY'	=> 'g.g_title'
		);

		if ($this->queries->changed('pf_change_details_admin_qr_get_groups', ProfilesInterface::class.'::groups', $query))
			$result = array_map(static fn (array $row): ListedGroup => new ListedGroup((int) Markers::markup($row['g_id'] ?? 0), Markers::markup($row['g_title'] ?? '')), PluggedQuery::rows($query));

		return $result;
	}

	/**
	 * @param list<ModeratableForumInterface> $result
	 * @return list<ModeratableForumInterface>
	 */
	public function afterModeratableForums(ProfilesInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.moderators',
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

		if ($this->queries->changed('pf_change_details_admin_qr_get_cats_and_forums', ProfilesInterface::class.'::moderatableForums', $query))
		{
			$result = array();
			foreach (PluggedQuery::rows($query) as $row)
			{
				$forum = new ModeratableForum((int) Markers::markup($row['cid'] ?? 0), Markers::markup($row['cat_name'] ?? ''), (int) Markers::markup($row['fid'] ?? 0), Markers::markup($row['forum_name'] ?? ''), Profiles::moderators(isset($row['moderators']) ? Markers::markup($row['moderators']) : null));
				$this->rows->keep($forum, $row);
				$result[] = $forum;
			}
		}

		return $result;
	}

	/**
	 * @param array<RenameInterface> $renames
	 * @param ?Closure(RenameInterface): string $where the rows renamed; those carrying the old name when null
	 * @return list<RenameInterface>|null
	 */
	private function renames(array $renames, string $point, string $method, string $table, string $column, ?Closure $where = null): ?array {
		return $this->statements($renames, $point, $method, fn (RenameInterface $rename): array => array(
			array('UPDATE' => $table, 'SET' => $column.'=\''.self::escape($rename->newName()).'\'', 'WHERE' => $where !== null ? $where($rename) : $column.'=\''.self::escape($rename->oldName()).'\''),
			array('old_username' => $rename->oldName()),
		));
	}

	/**
	 * Runs $point over the statement $build makes for each item, with the
	 * variables it names; a statement the point changed runs instead.
	 *
	 * @template T
	 * @param array<T> $items
	 * @param Closure(T): array{array<string, mixed>, array<string, mixed>} $build the statement, and what else the point sees
	 * @return list<T>|null the items the repository still stores; null when it stores them all
	 */
	private function statements(array $items, string $point, string $method, Closure $build): ?array {
		$kept = array();
		foreach ($items as $item)
		{
			[$query, $variables] = $build($item);

			$locals = array();
			foreach (array_keys($variables) as $name)
				$locals[$name] = &$variables[$name];

			if ($this->queries->changed($point, ProfilesInterface::class.'::'.$method, $query, $locals))
				PluggedQuery::run($query);
			else
				$kept[] = $item;
		}

		return count($kept) !== count($items) ? $kept : null;
	}

	private static function escape(string $text): string {
		return Markers::markup(LegacyConnection::legacy()->escape($text));
	}
}
