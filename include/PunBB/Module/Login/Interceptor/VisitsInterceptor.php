<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Login\Api\VisitsInterface;

final class VisitsInterceptor implements VisitsInterface {
	public function __construct(private readonly VisitsInterface $subject, private readonly PluginChain $plugins) {}

	public function endGuestVisit(string ...$addresses): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->endGuestVisit(...));
	}

	public function endVisit(int ...$userIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->endVisit(...));
	}
}
