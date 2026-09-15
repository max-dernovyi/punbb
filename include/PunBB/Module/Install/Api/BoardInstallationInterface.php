<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Api;

use PunBB\Module\Install\Api\Data\AdministratorInterface;
use PunBB\Module\Install\Api\Data\ExtensionInterface;
use PunBB\Module\Install\Api\Data\RankInterface;
use PunBB\Module\Install\Api\Data\SettingInterface;
use PunBB\Module\Install\Api\Data\WelcomeInterface;

/**
 * What a board starts with, stored into the tables an installation just created.
 */
interface BoardInstallationInterface {
	/** Whether a board is installed in the database already: its guest account is. */
	public function isInstalled(): bool;

	/** Stores the preset groups: administrators, guests, members and moderators, ids 1 to 4. */
	public function addGroups(): void;

	/** Stores the guest account, id 1. */
	public function addGuest(): void;

	/** @return int the id the administrator's account was given */
	public function addAdministrator(AdministratorInterface $administrator): int;

	public function addSettings(SettingInterface ...$settings): void;

	/** @return int the id of the welcome post, in a topic, a forum and a category of its own */
	public function addWelcome(WelcomeInterface $welcome): int;

	public function addRanks(RankInterface ...$ranks): void;

	/** Stores $extension as installed and enabled, with the code it attaches at its hook points. */
	public function addExtension(ExtensionInterface $extension): void;
}
