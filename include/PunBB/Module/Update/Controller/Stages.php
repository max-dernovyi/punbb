<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Controller;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Setup\Schema\BoardSchema;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;
use PunBB\Module\Update\Charset\Utf8Text;
use PunBB\Module\Update\Model\Setting;
use PunBB\Module\Update\Model\TextRow;
use PunBB\Module\Update\Parsing\PreparserInterface;

/**
 * The stages of an update: the structure first, then for a 1.2 board its text
 * converted to UTF-8 a table at a time, and every post and signature
 * preparsed; the finish is the controller's.
 */
final class Stages {
	/** The rows a stage handles per request: lower it where a stage times out. */
	public const PER_PAGE = 300;

	private const AVATAR_GIF = 1;

	private const AVATAR_JPG = 2;

	private const AVATAR_PNG = 3;

	/** The tables 1.2 stored text in, converted by MySQL itself. */
	private const MYSQL_TABLES = array('bans', 'categories', 'censoring', 'config', 'extension_hooks', 'extensions', 'forum_perms', 'forums', 'groups', 'online', 'posts', 'ranks', 'reports', 'search_cache', 'search_matches', 'search_words', 'subscriptions', 'topics', 'users');

	/** MySQL's text types, and the binary type holding their bytes while the character set changes. */
	private const BINARY_TYPES = array('char' => 'binary', 'varchar' => 'varbinary', 'tinytext' => 'tinyblob', 'mediumtext' => 'mediumblob', 'text' => 'blob', 'longtext' => 'longblob');

	/** The options 1.2 kept for every moderator, which are a permission of each group now. */
	private const MODERATOR_OPTIONS = array('p_mod_edit_users' => 'g_mod_edit_users', 'p_mod_rename_users' => 'g_mod_rename_users', 'p_mod_change_passwords' => 'g_mod_change_passwords', 'p_mod_ban_users' => 'g_mod_ban_users');

	/** @var array<string, string> each moderator permission's column => the column it is added after */
	private const MODERATOR_COLUMNS = array('g_mod_edit_users' => 'g_moderator', 'g_mod_rename_users' => 'g_mod_edit_users', 'g_mod_change_passwords' => 'g_mod_rename_users', 'g_mod_ban_users' => 'g_mod_change_passwords');

	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly BoardDataInterface $data,
		private readonly ConversionInterface $conversion,
		private readonly SchemaInterface $schema,
		private readonly DatabaseInterface $database,
		private readonly EnvironmentInterface $environment,
		private readonly BoardFilesInterface $files,
		private readonly PreparserInterface $preparser
	) {}

	/**
	 * @param string $version the version the board's database is at
	 * @param array<string, ?string> $config the board's options, as the update found them
	 * @param bool $convert whether the text of a 1.2 board is converted
	 */
	public function run(string $stage, DatabaseSettings $database, string $version, array $config, string $charset, int $startAt, bool $convert): StageResult {
		$from12 = str_starts_with($version, '1.2');
		$converting = '&req_old_charset='.$charset.'&req_per_page='.self::PER_PAGE;

		return match ($stage) {
			'start'				=> $this->start($database, $version, $config, $from12 && $convert ? '?stage=conv_misc'.$converting : '?stage=conv_tables'),
			'conv_misc'			=> $from12 ? $this->convertMisc($config, $charset, '?stage=conv_reports'.$converting) : new StageResult(next: '?stage=conv_tables'),
			'conv_reports'		=> $from12 ? $this->convertBatch('reports', array('message'), array(), 'report', $charset, $startAt, 'conv_reports', 'conv_search_words') : new StageResult(next: '?stage=conv_tables'),
			'conv_search_words'	=> $from12 ? $this->convertBatch('search_words', array('word'), array(), 'search word', $charset, $startAt, 'conv_search_words', 'conv_users') : new StageResult(next: '?stage=conv_tables'),
			'conv_users'		=> $from12 ? $this->convertBatch('users', array('username', 'title', 'realname', 'location', 'signature', 'admin_note'), array('title', 'realname', 'location', 'signature', 'admin_note'), 'user', $charset, $startAt === 0 ? 2 : $startAt, 'conv_users', 'conv_topics') : new StageResult(next: '?stage=conv_tables'),
			'conv_topics'		=> $from12 ? $this->convertBatch('topics', array('poster', 'subject', 'last_poster'), array(), 'topic', $charset, $startAt, 'conv_topics', 'conv_posts') : new StageResult(next: '?stage=conv_tables'),
			'conv_posts'		=> $from12 ? $this->convertBatch('posts', array('poster', 'message', 'edited_by'), array('edited_by'), 'post', $charset, $startAt, 'conv_posts', 'conv_tables') : new StageResult(next: '?stage=conv_tables'),
			'conv_tables'		=> $this->convertTables($database),
			'preparse_posts'	=> $this->preparse('posts', 'message', false, 'Preparsing post', $startAt, 'preparse_posts', 'preparse_sigs'),
			'preparse_sigs'		=> $this->preparse('users', 'signature', true, 'Preparsing signature', $startAt === 0 ? 1 : $startAt, 'preparse_sigs', 'finish'),
			default				=> new StageResult(),
		};
	}

	/**
	 * The structure every release since 1.2 changed, each change made only where it is not made yet.
	 *
	 * @param array<string, ?string> $config
	 */
	private function start(DatabaseSettings $database, string $version, array $config, string $conversion): StageResult {
		$mysql = $database->isMysql();

		// Put back dropped search tables
		if (!$this->schema->tableExists('search_cache') && $mysql)
			foreach (array('search_cache', 'search_matches', 'search_words') as $table)
				$this->schema->createTable(BoardSchema::table($table, $database->type));

		if (!$this->schema->tableExists('extensions'))
			$this->schema->createTable(BoardSchema::table('extensions', $database->type));

		// The collation on "word" in the search_words table is utf8_bin
		if ($mysql)
			foreach ($this->conversion->columns('search_words') as $column)
				if ($column->name() === 'word' && $column->collation() !== 'utf8_bin')
					$this->schema->alterField('search_words', new Column('word', 'VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_bin', false, ''));

		$this->schema->addField('extensions', new Column('uninstall_note', 'TEXT', true), 'uninstall');
		$this->schema->dropField('extensions', 'uninstall_notes');
		$this->schema->addField('extensions', new Column('disabled', 'TINYINT(1)', false, 0), 'uninstall_note');
		$this->schema->addField('extensions', new Column('dependencies', 'VARCHAR(255)', false, ''), 'disabled');

		if (!$this->schema->tableExists('extension_hooks'))
			$this->schema->createTable(BoardSchema::table('extension_hooks', $database->type));

		$this->schema->addField('extension_hooks', new Column('priority', 'TINYINT(1)', false, 5), 'installed');
		$this->schema->alterField('extension_hooks', new Column('id', 'VARCHAR(150)', false, ''));

		if (!$this->schema->tableExists('forum_subscriptions'))
			$this->schema->createTable(BoardSchema::table('forum_subscriptions', $database->type));

		// Every email field is VARCHAR(80)
		$this->schema->alterField('bans', new Column('email', 'VARCHAR(80)', true));
		$this->schema->alterField('posts', new Column('poster_email', 'VARCHAR(80)', true));
		$this->schema->alterField('users', new Column('email', 'VARCHAR(80)', false, ''));
		$this->schema->alterField('users', new Column('jabber', 'VARCHAR(80)', true));
		$this->schema->alterField('users', new Column('msn', 'VARCHAR(80)', true));
		$this->schema->alterField('users', new Column('activate_string', 'VARCHAR(80)', true));

		$this->schema->addField('users', new Column('avatar', 'TINYINT(3) UNSIGNED', false, 0));
		$this->schema->addField('users', new Column('avatar_width', 'TINYINT(3) UNSIGNED', false, 0), 'avatar');
		$this->schema->addField('users', new Column('avatar_height', 'TINYINT(3) UNSIGNED', false, 0), 'avatar_width');

		$this->schema->addField('users', new Column('facebook', 'VARCHAR(100)', true), 'url');
		$this->schema->addField('users', new Column('twitter', 'VARCHAR(100)', true), 'facebook');
		$this->schema->addField('users', new Column('linkedin', 'VARCHAR(100)', true), 'twitter');
		$this->schema->addField('users', new Column('skype', 'VARCHAR(100)', true), 'linkedin');

		$this->convertAvatars($config);

		// password_hash() output is 60 bytes today and may grow with the default algorithm; the column held exactly the 40 of a SHA-1
		$this->schema->alterField('users', new Column('password', 'VARCHAR(255)', false, ''));

		// TEXT fields may be NULL, for consistency
		$this->schema->alterField('posts', new Column('message', 'TEXT', true));
		$this->schema->alterField('reports', new Column('message', 'TEXT', true));

		// Fulltext indexes, which only SVN installs had
		if ($mysql)
		{
			$this->schema->dropIndex('topics', 'subject_idx');
			$this->schema->dropIndex('posts', 'message_idx');
		}

		// Every IP field is VARCHAR(39), for IPv6
		$this->schema->alterField('posts', new Column('poster_ip', 'VARCHAR(39)', true));
		$this->schema->alterField('users', new Column('registration_ip', 'VARCHAR(39)', false, '0.0.0.0'));

		$this->schema->addField('users', new Column('dst', 'TINYINT(1)', false, 0), 'timezone');
		$this->schema->addField('users', new Column('salt', 'VARCHAR(12)', true), 'password');
		$this->schema->addField('users', new Column('access_keys', 'TINYINT(1)', false, 0), 'show_sig');

		$this->schema->addField('online', new Column('csrf_token', 'VARCHAR(40)', false, ''));
		$this->schema->addField('online', new Column('prev_url', 'VARCHAR(255)', true));
		$this->schema->addField('online', new Column('last_post', 'INT(10) UNSIGNED', true));
		$this->schema->addField('online', new Column('last_search', 'INT(10) UNSIGNED', true));

		$this->schema->dropField('users', 'use_avatar');
		$this->schema->dropField('users', 'save_pass');
		$this->schema->dropField('groups', 'g_edit_subjects_interval');

		$this->addOptions($config);

		// Server timezone is now simply the default timezone
		if (!array_key_exists('o_default_timezone', $config))
			$this->settings->rename('o_server_timezone', 'o_default_timezone');

		// A visit lasts 30 minutes, where the board kept the old default
		if (($config['o_timeout_visit'] ?? null) === '600')
			$this->settings->update(new Setting('o_timeout_visit', '1800'));

		if (version_compare($version, '1.4', '<') && ($config['o_redirect_delay'] ?? null) === '1')
			$this->settings->update(new Setting('o_redirect_delay', '0'));

		$this->schema->dropField('groups', 'g_post_polls');

		// Room for more than one moderator group
		if (!$this->schema->fieldExists('groups', 'g_moderator'))
		{
			$this->schema->addField('groups', new Column('g_moderator', 'TINYINT(1)', false, 0), 'g_user_title');
			$this->data->reorderGroups();

			// The default group, where it is the members' old id
			$this->settings->replace(new Setting('o_default_user_group', '3'), '4');
		}

		foreach (self::MODERATOR_OPTIONS as $option => $permission)
		{
			if (!array_key_exists($option, $config))
				continue;

			$this->settings->remove($option);
			$this->schema->addField('groups', new Column($permission, 'TINYINT(1)', false, 0), self::MODERATOR_COLUMNS[$permission]);
			$this->data->grantModerators($permission, (int) $config[$option]);
		}

		// One row per visitor in the online table, which a unique index keeps
		if (!$this->schema->indexExists('online', 'user_id_ident_idx'))
		{
			$this->data->emptyOnline();
			$this->schema->addIndex('online', 'user_id_ident_idx', array('user_id', $mysql ? 'ident(25)' : 'ident'), true);
		}

		$this->schema->dropIndex('online', 'user_id_idx');
		$this->schema->addIndex('online', 'ident_idx', array($mysql ? 'ident(25)' : 'ident'));
		$this->schema->addIndex('online', 'logged_idx', array('logged'));
		$this->schema->addIndex('topics', 'last_post_idx', array('last_post'));

		// What is left of the defunct post approval system
		$this->schema->dropField('forums', 'approval');
		$this->schema->dropField('groups', 'g_posts_approved');
		$this->schema->dropField('posts', 'approved');

		$this->schema->addField('groups', new Column('g_view_users', 'TINYINT(1)', false, 1), 'g_read_board');
		$this->schema->addField('users', new Column('time_format', 'INT(10)', false, 0), 'dst');
		$this->schema->addField('users', new Column('date_format', 'INT(10)', false, 0), 'dst');
		$this->schema->addField('users', new Column('last_search', 'INT(10)', true), 'last_post');
		$this->schema->addField('users', new Column('last_email_sent', 'INT(10)', true), 'last_search');
		$this->schema->addField('groups', new Column('g_send_email', 'TINYINT(1)', false, 1), 'g_search_users');
		$this->schema->addField('groups', new Column('g_email_flood', 'INT(10)', false, 60), 'g_search_flood');

		$this->data->limitGroupMail();

		$this->schema->addField('users', new Column('auto_notify', 'TINYINT(1)', false, 0), 'notify_with_post');

		if (!$this->schema->fieldExists('topics', 'first_post_id'))
		{
			$this->schema->addField('topics', new Column('first_post_id', 'INT(10) UNSIGNED', false, 0), 'posted');
			$this->schema->addIndex('topics', 'first_post_id_idx', array('first_post_id'));
			$this->data->recordFirstPosts();
		}

		if (!$this->schema->indexExists('posts', 'posted_idx'))
			$this->schema->addIndex('posts', 'posted_idx', array('posted'));

		$this->data->moveUnverifiedUsers();

		$this->schema->addField('bans', new Column('ban_creator', 'INT(10) UNSIGNED', false, 0));

		// The hotfixes this update supersedes
		foreach ($this->data->supersededHotfixes($this->environment->version()) as $hotfix)
			$this->data->removeExtension($hotfix);

		// The LinkedIn address 1.4.0 stored without a scheme
		if (version_compare($version, '1.3', '>') && version_compare($version, '1.4.1', '<') && $this->schema->fieldExists('users', 'linkedin'))
			$this->data->schemeLinkedinAddresses();

		if (version_compare($version, '1.3', '>='))
			return new StageResult(next: '?stage=finish');

		return new StageResult(next: $conversion);
	}

	/**
	 * The options each release added, for a board that has not got them yet.
	 *
	 * @param array<string, ?string> $config
	 */
	private function addOptions(array $config): void {
		$fetches = $this->environment->fetchesRemoteFiles() ? '1' : '0';

		$added = array(
			'o_quote_depth'				=> '3',
			'o_database_revision'		=> '0',
			'o_default_email_setting'	=> '1',
			'o_additional_navlinks'		=> '',
			'o_sef'						=> 'Default',
			'o_topic_views'				=> '1',
			'o_signatures'				=> '1',
			'o_smtp_ssl'				=> '0',
			'o_check_for_updates'		=> $fetches,
			'o_check_for_versions'		=> $config['o_check_for_updates'] ?? $fetches,
			'o_announcement_heading'	=> '',
			'o_default_dst'				=> '0',
			'o_show_moderators'			=> '0',
			'o_mask_passwords'			=> '1',
		);

		$missing = array();
		foreach ($added as $name => $value)
			if (!array_key_exists($name, $config))
				$missing[] = new Setting($name, $value);

		$this->settings->add(...$missing);
	}

	/**
	 * The avatars 1.2 kept as files alone, recorded on their accounts; an avatar
	 * that is no image, or larger than the board allows, is deleted.
	 *
	 * @param array<string, ?string> $config
	 */
	private function convertAvatars(array $config): void {
		$types = array('gif' => self::AVATAR_GIF, 'jpg' => self::AVATAR_JPG, 'png' => self::AVATAR_PNG);

		foreach ($this->files->avatars() as $avatar)
		{
			if (preg_match('/^(\d+)\.(png|gif|jpg)/', $avatar, $matches) !== 1)
				continue;

			$userId = intval($matches[1], 10);
			if ($userId < 2)
				continue;

			$size = $this->files->avatarSize($avatar);
			if ($size === null || $size[0] > (int) ($config['o_avatars_width'] ?? 0) || $size[1] > (int) ($config['o_avatars_height'] ?? 0))
				$this->files->removeAvatar($avatar);
			else
				$this->data->storeAvatar($userId, $types[$matches[2]], $size[0], $size[1]);
		}
	}

	/**
	 * The configuration, categories, forums, groups, ranks and censored words, converted at once.
	 *
	 * @param array<string, ?string> $config
	 */
	private function convertMisc(array $config, string $charset, string $next): StageResult {
		$this->database->setNames('utf8');

		$lines = array(new Html('Converting configuration…'));
		foreach ($config as $name => $value)
		{
			$converted = Utf8Text::convert($value, $charset);
			if ($converted !== null)
				$this->settings->update(new Setting($name, $converted));
		}

		$lines[] = new Html('Converting categories…');
		$this->convertAll('categories', 'id', array('cat_name'), array(), $charset);

		$lines[] = new Html('Converting forums…');
		foreach ($this->conversion->rows('forums', 'id', array('forum_name', 'forum_desc', 'moderators')) as $forum)
		{
			$moderators = self::moderators($forum->value('moderators'));
			$converted = array();
			foreach ($moderators as $username => $userId)
				$converted[Utf8Text::convert((string) $username, $charset) ?? (string) $username] = $userId;

			$name = Utf8Text::convert($forum->value('forum_name'), $charset);
			$description = Utf8Text::convert($forum->value('forum_desc'), $charset) ?? $forum->value('forum_desc');

			if ($name !== null || $description !== $forum->value('forum_desc') || $converted !== $moderators)
				$this->conversion->store('forums', 'id', new TextRow($forum->id(), array(
					'forum_name'	=> $name ?? $forum->value('forum_name') ?? '',
					'forum_desc'	=> $description !== '' ? $description : null,
					'moderators'	=> $converted !== array() ? serialize($converted) : null,
				)));
		}

		$lines[] = new Html('Converting groups…');
		$this->convertAll('groups', 'g_id', array('g_title', 'g_user_title'), array('g_user_title'), $charset);

		$lines[] = new Html('Converting ranks…');
		$this->convertAll('ranks', 'id', array('rank'), array(), $charset);

		$lines[] = new Html('Converting censor words…');
		$this->convertAll('censoring', 'id', array('search_for', 'replace_with'), array(), $charset);

		return new StageResult($lines, $next);
	}

	/**
	 * A batch of $table's rows converted, $label naming each; the stage goes on
	 * with the next batch, or with stage $then once there is none.
	 *
	 * @param list<string> $columns
	 * @param list<string> $nullable the columns stored as NULL where they end up empty
	 */
	private function convertBatch(string $table, array $columns, array $nullable, string $label, string $charset, int $startAt, string $stage, string $then): StageResult {
		$this->database->setNames('utf8');

		if ($startAt === 0)
			$startAt = $this->conversion->firstId($table) ?? 0;

		$endAt = $startAt + self::PER_PAGE;

		$lines = array();
		foreach ($this->conversion->rows($table, 'id', $columns, $startAt, $endAt) as $row)
		{
			$lines[] = Html::format('Converting %s %d…', $label, $row->id());

			$converted = self::converted($row, $columns, $nullable, $charset);
			if ($converted !== null)
				$this->conversion->store($table, 'id', $converted);
		}

		$converting = '&req_old_charset='.$charset.'&req_per_page='.self::PER_PAGE;
		$nextId = $this->conversion->nextId($table, $endAt);

		if ($nextId !== null)
			return new StageResult($lines, '?stage='.$stage.$converting.'&start_at='.$nextId);

		return new StageResult($lines, $then === 'conv_tables' ? '?stage=conv_tables' : '?stage='.$then.$converting);
	}

	/**
	 * Every row of $table converted.
	 *
	 * @param list<string> $columns
	 * @param list<string> $nullable the columns stored as NULL where they end up empty
	 */
	private function convertAll(string $table, string $idColumn, array $columns, array $nullable, string $charset): void {
		foreach ($this->conversion->rows($table, $idColumn, $columns) as $row)
		{
			$converted = self::converted($row, $columns, $nullable, $charset);
			if ($converted !== null)
				$this->conversion->store($table, $idColumn, $converted);
		}
	}

	/** MySQL converts the character set of every text column of the tables itself. */
	private function convertTables(DatabaseSettings $database): StageResult {
		$lines = array();

		if ($database->isMysql())
		{
			foreach (self::MYSQL_TABLES as $table)
			{
				$lines[] = Html::format('Converting table %s…', $database->prefix.$table);

				$this->conversion->setDefaultCharset($table);

				foreach ($this->conversion->columns($table) as $column)
				{
					$base = explode('(', $column->type())[0];
					if (!isset(self::BINARY_TYPES[$base]) || str_contains($column->collation() ?? '', 'utf8'))
						continue;

					// Through the binary type, so the bytes are kept as they are and only read differently after
					$binary = (string) preg_replace('/'.$base.'/i', self::BINARY_TYPES[$base], $column->type());
					$this->schema->alterField($table, new Column($column->name(), $binary, $column->nullable(), $column->default()));
					$this->schema->alterField($table, new Column($column->name(), $column->type().' CHARACTER SET utf8', $column->nullable(), $column->default()));
				}
			}
		}

		return new StageResult($lines, '?stage=preparse_posts');
	}

	/** A batch of $table's $column preparsed, $label naming each row; then stage $then. */
	private function preparse(string $table, string $column, bool $signature, string $label, int $startAt, string $stage, string $then): StageResult {
		// Definitely UTF-8 from here on
		$this->database->setNames('utf8');

		if ($startAt === 0)
			$startAt = $this->conversion->firstId($table) ?? 0;

		$endAt = $startAt + self::PER_PAGE;

		$lines = array();
		foreach ($this->conversion->rows($table, 'id', array($column), $startAt, $endAt) as $row)
		{
			$lines[] = Html::format('%s %d…', $label, $row->id());
			$this->conversion->store($table, 'id', new TextRow($row->id(), array($column => $this->preparser->preparse($row->value($column) ?? '', $signature))));
		}

		$nextId = $this->conversion->nextId($table, $endAt);

		return new StageResult($lines, $nextId !== null ? '?stage='.$stage.'&req_per_page='.self::PER_PAGE.'&start_at='.$nextId : '?stage='.$then);
	}

	/**
	 * $row with each of $columns converted, each of $nullable NULL where it is
	 * empty; null when no column changed.
	 *
	 * @param list<string> $columns
	 * @param list<string> $nullable
	 */
	private static function converted(TextRowInterface $row, array $columns, array $nullable, string $charset): ?TextRow {
		$values = array();
		$changed = false;

		foreach ($columns as $column)
		{
			$converted = Utf8Text::convert($row->value($column), $charset);
			$changed = $changed || $converted !== null;
			$value = $converted ?? $row->value($column);

			$values[$column] = in_array($column, $nullable, true) && ($value ?? '') === '' ? null : ($value ?? '');
		}

		return $changed ? new TextRow($row->id(), $values) : null;
	}

	/** @return array<array-key, mixed> the moderators a forum stored, username => id */
	private static function moderators(?string $stored): array {
		if ($stored === null || $stored === '')
			return array();

		$moderators = unserialize($stored, array('allowed_classes' => false));

		return is_array($moderators) ? $moderators : array();
	}
}
