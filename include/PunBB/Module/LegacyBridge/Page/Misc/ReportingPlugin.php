<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\NewReportInterface;
use PunBB\Module\Misc\Api\Data\ReportedTopicInterface;
use PunBB\Module\Misc\Api\ReportingInterface;
use PunBB\Module\Misc\Model\ReportedTopic;

/**
 * The query points of reporting a post, with the query arrays misc.php built.
 * A query a point changed answers instead; a statement a point changed runs
 * instead, and the repository is handed nothing to store. The post's topic is
 * left in $topic_info, with any column a point added.
 */
final class ReportingPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterTopicOf(ReportingInterface $subject, ?ReportedTopicInterface $result, int $postId): ?ReportedTopicInterface {
		$query = array(
			'SELECT'	=> 't.id, t.subject, t.forum_id',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				)
			),
			'WHERE'		=> 'p.id='.$postId
		);

		$row = null;
		if ($this->queries->changed('mi_report_qr_get_topic_data', ReportingInterface::class.'::topicOf', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? new ReportedTopic((int) Markers::markup($row['id'] ?? 0), Markers::markup($row['subject'] ?? ''), (int) Markers::markup($row['forum_id'] ?? 0)) : null;
		}

		$GLOBALS['topic_info'] = $row ?? ($result !== null ? array('id' => $result->id(), 'subject' => $result->subject(), 'forum_id' => $result->forumId()) : false);

		return $result;
	}

	/** @return list<NewReportInterface>|null */
	public function beforeAdd(ReportingInterface $subject, NewReportInterface ...$reports): ?array {
		$kept = array();
		foreach ($reports as $report)
		{
			$query = array(
				'INSERT'	=> 'post_id, topic_id, forum_id, reported_by, created, message',
				'INTO'		=> 'reports',
				'VALUES'	=> $report->postId().', '.$report->topicId().', '.$report->forumId().', '.$report->reporterId().', '.$report->createdAt().', \''.Markers::markup(LegacyConnection::legacy()->escape($report->message())).'\''
			);

			if ($this->queries->changed('mi_report_add_report', ReportingInterface::class.'::add', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $report;
		}

		return count($kept) !== count($reports) ? $kept : null;
	}

	/** @return list<MailSentInterface>|null */
	public function beforeRecordMailSent(ReportingInterface $subject, MailSentInterface ...$sent): ?array {
		$kept = array();
		foreach ($sent as $mail)
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'last_email_sent='.$mail->at(),
				'WHERE'		=> 'id='.$mail->userId()
			);

			if ($this->queries->changed('mi_report_qr_update_last_email_sent', ReportingInterface::class.'::recordMailSent', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $mail;
		}

		return count($kept) !== count($sent) ? $kept : null;
	}
}
