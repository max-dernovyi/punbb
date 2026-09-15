<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Install\Api\BoardInstallationInterface;
use PunBB\Module\Install\Api\Data\AdministratorInterface;
use PunBB\Module\Install\Api\Data\ExtensionInterface;
use PunBB\Module\Install\Api\Data\RankInterface;
use PunBB\Module\Install\Api\Data\SettingInterface;
use PunBB\Module\Install\Api\Data\WelcomeInterface;

/**
 * The board's first rows. The preset ids are written out, except on PostgreSQL,
 * where a written id would leave its sequence behind: a fresh sequence hands
 * out the same ids.
 */
final class BoardInstallation implements BoardInstallationInterface {
	private const GROUP_COLUMNS = array('g_title', 'g_user_title', 'g_moderator', 'g_mod_edit_users', 'g_mod_rename_users', 'g_mod_change_passwords', 'g_mod_ban_users', 'g_read_board', 'g_view_users', 'g_post_replies', 'g_post_topics', 'g_edit_posts', 'g_delete_posts', 'g_delete_topics', 'g_set_title', 'g_search', 'g_search_users', 'g_send_email', 'g_post_flood', 'g_search_flood', 'g_email_flood');

	/** @var list<list<int|string|null>> each group by id, its values in the order of GROUP_COLUMNS */
	private const GROUPS = array(
		array('Administrators', 'Administrator', 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0),
		array('Guest', null, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 60, 30, 0),
		array('Members', null, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 60, 30, 60),
		array('Moderators', 'Moderator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0),
	);

	/** The address the installation's own rows are recorded from. */
	private const LOCAL_ADDRESS = '127.0.0.1';

	public function __construct(private readonly Connection $db) {}

	public function isInstalled(): bool {
		return (int) $this->db->selectValue('SELECT COUNT(u.id) FROM '.$this->db->table('users').' AS u WHERE u.id=1') > 0;
	}

	public function addGroups(): void {
		foreach (self::GROUPS as $index => $values)
			$this->insert('groups', array_combine(self::GROUP_COLUMNS, $values), 'g_id', $index + 1);
	}

	public function addGuest(): void {
		$this->insert('users', array('group_id' => 2, 'username' => 'Guest', 'password' => 'Guest', 'email' => 'Guest'), 'id', 1);
	}

	public function addAdministrator(AdministratorInterface $administrator): int {
		$this->insert('users', array(
			'group_id'			=> 1,
			'username'			=> $administrator->username(),
			'password'			=> $administrator->passwordHash(),
			'email'				=> $administrator->email(),
			'language'			=> $administrator->language(),
			'num_posts'			=> 1,
			'last_post'			=> $administrator->registered(),
			'registered'		=> $administrator->registered(),
			'registration_ip'	=> self::LOCAL_ADDRESS,
			'last_visit'		=> $administrator->registered(),
			'salt'				=> $administrator->salt(),
		));

		return $this->db->lastInsertId();
	}

	public function addSettings(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
			$this->insert('config', array('conf_name' => $setting->name(), 'conf_value' => $setting->value()));
	}

	public function addWelcome(WelcomeInterface $welcome): int {
		$this->insert('categories', array('cat_name' => $welcome->category(), 'disp_position' => 1));

		$this->insert('forums', array(
			'forum_name'	=> $welcome->forum(),
			'forum_desc'	=> $welcome->forumDescription(),
			'num_topics'	=> 1,
			'num_posts'		=> 1,
			'last_post'		=> $welcome->posted(),
			'last_post_id'	=> 1,
			'last_poster'	=> $welcome->poster(),
			'disp_position'	=> 1,
			'cat_id'		=> $this->db->lastInsertId(),
		));

		$this->insert('topics', array(
			'poster'		=> $welcome->poster(),
			'subject'		=> $welcome->subject(),
			'posted'		=> $welcome->posted(),
			'first_post_id'	=> 1,
			'last_post'		=> $welcome->posted(),
			'last_post_id'	=> 1,
			'last_poster'	=> $welcome->poster(),
			'forum_id'		=> $this->db->lastInsertId(),
		));

		$this->insert('posts', array(
			'poster'		=> $welcome->poster(),
			'poster_id'		=> $welcome->posterId(),
			'poster_ip'		=> self::LOCAL_ADDRESS,
			'message'		=> $welcome->message(),
			'posted'		=> $welcome->posted(),
			'topic_id'		=> $this->db->lastInsertId(),
		), 'id', 1);

		return $this->db->lastInsertId();
	}

	public function addRanks(RankInterface ...$ranks): void {
		foreach ($ranks as $rank)
			$this->insert('ranks', array('rank' => $rank->title(), 'min_posts' => $rank->minPosts()));
	}

	public function addExtension(ExtensionInterface $extension): void {
		$this->insert('extensions', array(
			'id'				=> $extension->id(),
			'title'				=> $extension->title(),
			'version'			=> $extension->version(),
			'description'		=> $extension->description(),
			'author'			=> $extension->author(),
			'uninstall'			=> null,
			'uninstall_note'	=> null,
			'dependencies'		=> '||',
		));

		foreach ($extension->hooks() as $hook)
			$this->insert('extension_hooks', array(
				'id'			=> $hook->point(),
				'extension_id'	=> $extension->id(),
				'code'			=> $hook->code(),
				'installed'		=> $hook->installed(),
				'priority'		=> $hook->priority(),
			));
	}

	/**
	 * Stores a row of $values, column => value, into $table, with $id in column
	 * $idColumn where the platform takes a written id.
	 *
	 * @param array<string, int|string|null> $values
	 */
	private function insert(string $table, array $values, ?string $idColumn = null, ?int $id = null): void {
		if ($idColumn !== null && $id !== null && $this->db->platform() !== Platform::Pgsql)
			$values[$idColumn] = $id;

		$platform = $this->db->platform();
		$columns = array_map(static fn (string $column): string => $platform->quoteIdentifier($column), array_keys($values));

		$this->db->execute('INSERT INTO '.$this->db->table($table).' ('.implode(', ', $columns).') VALUES ('.implode(', ', array_fill(0, count($values), '?')).')', ...array_values($values));
	}
}
