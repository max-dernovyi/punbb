<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\Data\SettingInterface;

final class BoardSettingsInterceptor implements BoardSettingsInterface {
	public function __construct(private readonly BoardSettingsInterface $subject, private readonly PluginChain $plugins) {}

	public function version(): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->version(...));
	}

	public function all(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->all(...));
	}

	public function add(SettingInterface ...$settings): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function update(SettingInterface ...$settings): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function replace(SettingInterface $setting, string $expected): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->replace(...));
	}

	public function rename(string $from, string $to): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->rename(...));
	}

	public function remove(string ...$names): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}
}
