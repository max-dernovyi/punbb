<?php
/**
 * The members mailed and the reports filed through misc.php over an in-memory
 * SQLite database with the forum's tables: a recipient, a reported post's
 * topic, a report stored, and when the sender last mailed.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Driver\Sqlite3Driver;
use PunBB\Module\Misc\Model\MailSent;
use PunBB\Module\Misc\Model\NewReport;
use PunBB\Module\Misc\Model\Recipients;
use PunBB\Module\Misc\Model\Reporting;

class ReportingTest extends TestCase {
	private Connection $db;

	protected function setUp(): void {
		$this->db = new Connection(new Sqlite3Driver(new SQLite3(':memory:')), 'pun_');
		$this->db->execute('CREATE TABLE pun_users (id INTEGER PRIMARY KEY, username VARCHAR(200) NOT NULL, email VARCHAR(80), email_setting TINYINT NOT NULL DEFAULT 1, last_email_sent INTEGER)');
		$this->db->execute('CREATE TABLE pun_topics (id INTEGER PRIMARY KEY, subject VARCHAR(255) NOT NULL, forum_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_posts (id INTEGER PRIMARY KEY, topic_id INTEGER NOT NULL)');
		$this->db->execute('CREATE TABLE pun_reports (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, topic_id INTEGER NOT NULL, forum_id INTEGER NOT NULL, reported_by INTEGER NOT NULL, created INTEGER NOT NULL, message TEXT)');
		$this->db->execute('INSERT INTO pun_users (id, username, email, email_setting) VALUES (5, ?, ?, 2), (6, ?, NULL, 0)', 'anna', 'anna@example.com', 'bob');
		$this->db->execute('INSERT INTO pun_topics (id, subject, forum_id) VALUES (4, ?, 7)', 'It\'s "here"');
		$this->db->execute('INSERT INTO pun_posts (id, topic_id) VALUES (9, 4)');
	}

	public function testARecipientIsFoundWithHowTheyTakeMail(): void {
		$recipients = new Recipients($this->db);

		$anna = $recipients->find(5);
		$this->assertSame(array(5, 'anna', 'anna@example.com', 2), array($anna?->id(), $anna?->username(), $anna?->email(), $anna?->emailSetting()));
		$this->assertSame('', $recipients->find(6)?->email());
		$this->assertNull($recipients->find(7));

		$recipients->recordMailSent(new MailSent(6, 3000));
		$this->assertSame(3000, (int) $this->db->selectValue('SELECT last_email_sent FROM pun_users WHERE id=6'));
	}

	public function testAReportIsFiledInThePostsTopic(): void {
		$reporting = new Reporting($this->db);

		$topic = $reporting->topicOf(9);
		$this->assertSame(array(4, 'It\'s "here"', 7), array($topic?->id(), $topic?->subject(), $topic?->forumId()));
		$this->assertNull($reporting->topicOf(8));

		$reporting->add(new NewReport(9, 4, 7, 5, 1000, 'Spam\'s here'));
		$reporting->recordMailSent(new MailSent(5, 1000));

		$this->assertSame(array('post_id' => 9, 'topic_id' => 4, 'forum_id' => 7, 'reported_by' => 5, 'created' => 1000, 'message' => 'Spam\'s here'),
			array_map(static fn (mixed $value): mixed => is_numeric($value) ? (int) $value : $value, $this->db->selectRow('SELECT post_id, topic_id, forum_id, reported_by, created, message FROM pun_reports')?->values() ?? array()));
		$this->assertSame(1000, (int) $this->db->selectValue('SELECT last_email_sent FROM pun_users WHERE id=5'));
	}
}
