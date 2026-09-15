<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Install\Api\BoardInstallationInterface;
use PunBB\Module\Install\Api\Data\AdministratorInterface;
use PunBB\Module\Install\Api\Data\ExtensionInterface;
use PunBB\Module\Install\Api\Data\RankInterface;
use PunBB\Module\Install\Api\Data\SettingInterface;
use PunBB\Module\Install\Api\Data\WelcomeInterface;

final class BoardInstallationInterceptor implements BoardInstallationInterface {
	public function __construct(private readonly BoardInstallationInterface $subject, private readonly PluginChain $plugins) {}

	public function isInstalled(): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->isInstalled(...));
	}

	public function addGroups(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addGroups(...));
	}

	public function addGuest(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addGuest(...));
	}

	public function addAdministrator(AdministratorInterface $administrator): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addAdministrator(...));
	}

	public function addSettings(SettingInterface ...$settings): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addSettings(...));
	}

	public function addWelcome(WelcomeInterface $welcome): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addWelcome(...));
	}

	public function addRanks(RankInterface ...$ranks): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addRanks(...));
	}

	public function addExtension(ExtensionInterface $extension): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addExtension(...));
	}
}
