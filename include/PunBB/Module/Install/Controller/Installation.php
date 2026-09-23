<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Controller;

use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Install\Api\BoardInstallationInterface;
use PunBB\Module\Install\Indexing\PostIndexInterface;
use PunBB\Module\Install\Manifest\BundledExtensionsInterface;
use PunBB\Module\Install\Model\Administrator;
use PunBB\Module\Install\Model\DefaultSettings;
use PunBB\Module\Install\Model\Rank;
use PunBB\Module\Install\Model\Submission;
use PunBB\Module\Install\Model\Welcome;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;

/**
 * An installation, over the database the form named once it is open: the
 * tables every module declares, and the rows a board starts with, in one
 * transaction. The rows are written in the shape the data patches lead to, so
 * every patch is recorded as applied.
 */
final class Installation {
	public function __construct(
		private readonly BoardInstallationInterface $board,
		private readonly SchemaInterface $schema,
		private readonly SchemaSynchronizer $synchronizer,
		private readonly PatchApplier $patches,
		private readonly DatabaseInterface $database,
		private readonly EnvironmentInterface $environment,
		private readonly PasswordsInterface $passwords,
		private readonly RandomKeysInterface $keys,
		private readonly PostIndexInterface $index,
		private readonly BundledExtensionsInterface $extensions
	) {}

	/** Whether a board is installed in the database already. */
	public function isInstalled(): bool {
		return $this->schema->tableExists('users') && $this->board->isInstalled();
	}

	/**
	 * @param array<string, Html> $strings the installer's strings
	 * @param array<string, Html> $settingStrings the settings page's strings
	 */
	public function install(Submission $submission, array $strings, array $settingStrings): void {
		$this->database->startTransaction();

		$this->synchronizer->synchronize(Platform::ofDbType($submission->database->type));
		$this->patches->recordAll();

		$now = time();

		$this->board->addGroups();
		$this->board->addGuest();

		$administratorId = $this->board->addAdministrator(new Administrator(
			$submission->username,
			$this->passwords->hash($submission->password),
			$this->keys->key(12),
			$submission->email,
			$submission->language,
			$now
		));

		$this->board->addSettings(...DefaultSettings::settings(
			$this->environment->version(),
			$this->environment->databaseRevision(),
			$submission->language,
			$submission->email,
			$this->environment->acceptsUploads(),
			$this->environment->fetchesRemoteFiles(),
			$strings,
			$settingStrings
		));

		$subject = self::text($strings, 'Default topic subject');
		$message = self::text($strings, 'Default post contents');

		$postId = $this->board->addWelcome(new Welcome(
			self::text($strings, 'Default category name'),
			self::text($strings, 'Default forum name'),
			self::text($strings, 'Default forum descrip'),
			$subject,
			$message,
			$submission->username,
			$administratorId,
			$now
		));

		$this->index->index($postId, $message, $subject);

		$this->board->addRanks(new Rank(self::text($strings, 'Default rank 1'), 0), new Rank(self::text($strings, 'Default rank 2'), 10));

		$this->database->endTransaction();
	}

	/** Installs the repository extension the forum ships, at $now. */
	public function installRepository(int $now): void {
		$repository = $this->extensions->repository($now);
		if ($repository !== null)
			$this->board->addExtension($repository);
	}

	/** @param array<string, Html> $strings */
	private static function text(array $strings, string $key): string {
		return ($strings[$key] ?? new Html(''))->html;
	}
}
