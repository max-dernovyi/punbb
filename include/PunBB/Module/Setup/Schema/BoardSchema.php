<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Schema;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;

/**
 * The tables this release installs, and the updater brings a board up to.
 * MySQL indexes a prefix of a long text column, and SQLite keys the search
 * words by their id.
 */
final class BoardSchema {
	/** @return list<Table> in the order they are created */
	public static function tables(string $dbType): array {
		$mysql = $dbType === 'mysqli' || $dbType === 'mysqli_innodb';

		return array(
			new Table('bans', array(
				new Column('id', 'SERIAL'),
				new Column('username', 'VARCHAR(200)', true),
				new Column('ip', 'VARCHAR(255)', true),
				new Column('email', 'VARCHAR(80)', true),
				new Column('message', 'VARCHAR(255)', true),
				new Column('expire', 'INT(10) UNSIGNED', true),
				new Column('ban_creator', 'INT(10) UNSIGNED', false, 0),
			), array('id')),

			new Table('categories', array(
				new Column('id', 'SERIAL'),
				new Column('cat_name', 'VARCHAR(80)', false, 'New Category'),
				new Column('disp_position', 'INT(10)', false, 0),
			), array('id')),

			new Table('censoring', array(
				new Column('id', 'SERIAL'),
				new Column('search_for', 'VARCHAR(60)', false, ''),
				new Column('replace_with', 'VARCHAR(60)', false, ''),
			), array('id')),

			new Table('config', array(
				new Column('conf_name', 'VARCHAR(255)', false, ''),
				new Column('conf_value', 'TEXT', true),
			), array('conf_name')),

			self::extensions(),
			self::extensionHooks(),

			new Table('forum_perms', array(
				new Column('group_id', 'INT(10)', false, 0),
				new Column('forum_id', 'INT(10)', false, 0),
				new Column('read_forum', 'TINYINT(1)', false, 1),
				new Column('post_replies', 'TINYINT(1)', false, 1),
				new Column('post_topics', 'TINYINT(1)', false, 1),
			), array('group_id', 'forum_id')),

			new Table('forums', array(
				new Column('id', 'SERIAL'),
				new Column('forum_name', 'VARCHAR(80)', false, 'New forum'),
				new Column('forum_desc', 'TEXT', true),
				new Column('redirect_url', 'VARCHAR(100)', true),
				new Column('moderators', 'TEXT', true),
				new Column('num_topics', 'MEDIUMINT(8) UNSIGNED', false, 0),
				new Column('num_posts', 'MEDIUMINT(8) UNSIGNED', false, 0),
				new Column('last_post', 'INT(10) UNSIGNED', true),
				new Column('last_post_id', 'INT(10) UNSIGNED', true),
				new Column('last_poster', 'VARCHAR(200)', true),
				new Column('sort_by', 'TINYINT(1)', false, 0),
				new Column('disp_position', 'INT(10)', false, 0),
				new Column('cat_id', 'INT(10) UNSIGNED', false, 0),
			), array('id')),

			new Table('groups', array(
				new Column('g_id', 'SERIAL'),
				new Column('g_title', 'VARCHAR(50)', false, ''),
				new Column('g_user_title', 'VARCHAR(50)', true),
				new Column('g_moderator', 'TINYINT(1)', false, 0),
				new Column('g_mod_edit_users', 'TINYINT(1)', false, 0),
				new Column('g_mod_rename_users', 'TINYINT(1)', false, 0),
				new Column('g_mod_change_passwords', 'TINYINT(1)', false, 0),
				new Column('g_mod_ban_users', 'TINYINT(1)', false, 0),
				new Column('g_read_board', 'TINYINT(1)', false, 1),
				new Column('g_view_users', 'TINYINT(1)', false, 1),
				new Column('g_post_replies', 'TINYINT(1)', false, 1),
				new Column('g_post_topics', 'TINYINT(1)', false, 1),
				new Column('g_edit_posts', 'TINYINT(1)', false, 1),
				new Column('g_delete_posts', 'TINYINT(1)', false, 1),
				new Column('g_delete_topics', 'TINYINT(1)', false, 1),
				new Column('g_set_title', 'TINYINT(1)', false, 1),
				new Column('g_search', 'TINYINT(1)', false, 1),
				new Column('g_search_users', 'TINYINT(1)', false, 1),
				new Column('g_send_email', 'TINYINT(1)', false, 1),
				new Column('g_post_flood', 'SMALLINT(6)', false, 30),
				new Column('g_search_flood', 'SMALLINT(6)', false, 30),
				new Column('g_email_flood', 'SMALLINT(6)', false, 60),
			), array('g_id')),

			new Table('online', array(
				new Column('user_id', 'INT(10) UNSIGNED', false, 1),
				new Column('ident', 'VARCHAR(200)', false, ''),
				new Column('logged', 'INT(10) UNSIGNED', false, 0),
				new Column('idle', 'TINYINT(1)', false, 0),
				new Column('csrf_token', 'VARCHAR(40)', false, ''),
				new Column('prev_url', 'VARCHAR(255)', true),
				new Column('last_post', 'INT(10) UNSIGNED', true),
				new Column('last_search', 'INT(10) UNSIGNED', true),
			), array(), array(
				'user_id_ident_idx'	=> array('user_id', $mysql ? 'ident(40)' : 'ident'),
			), array(
				'ident_idx'			=> array($mysql ? 'ident(40)' : 'ident'),
				'logged_idx'		=> array('logged'),
			), 'HEAP'),

			new Table('posts', array(
				new Column('id', 'SERIAL'),
				new Column('poster', 'VARCHAR(200)', false, ''),
				new Column('poster_id', 'INT(10) UNSIGNED', false, 1),
				new Column('poster_ip', 'VARCHAR(39)', true),
				new Column('poster_email', 'VARCHAR(80)', true),
				new Column('message', 'TEXT', true),
				new Column('hide_smilies', 'TINYINT(1)', false, 0),
				new Column('posted', 'INT(10) UNSIGNED', false, 0),
				new Column('edited', 'INT(10) UNSIGNED', true),
				new Column('edited_by', 'VARCHAR(200)', true),
				new Column('topic_id', 'INT(10) UNSIGNED', false, 0),
			), array('id'), array(), array(
				'topic_id_idx'	=> array('topic_id'),
				'multi_idx'		=> array('poster_id', 'topic_id'),
				'posted_idx'	=> array('posted'),
			)),

			new Table('ranks', array(
				new Column('id', 'SERIAL'),
				new Column('rank', 'VARCHAR(50)', false, ''),
				new Column('min_posts', 'MEDIUMINT(8) UNSIGNED', false, 0),
			), array('id')),

			new Table('reports', array(
				new Column('id', 'SERIAL'),
				new Column('post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('topic_id', 'INT(10) UNSIGNED', false, 0),
				new Column('forum_id', 'INT(10) UNSIGNED', false, 0),
				new Column('reported_by', 'INT(10) UNSIGNED', false, 0),
				new Column('created', 'INT(10) UNSIGNED', false, 0),
				new Column('message', 'TEXT', true),
				new Column('zapped', 'INT(10) UNSIGNED', true),
				new Column('zapped_by', 'INT(10) UNSIGNED', true),
			), array('id'), array(), array(
				'zapped_idx'	=> array('zapped'),
			)),

			...self::search($dbType),

			new Table('subscriptions', array(
				new Column('user_id', 'INT(10) UNSIGNED', false, 0),
				new Column('topic_id', 'INT(10) UNSIGNED', false, 0),
			), array('user_id', 'topic_id')),

			self::forumSubscriptions(),

			new Table('topics', array(
				new Column('id', 'SERIAL'),
				new Column('poster', 'VARCHAR(200)', false, ''),
				new Column('subject', 'VARCHAR(255)', false, ''),
				new Column('posted', 'INT(10) UNSIGNED', false, 0),
				new Column('first_post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('last_post', 'INT(10) UNSIGNED', false, 0),
				new Column('last_post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('last_poster', 'VARCHAR(200)', true),
				new Column('num_views', 'MEDIUMINT(8) UNSIGNED', false, 0),
				new Column('num_replies', 'MEDIUMINT(8) UNSIGNED', false, 0),
				new Column('closed', 'TINYINT(1)', false, 0),
				new Column('sticky', 'TINYINT(1)', false, 0),
				new Column('moved_to', 'INT(10) UNSIGNED', true),
				new Column('forum_id', 'INT(10) UNSIGNED', false, 0),
			), array('id'), array(), array(
				'forum_id_idx'		=> array('forum_id'),
				'moved_to_idx'		=> array('moved_to'),
				'last_post_idx'		=> array('last_post'),
				'first_post_id_idx'	=> array('first_post_id'),
			)),

			self::users($mysql),
		);
	}

	/** Table $name as tables() declares it for $dbType. */
	public static function table(string $name, string $dbType): Table {
		foreach (self::tables($dbType) as $table)
			if ($table->name === $name)
				return $table;

		throw new SchemaException(sprintf('The board has no table "%s"', $name));
	}

	private static function extensions(): Table {
		return new Table('extensions', array(
			new Column('id', 'VARCHAR(150)', false, ''),
			new Column('title', 'VARCHAR(255)', false, ''),
			new Column('version', 'VARCHAR(25)', false, ''),
			new Column('description', 'TEXT', true),
			new Column('author', 'VARCHAR(50)', false, ''),
			new Column('uninstall', 'TEXT', true),
			new Column('uninstall_note', 'TEXT', true),
			new Column('disabled', 'TINYINT(1)', false, 0),
			new Column('dependencies', 'VARCHAR(255)', false, ''),
		), array('id'));
	}

	private static function extensionHooks(): Table {
		return new Table('extension_hooks', array(
			new Column('id', 'VARCHAR(150)', false, ''),
			new Column('extension_id', 'VARCHAR(50)', false, ''),
			new Column('code', 'TEXT', true),
			new Column('installed', 'INT(10) UNSIGNED', false, 0),
			new Column('priority', 'TINYINT(1) UNSIGNED', false, 5),
		), array('id', 'extension_id'));
	}

	private static function forumSubscriptions(): Table {
		return new Table('forum_subscriptions', array(
			new Column('user_id', 'INT(10) UNSIGNED', false, 0),
			new Column('forum_id', 'INT(10) UNSIGNED', false, 0),
		), array('user_id', 'forum_id'));
	}

	/** @return list<Table> the search cache, matches and words */
	private static function search(string $dbType): array {
		$mysql = $dbType === 'mysqli' || $dbType === 'mysqli_innodb';
		$sqlite = $dbType === 'sqlite3';

		return array(
			new Table('search_cache', array(
				new Column('id', 'INT(10) UNSIGNED', false, 0),
				new Column('ident', 'VARCHAR(200)', false, ''),
				new Column('search_data', 'TEXT', true),
			), array('id'), array(), array(
				'ident_idx'	=> array($mysql ? 'ident(8)' : 'ident'),
			)),

			new Table('search_matches', array(
				new Column('post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('word_id', 'INT(10) UNSIGNED', false, 0),
				new Column('subject_match', 'TINYINT(1)', false, 0),
			), array(), array(), array(
				'word_id_idx'	=> array('word_id'),
				'post_id_idx'	=> array('post_id'),
			)),

			new Table('search_words', array(
				new Column('id', 'SERIAL'),
				new Column('word', 'VARCHAR(20)', false, '', 'bin'),
			), $sqlite ? array('id') : array('word'), $sqlite ? array('word_idx' => array('word')) : array(), array(
				'id_idx'	=> array('id'),
			)),
		);
	}

	private static function users(bool $mysql): Table {
		return new Table('users', array(
			new Column('id', 'SERIAL'),
			new Column('group_id', 'INT(10) UNSIGNED', false, 3),
			new Column('username', 'VARCHAR(200)', false, ''),
			// Wide enough for password_hash() output and its successors; the salted SHA-1 of older rows keeps fitting.
			new Column('password', 'VARCHAR(255)', false, ''),
			new Column('salt', 'VARCHAR(12)', true),
			new Column('email', 'VARCHAR(80)', false, ''),
			new Column('title', 'VARCHAR(50)', true),
			new Column('realname', 'VARCHAR(40)', true),
			new Column('url', 'VARCHAR(100)', true),
			new Column('facebook', 'VARCHAR(100)', true),
			new Column('twitter', 'VARCHAR(100)', true),
			new Column('skype', 'VARCHAR(100)', true),
			new Column('icq', 'VARCHAR(12)', true),
			new Column('linkedin', 'VARCHAR(100)', true),
			new Column('jabber', 'VARCHAR(80)', true),
			new Column('msn', 'VARCHAR(80)', true),
			new Column('aim', 'VARCHAR(30)', true),
			new Column('yahoo', 'VARCHAR(30)', true),
			new Column('location', 'VARCHAR(30)', true),
			new Column('signature', 'TEXT', true),
			new Column('disp_topics', 'TINYINT(3) UNSIGNED', true),
			new Column('disp_posts', 'TINYINT(3) UNSIGNED', true),
			new Column('email_setting', 'TINYINT(1)', false, 1),
			new Column('notify_with_post', 'TINYINT(1)', false, 0),
			new Column('auto_notify', 'TINYINT(1)', false, 0),
			new Column('show_smilies', 'TINYINT(1)', false, 1),
			new Column('show_img', 'TINYINT(1)', false, 1),
			new Column('show_img_sig', 'TINYINT(1)', false, 1),
			new Column('show_avatars', 'TINYINT(1)', false, 1),
			new Column('show_sig', 'TINYINT(1)', false, 1),
			new Column('access_keys', 'TINYINT(1)', false, 0),
			new Column('timezone', 'FLOAT', false, 0),
			new Column('dst', 'TINYINT(1)', false, 0),
			new Column('time_format', 'INT(10) UNSIGNED', false, 0),
			new Column('date_format', 'INT(10) UNSIGNED', false, 0),
			new Column('language', 'VARCHAR(25)', false, 'English'),
			new Column('style', 'VARCHAR(25)', false, 'Oxygen'),
			new Column('num_posts', 'INT(10) UNSIGNED', false, 0),
			new Column('last_post', 'INT(10) UNSIGNED', true),
			new Column('last_search', 'INT(10) UNSIGNED', true),
			new Column('last_email_sent', 'INT(10) UNSIGNED', true),
			new Column('registered', 'INT(10) UNSIGNED', false, 0),
			new Column('registration_ip', 'VARCHAR(39)', false, '0.0.0.0'),
			new Column('last_visit', 'INT(10) UNSIGNED', false, 0),
			new Column('admin_note', 'VARCHAR(30)', true),
			new Column('activate_string', 'VARCHAR(80)', true),
			new Column('activate_key', 'VARCHAR(8)', true),
			new Column('avatar', 'TINYINT(3) UNSIGNED', false, 0),
			new Column('avatar_width', 'TINYINT(3) UNSIGNED', false, 0),
			new Column('avatar_height', 'TINYINT(3) UNSIGNED', false, 0),
		), array('id'), array(), array(
			'registered_idx'	=> array('registered'),
			'username_idx'		=> array($mysql ? 'username(8)' : 'username'),
		));
	}
}
