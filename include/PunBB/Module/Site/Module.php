<?php

declare(strict_types=1);

namespace PunBB\Module\Site;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;

/**
 * What a page reads about the site it is served on: the visitor, the board's
 * settings, the language pack, the URL scheme and the formats text is shown
 * in; and the board-wide work pages share, such as the caches they rebuild.
 * The module declares them; the bootstrap's side wires them. The accounts and
 * the visits are its tables, which every module reads.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Site';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {}

	public function tables(Platform $platform): array {
		// MySQL indexes a prefix of a long text column
		$mysql = $platform === Platform::Mysql;

		return array(
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
			), 'HEAP', removedIndexes: array('user_id_idx')),

			new Table('users', array(
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
			), removedColumns: array('use_avatar', 'save_pass')),
		);
	}
}
