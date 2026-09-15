<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\RecipientInterface;
use PunBB\Module\Misc\Api\RecipientsInterface;

/**
 * The members in the users table.
 */
final class Recipients implements RecipientsInterface {
	public function __construct(private readonly Connection $db) {}

	public function find(int $userId): ?RecipientInterface {
		$row = $this->db->selectRow('SELECT u.username, u.email, u.email_setting FROM '.$this->db->table('users').' AS u WHERE u.id=?', $userId);

		return $row !== null ? new Recipient($userId, $row->string('username'), $row->nullableString('email') ?? '', $row->int('email_setting')) : null;
	}

	public function recordMailSent(MailSentInterface ...$sent): void {
		foreach ($sent as $mail)
			$this->db->execute('UPDATE '.$this->db->table('users').' SET last_email_sent=? WHERE id=?', $mail->at(), $mail->userId());
	}
}
