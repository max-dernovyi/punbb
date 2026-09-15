<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Interceptor;

use PunBB\Module\Censoring\Api\CensorsInterface;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class CensorsInterceptor implements CensorsInterface {
	public function __construct(private readonly CensorsInterface $subject, private readonly PluginChain $plugins) {}

	public function all(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->all(...));
	}

	public function add(CensorInterface ...$censors): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function update(CensorInterface ...$censors): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function remove(int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}
}
