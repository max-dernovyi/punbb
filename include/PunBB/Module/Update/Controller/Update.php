<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Controller;

use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Patch\PatchException;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Config\BoardConfiguration;
use PunBB\Module\Setup\Config\ConfigFile;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Charset\ConversionException;
use PunBB\Module\Update\Charset\Utf8Text;
use PunBB\Module\Update\Model\Setting;
use PunBB\Module\Update\Patch\BoardOptions;
use PunBB\Module\Update\Patch\ConvertRows;
use PunBB\Module\Update\View\UpdateView;

/**
 * An update over the database config.php names, once it is open: the checks
 * that it is a board this release updates, then the stage the request names.
 * The start brings the schema to what the modules declare, each request after
 * it applies a batch of the first data patch the board has not recorded, and
 * the finish records the release.
 *
 * There is no rollback: a patch that fails stops the update unrecorded, its
 * batch discarded where the database keeps transactions, and the next run goes
 * on from it. MySQL commits a schema change as it makes it, and MyISAM keeps
 * every row written before the failure.
 */
final class Update {
	private const FORM = __DIR__.'/../templates/form.phtml';

	private const FINISHED = __DIR__.'/../templates/finished.phtml';

	private const STAGE = __DIR__.'/../templates/stage.phtml';

	private const MIN_MYSQL_VERSION = '4.1.2';

	/** The posts sampled for whether a board's text is UTF-8 already. */
	private const SAMPLES = 100;

	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly BoardDataInterface $data,
		private readonly SchemaInterface $schema,
		private readonly DatabaseInterface $database,
		private readonly EnvironmentInterface $environment,
		private readonly BoardFilesInterface $files,
		private readonly SetupPage $pages,
		private readonly TemplateRenderer $templates,
		private readonly SchemaSynchronizer $synchronizer,
		private readonly PatchApplier $patches
	) {}

	public function run(Request $request, BoardConfiguration $configuration): Response {
		$database = $configuration->database;
		$mismatch = Html::format('Version mismatch. The database \'%s\' doesn\'t seem to be running a PunBB database schema supported by this update script.', $database->name);

		// A missing or misprefixed config table is reported by name, before any statement reads it
		if (!$this->schema->tableExists('config'))
			return $this->pages->error($mismatch);

		$version = $this->settings->version();
		if ($version === null || version_compare($version, '1.2', '<'))
			return $this->pages->error($mismatch);

		// Text updated before is UTF-8, text a 1.2 board stored is read as it was written
		$this->database->setNames(version_compare($version, '1.3', '>=') ? 'utf8' : 'latin1');

		if ($database->isMysql())
		{
			$server = $this->database->serverVersion();
			if (version_compare($server, self::MIN_MYSQL_VERSION, '<'))
				return $this->pages->error(Html::format('You are running MySQL version %s. PunBB %s requires at least MySQL %s to run properly. You must upgrade your MySQL installation before you can continue.', $server, $this->environment->version(), self::MIN_MYSQL_VERSION));
		}

		$config = array();
		foreach ($this->settings->all() as $setting)
			$config[$setting->name()] = $setting->value();

		if (isset($config['o_database_revision']) && (int) $config['o_database_revision'] >= $this->environment->databaseRevision() && version_compare($config['o_cur_version'] ?? '', $this->environment->version(), '>='))
			return $this->pages->error(new Html('Your database is already as up-to-date as this script can make it.'), $config['o_board_title'] ?? 'PunBB');

		$baseUrl = $configuration->baseUrl ?? $config['o_base_url'] ?? '';

		// A default style or language the board no longer has is a 1.2 upgrade most likely
		if (!$this->files->hasStyle($config['o_default_style'] ?? ''))
			$this->settings->update(new Setting('o_default_style', 'Oxygen'));

		if (!$this->files->hasLanguage($config['o_default_lang'] ?? ''))
			$this->settings->update(new Setting('o_default_lang', 'English'));

		$charset = is_string($request->query['req_old_charset'] ?? null) ? str_replace('ISO8859', 'ISO-8859', strtoupper($request->query['req_old_charset'])) : 'ISO-8859-1';

		// The name reaches the conversion and the next stage's address
		if (!Utf8Text::knows($charset))
			return $this->pages->text(new Html('Unknown character set. Set req_old_charset to an encoding this PHP installation supports.'));

		$stage = is_string($request->query['stage'] ?? null) ? $request->query['stage'] : '';
		$startAt = is_scalar($request->query['start_at'] ?? null) ? max(0, min(PHP_INT_MAX - ConvertRows::PER_PAGE, intval($request->query['start_at']))) : 0;
		$patch = is_string($request->query['patch'] ?? null) ? $request->query['patch'] : '';

		try {
			return match ($stage) {
				''			=> $this->form($request, $version, $baseUrl),
				'start'		=> $this->stage($this->start($database, $version, $charset, isset($request->query['convert_charset']))),
				'patch'		=> $this->stage($this->patch($patch, $startAt)),
				'finish'	=> $this->finish($configuration, $config, $baseUrl),
				default		=> $this->stage(new StageResult()),
			};
		}
		catch (PatchException $e) {
			$this->database->rollBack();

			// Text the named character set cannot convert is for the one running the update to fix; anything else is a fault
			$cause = $e->getPrevious();
			if (!$cause instanceof ConversionException)
				throw $e;

			return $this->pages->text(Html::format('%s: %s The patches before it are applied; the update goes on from this one when it is run again.', $e->getMessage(), $cause->getMessage()));
		}
	}

	/** The schema every module declares, and for a 1.2 board the character set its text is converted from. */
	private function start(DatabaseSettings $database, string $version, string $charset, bool $convert): StageResult {
		// Kept in the options: the patches read it over as many requests as they take
		if (str_starts_with($version, '1.2'))
		{
			$this->settings->remove(BoardOptions::LEGACY_CHARSET);

			if ($convert)
				$this->settings->add(new Setting(BoardOptions::LEGACY_CHARSET, $charset));
		}

		// The online list holds visits alone, and the unique key the schema declares over it must not meet one twice
		$this->data->emptyOnline();

		$lines = array();
		foreach ($this->synchronizer->synchronize(Platform::ofDbType($database->type)) as $change)
			$lines[] = Html::escape(ucfirst($change->describe()).'…');

		// Every update supersedes the hotfixes of the releases before it, so this is no patch recorded once
		foreach ($this->data->supersededHotfixes($this->environment->version()) as $hotfix)
			$this->data->removeExtension($hotfix);

		return new StageResult($lines, $this->patches->pending() !== array() ? '?stage=patch' : '?stage=finish');
	}

	/** A batch of the first patch the board has not recorded, from $startAt on where it is patch $name's next. */
	private function patch(string $name, int $startAt): StageResult {
		$pending = $this->patches->pending();
		if ($pending === array())
			return new StageResult(next: '?stage=finish');

		$patch = $pending[0];
		$step = $this->patches->apply($patch, $patch->name === $name ? $startAt : 0);

		$lines = array(Html::format('Applying %s…', $patch->name));
		foreach ($step->lines as $line)
			$lines[] = Html::escape($line);

		if ($step->next !== null)
			return new StageResult($lines, '?stage=patch&patch='.rawurlencode($patch->name).'&start_at='.$step->next);

		return new StageResult($lines, count($pending) > 1 ? '?stage=patch' : '?stage=finish');
	}

	private function form(Request $request, string $version, string $baseUrl): Response {
		return self::page($this->templates->render(self::FORM, UpdateView::form($baseUrl, str_starts_with($version, '1.2'), $this->seemsUtf8(), isset($request->query['force']))));
	}

	/** @param array<string, ?string> $config */
	private function finish(BoardConfiguration $configuration, array $config, string $baseUrl): Response {
		// Recording the release before every patch has run would refuse the rerun that applies the rest
		if ($this->patches->pending() !== array())
			return $this->stage(new StageResult(next: '?stage=patch'));

		$this->database->setNames('utf8');

		// This feels like a good time to synchronize the forums
		foreach ($this->data->forumIds() as $forumId)
			$this->data->syncForum($forumId);

		// The search cache and the online list hold text that was not converted
		$this->data->emptySearchCache();
		$this->data->emptyOnline();

		$this->settings->remove(...BoardOptions::progress($this->settings));

		$this->files->clearCache();

		// A board that kept its address in the database keeps it in config.php from now on; until the copy
		// offered in its place is saved, the stored address is what the forum and a rerun fall back to
		$unwritten = null;
		if (array_key_exists('o_base_url', $config))
		{
			$source = ConfigFile::updated(new BoardConfiguration($configuration->database, $baseUrl, $configuration->cookieName, $configuration->cookieDomain, $configuration->cookiePath, $configuration->cookieSecure));
			if ($this->files->replaceConfig($source))
				$this->settings->remove('o_base_url');
			else
				$unwritten = $source;
		}

		// Recorded last, so an interrupted finish is run again rather than refused
		$this->settings->update(new Setting('o_cur_version', $this->environment->version()), new Setting('o_database_revision', (string) $this->environment->databaseRevision()));

		return self::page($this->templates->render(self::FINISHED, UpdateView::finished($baseUrl, $unwritten)));
	}

	private function stage(StageResult $result): Response {
		if ($result->next === '')
			return self::page(Html::join('<br />'."\n", $result->lines)->html);

		return self::page($this->templates->render(self::STAGE, UpdateView::stage($result)));
	}

	/** Whether the text of a random soup of posts, with their topics and forums, is UTF-8 already. */
	private function seemsUtf8(): bool {
		$range = $this->data->postRange();
		if ($range->count() === 0)
			return false;

		for ($i = 0; $i < self::SAMPLES; ++$i)
		{
			$id = match ($i) {
				0		=> $range->lowest(),
				1		=> $range->highest(),
				default	=> random_int($range->lowest(), $range->highest()),
			};

			if (!Utf8Text::seemsUtf8($this->data->postText($id) ?? ''))
				return false;
		}

		return true;
	}

	private static function page(string $body): Response {
		return new Response($body, 200, array('Content-Type' => 'text/html; charset=utf-8'));
	}
}
