<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Controller;

use Closure;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Install\Language\InstallerLanguageInterface;
use PunBB\Module\Install\Manifest\BundledExtensionsInterface;
use PunBB\Module\Install\Model\Submission;
use PunBB\Module\Install\View\InstallView;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Setup\Config\BoardConfiguration;
use PunBB\Module\Setup\Config\ConfigFile;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Database\DatabaseSettings;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Files\BoardFilesInterface;
use PunBB\Module\Setup\Page\SetupPage;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;

/**
 * admin/install.php: the installation form, the installation it posts, and
 * config.php to download where it could not be written. It runs where no
 * config.php exists, so the database is the one the form names.
 */
final class InstallController implements ControllerInterface {
	private const FORM = __DIR__.'/../templates/form.phtml';

	private const INSTALLED = __DIR__.'/../templates/installed.phtml';

	private const DEFAULT_LANGUAGE = 'English';

	private const MIN_MYSQL_VERSION = '4.1.2';

	/** The fields of config.php to download, as the last page posts them back. */
	private const CONFIG_FIELDS = array('db_type', 'db_host', 'db_name', 'db_username', 'db_password', 'db_prefix', 'base_url', 'cookie_name');

	/** @param Closure(): Installation $installation the installation, over the database once it is open */
	public function __construct(
		private readonly EnvironmentInterface $environment,
		private readonly BoardFilesInterface $files,
		private readonly DatabaseInterface $database,
		private readonly InstallerLanguageInterface $language,
		private readonly BundledExtensionsInterface $extensions,
		private readonly EmailAddressesInterface $emails,
		private readonly RandomKeysInterface $keys,
		private readonly SetupPage $pages,
		private readonly TemplateRenderer $templates,
		private readonly Closure $installation
	) {}

	public function handle(Request $request): Response {
		if ($this->files->hasConfig())
			return $this->pages->text(new Html('The file \'config.php\' already exists which would mean that PunBB is already installed. You should go <a href="../index.php">here</a> instead.'));

		$requirements = $this->environment->requirementErrors();
		if ($requirements !== array())
			return $this->pages->text(Html::format("PunBB cannot be installed on this PHP installation:\n<ul><li>%s</li></ul>", Html::join('</li><li>', array_map(Html::escape(...), $requirements))));

		$language = Submission::packName(self::chosenLanguage($request));
		if (!$this->language->speaksInstaller($language))
			return $this->pages->text(new Html('The language pack you have chosen doesn\'t seem to exist or is corrupt. Please recheck and try again.'));

		$strings = $this->language->strings($language, 'install');

		if (isset($request->post['generate_config']))
			return $this->download($request->post);

		if (!isset($request->post['form_sent']))
			return self::page($this->templates->render(self::FORM, InstallView::form($strings, $this->environment->version(), $this->language->packs(), $language, $this->environment->databaseTypes(), self::guessBaseUrl($request), $this->extensions->hasRepository())));

		return $this->install(Submission::fromPost($request->post), $strings, $this->language->strings($language, 'admin_settings'));
	}

	/**
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $settingStrings
	 */
	private function install(Submission $submission, array $strings, array $settingStrings): Response {
		$problem = $submission->accountProblem();
		if ($problem !== null)
			return $this->error($strings, $problem);

		if (!$this->emails->isValid($submission->email))
			return $this->error($strings, 'Invalid email');

		if ($submission->baseUrl === '')
			return $this->error($strings, 'Missing base url');

		if (!$this->files->hasLanguage($submission->language))
			return $this->error($strings, 'Invalid language');

		if (!in_array($submission->database->type, Submission::DATABASE_TYPES, true))
			return self::page($this->pages->error(Html::format(self::string($strings, 'No such database type'), $submission->database->type)));

		$this->database->open($submission->database);

		if ($submission->database->isMysql())
		{
			$version = $this->database->serverVersion();
			if (version_compare($version, self::MIN_MYSQL_VERSION, '<'))
				return self::page($this->pages->error(Html::format(self::string($strings, 'Invalid MySQL version'), $version, self::MIN_MYSQL_VERSION)));

			if ($submission->database->type === 'mysqli_innodb' && !$this->database->supportsInnodb())
				return $this->error($strings, 'InnoDB Not Supported');
		}

		if (!$submission->hasValidPrefix())
			return self::page($this->pages->error($submission->prefixMessage(self::string($strings, 'Invalid table prefix'))));

		if ($submission->collidesWithSqlite())
			return $this->error($strings, 'SQLite prefix collision');

		$installation = ($this->installation)();
		if ($installation->isInstalled())
			return self::page($this->pages->error(Html::format(self::string($strings, 'PunBB already installed'), $submission->database->prefix, $submission->database->name)));

		$installation->install($submission, $strings, $settingStrings);

		$alerts = array();
		if ($this->files->cacheWritable())
			$this->files->clearCache();
		else
			$alerts[] = self::string($strings, 'No cache write');

		if (!$this->files->avatarsWritable())
			$alerts[] = self::string($strings, 'No avatar write');

		if (!$this->environment->acceptsUploads())
			$alerts[] = self::string($strings, 'File upload alert');

		// Random bytes at the end of the cookie name keep two boards on one host apart
		$cookieName = 'forum_cookie_'.$this->keys->key(6, false, true);

		$configuration = new BoardConfiguration($submission->database, $submission->baseUrl, $cookieName, cookieSecure: ConfigFile::isSecureAddress($submission->baseUrl));
		$written = $this->files->writeConfig(ConfigFile::installed($configuration));

		if ($submission->installsRepository && $this->extensions->hasRepository())
			$installation->installRepository(time());

		$this->database->close();

		return self::page($this->templates->render(self::INSTALLED, InstallView::installed($strings, $this->environment->version(), $alerts, $written, $configuration)));
	}

	/** @param array<mixed> $post */
	private function download(array $post): Response {
		$fields = array();
		foreach (self::CONFIG_FIELDS as $field)
		{
			$value = $post[$field] ?? '';

			// A field posted as name[]=x is an array; every one of them is written into config.php as text
			if (!is_string($value))
				return $this->pages->text(new Html('Bad request. Every configuration field must be a single value.'));

			$fields[$field] = $value;
		}

		$database = new DatabaseSettings($fields['db_type'], $fields['db_host'], $fields['db_name'], $fields['db_username'], $fields['db_password'], $fields['db_prefix']);
		$configuration = new BoardConfiguration($database, $fields['base_url'], $fields['cookie_name'], cookieSecure: ConfigFile::isSecureAddress($fields['base_url']));

		return new Response(ConfigFile::installed($configuration), 200, array(
			'Content-Type'			=> 'text/x-delimtext; name="config.php"',
			'Content-disposition'	=> 'attachment; filename=config.php',
		));
	}

	/** @param array<string, Html> $strings */
	private function error(array $strings, string $key): Response {
		return self::page($this->pages->error(self::string($strings, $key)));
	}

	/** The installer's language: the one chosen for it, else the board's default posted with the form. */
	private static function chosenLanguage(Request $request): string {
		if (isset($request->query['lang']) && is_string($request->query['lang']))
			return $request->query['lang'];

		if (isset($request->post['req_language']) && is_string($request->post['req_language']))
			return (new Html($request->post['req_language']))->trim()->html;

		return self::DEFAULT_LANGUAGE;
	}

	/** An educated guess at the board's address: where the installer was requested. */
	private static function guessBaseUrl(Request $request): string {
		return ($request->secure ? 'https://' : 'http://').preg_replace('/:80$/', '', $request->host).rtrim($request->base, '/');
	}

	/** $page, or the body of a page, as the installer sends every page: in UTF-8 and never cached. */
	private static function page(Response|string $page): Response {
		$headers = array('Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store');

		return is_string($page) ? new Response($page, 200, $headers) : new Response($page->body, $page->status, $headers);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
