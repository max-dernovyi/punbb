<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Model;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Setup\Database\DatabaseSettings;

/**
 * The installation form as it was posted: the database, the administrator's
 * account and the board's address and language.
 */
final readonly class Submission {
	/** The database types the installer offers. */
	public const DATABASE_TYPES = array('mysqli', 'mysqli_innodb', 'pgsql', 'sqlite3');

	private const IPV4 = '/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/';

	private const IPV6 = '/((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))/';

	private const BBCODE = '/(?:\[\/?(?:b|u|i|h|colou?r|quote|code|img|url|email|list)\]|\[(?:code|quote|list)=)/i';

	private const PREFIX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

	public function __construct(
		public DatabaseSettings $database,
		public string $username,
		public string $email,
		public string $password,
		public string $language,
		public string $baseUrl,
		public bool $installsRepository
	) {}

	/** @param array<mixed> $post */
	public static function fromPost(array $post): self {
		return new self(
			new DatabaseSettings(
				self::field($post, 'req_db_type', false),
				self::field($post, 'req_db_host'),
				self::field($post, 'req_db_name'),
				self::field($post, 'db_username'),
				self::field($post, 'db_password'),
				self::field($post, 'db_prefix')
			),
			self::field($post, 'req_username'),
			strtolower(self::field($post, 'req_email')),
			self::field($post, 'req_password1'),
			self::packName(self::field($post, 'req_language')),
			// Without a trailing slash
			(string) preg_replace('#/$#', '', self::field($post, 'req_base_url')),
			!empty($post['install_pun_repository'])
		);
	}

	/** $name without the characters that would take a pack's path out of lang/. */
	public static function packName(string $name): string {
		return (string) preg_replace('#[\.\\\/]#', '', $name);
	}

	/**
	 * What the account is refused for, before any database is asked: the key
	 * of the installer's message, or null when nothing is wrong with it.
	 */
	public function accountProblem(): ?string {
		$length = mb_strlen($this->username);

		return match (true) {
			mb_strlen($this->database->name) === 0										=> 'Missing database name',
			$length < 2																=> 'Username too short',
			$length > 25																=> 'Username too long',
			mb_strlen($this->password) < 4												=> 'Pass too short',
			strtolower($this->username) === 'guest'									=> 'Username guest',
			preg_match(self::IPV4, $this->username) === 1 || preg_match(self::IPV6, $this->username) === 1	=> 'Username IP',
			(str_contains($this->username, '[') || str_contains($this->username, ']')) && str_contains($this->username, '\'') && str_contains($this->username, '"')	=> 'Username reserved chars',
			preg_match(self::BBCODE, $this->username) === 1							=> 'Username BBCode',
			default																	=> null,
		};
	}

	/** Whether the table prefix may name the board's tables. */
	public function hasValidPrefix(): bool {
		$prefix = $this->database->prefix;

		return $prefix === '' || (preg_match(self::PREFIX, $prefix) === 1 && strlen($prefix) <= 40);
	}

	/** Whether the prefix is the one SQLite keeps for its own tables. */
	public function collidesWithSqlite(): bool {
		return $this->database->type === 'sqlite3' && strtolower($this->database->prefix) === 'sqlite_';
	}

	/** $message with the prefix in it, as the installer names it. */
	public function prefixMessage(Html $message): Html {
		return Html::format($message, $this->database->prefix);
	}

	/**
	 * A text field of the form, trimmed as the forum trims input; a field
	 * posted as an array is empty.
	 *
	 * @param array<mixed> $post
	 */
	private static function field(array $post, string $name, bool $trim = true): string {
		$value = $post[$name] ?? '';
		if (!is_string($value))
			return '';

		return $trim ? (new Html($value))->trim()->html : $value;
	}
}
