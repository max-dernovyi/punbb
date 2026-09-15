<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\NewReportInterface;
use PunBB\Module\Misc\Api\Data\ReportedTopicInterface;
use PunBB\Module\Misc\Api\ReportingInterface;

final class ReportingInterceptor implements ReportingInterface {
	public function __construct(private readonly ReportingInterface $subject, private readonly PluginChain $plugins) {}

	public function topicOf(int $postId): ?ReportedTopicInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topicOf(...));
	}

	public function add(NewReportInterface ...$reports): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function recordMailSent(MailSentInterface ...$sent): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->recordMailSent(...));
	}
}
