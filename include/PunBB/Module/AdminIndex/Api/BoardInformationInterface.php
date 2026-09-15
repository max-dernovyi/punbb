<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Api;

use PunBB\Module\AdminIndex\Api\Data\DatabaseInterface;

/**
 * What the administration's index reports about the board and the database it runs on.
 */
interface BoardInformationInterface {
	/** The visitors online now. */
	public function onlineCount(): int;

	/** @return list<string> the ids of the installed hotfix extensions */
	public function hotfixes(): array;

	public function database(): DatabaseInterface;
}
