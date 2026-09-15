<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api;

use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\NewReportInterface;
use PunBB\Module\Misc\Api\Data\ReportedTopicInterface;

/**
 * Reporting a post: its topic, the report, and when the reporter last reported.
 */
interface ReportingInterface {
	/** The topic post $postId is in; null when there is no such post. */
	public function topicOf(int $postId): ?ReportedTopicInterface;

	public function add(NewReportInterface ...$reports): void;

	public function recordMailSent(MailSentInterface ...$sent): void;
}
