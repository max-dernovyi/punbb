<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\NewReportInterface;
use PunBB\Module\Misc\Api\Data\ReportedTopicInterface;
use PunBB\Module\Misc\Api\ReportingInterface;

/**
 * The posts and topics tables, the reports table, and the reporters in the users table.
 */
final class Reporting implements ReportingInterface {
	public function __construct(private readonly Connection $db) {}

	public function topicOf(int $postId): ?ReportedTopicInterface {
		$row = $this->db->selectRow('SELECT t.id, t.subject, t.forum_id FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id WHERE p.id=?', $postId);

		return $row !== null ? new ReportedTopic($row->int('id'), $row->string('subject'), $row->int('forum_id')) : null;
	}

	public function add(NewReportInterface ...$reports): void {
		foreach ($reports as $report)
			$this->db->execute('INSERT INTO '.$this->db->table('reports').' (post_id, topic_id, forum_id, reported_by, created, message) VALUES (?, ?, ?, ?, ?, ?)',
				$report->postId(), $report->topicId(), $report->forumId(), $report->reporterId(), $report->createdAt(), $report->message());
	}

	public function recordMailSent(MailSentInterface ...$sent): void {
		foreach ($sent as $mail)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET last_email_sent=? WHERE id=?', $mail->at(), $mail->userId());
	}
}
