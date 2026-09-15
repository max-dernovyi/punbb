<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Controller;

use Closure;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Setup\Config\ConfigurationInterface;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Setup\Environment\EnvironmentInterface;
use PunBB\Module\Setup\Page\SetupPage;

/**
 * admin/db_update.php: brings the database config.php names up to this
 * release, a stage per request. It runs on a database the board cannot run on
 * yet, so nothing of the board is loaded for it.
 */
final class UpdateController implements ControllerInterface {
	/** @param Closure(): Update $update the update, over the database once it is open */
	public function __construct(
		private readonly EnvironmentInterface $environment,
		private readonly ConfigurationInterface $configuration,
		private readonly DatabaseInterface $database,
		private readonly SetupPage $pages,
		private readonly Closure $update
	) {}

	public function handle(Request $request): Response {
		$configuration = $this->configuration->load();
		if ($configuration === null)
			return $this->pages->text(new Html('Cannot find config.php, are you sure it exists?'));

		$requirements = $this->environment->requirementErrors();
		if ($requirements !== array())
			return $this->pages->text(Html::format("PunBB cannot be updated on this PHP installation:\n<ul><li>%s</li></ul>", Html::join('</li><li>', array_map(Html::escape(...), $requirements))));

		// A driver removed with its PHP extension is fixed in config.php by hand, before its dblayer would fail to load
		$type = $configuration->database->type;
		$replacement = $this->environment->removedDatabaseReplacement($type);
		if ($replacement !== null)
			return $this->pages->text(Html::format('Your config.php uses the \'%s\' database driver, which was removed along with the PHP extension it needs. Set $db_type to \'%s\' in config.php and run this script again.', $type, $replacement));

		$this->database->openUnencoded($configuration->database);
		$this->database->startTransaction();

		$response = ($this->update)()->run($request, $configuration);

		$this->database->endTransaction();
		$this->database->close();

		return $response;
	}
}
