<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Interceptor;

use PunBB\Module\AdminIndex\Api\BoardInformationInterface;
use PunBB\Module\AdminIndex\Api\Data\DatabaseInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class BoardInformationInterceptor implements BoardInformationInterface {
	public function __construct(private readonly BoardInformationInterface $subject, private readonly PluginChain $plugins) {}

	public function onlineCount(): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->onlineCount(...));
	}

	public function hotfixes(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->hotfixes(...));
	}

	public function database(): DatabaseInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->database(...));
	}
}
