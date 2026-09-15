<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Settings\Api\ConfigurationInterface;
use PunBB\Module\Settings\Api\Data\SettingInterface;

final class ConfigurationInterceptor implements ConfigurationInterface {
	public function __construct(private readonly ConfigurationInterface $subject, private readonly PluginChain $plugins) {}

	public function updatePermissions(SettingInterface ...$settings): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->updatePermissions(...));
	}

	public function updateOptions(SettingInterface ...$settings): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->updateOptions(...));
	}
}
