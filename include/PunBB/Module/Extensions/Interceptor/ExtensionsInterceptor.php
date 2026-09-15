<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Interceptor;

use PunBB\Module\Extensions\Api\Data\ExtensionRecordInterface;
use PunBB\Module\Extensions\Api\Data\ExtensionStatusInterface;
use PunBB\Module\Extensions\Api\Data\HookRecordInterface;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Api\ExtensionsInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class ExtensionsInterceptor implements ExtensionsInterface {
	public function __construct(private readonly ExtensionsInterface $subject, private readonly PluginChain $plugins) {}

	public function all(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->all(...));
	}

	public function enabledVersions(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->enabledVersions(...));
	}

	public function installedVersion(string $id): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->installedVersion(...));
	}

	public function update(ExtensionRecordInterface ...$extensions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function clearHooks(string ...$extensionIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->clearHooks(...));
	}

	public function add(ExtensionRecordInterface ...$extensions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function addHooks(HookRecordInterface ...$hooks): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addHooks(...));
	}

	public function find(string $id): ?InstalledExtensionInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function dependent(string $id): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->dependent(...));
	}

	public function removeHooks(string ...$extensionIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeHooks(...));
	}

	public function remove(string ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}

	public function isDisabled(string $id): ?bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->isDisabled(...));
	}

	public function enabledDependent(string $id): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->enabledDependent(...));
	}

	public function dependencies(string $id): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->dependencies(...));
	}

	public function enabledIds(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->enabledIds(...));
	}

	public function setDisabled(ExtensionStatusInterface ...$statuses): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->setDisabled(...));
	}
}
