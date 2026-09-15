<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Register\Api\RegistrationsInterface;

final class RegistrationsInterceptor implements RegistrationsInterface {
	public function __construct(private readonly RegistrationsInterface $subject, private readonly PluginChain $plugins) {}

	public function registrationsFrom(string $address, int $since): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->registrationsFrom(...));
	}

	public function removeUnverified(int ...$registeredBefore): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeUnverified(...));
	}

	public function usernamesWithEmail(string $email): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->usernamesWithEmail(...));
	}
}
