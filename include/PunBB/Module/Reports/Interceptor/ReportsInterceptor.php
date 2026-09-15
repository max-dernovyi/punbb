<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Reports\Api\ReportsInterface;

final class ReportsInterceptor implements ReportsInterface {
	public function __construct(private readonly ReportsInterface $subject, private readonly PluginChain $plugins) {}

	public function unread(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->unread(...));
	}

	public function recentlyRead(int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->recentlyRead(...));
	}

	public function markRead(array $reportIds, int $userId, int $now): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->markRead(...));
	}
}
