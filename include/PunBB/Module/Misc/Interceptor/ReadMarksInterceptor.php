<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Misc\Api\Data\LastVisitInterface;
use PunBB\Module\Misc\Api\ReadMarksInterface;

final class ReadMarksInterceptor implements ReadMarksInterface {
	public function __construct(private readonly ReadMarksInterface $subject, private readonly PluginChain $plugins) {}

	public function markBoardRead(LastVisitInterface ...$visits): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->markBoardRead(...));
	}

	public function forumName(int $forumId, int $groupId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumName(...));
	}
}
