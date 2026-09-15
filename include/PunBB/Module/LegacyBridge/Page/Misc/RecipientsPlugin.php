<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\RecipientInterface;
use PunBB\Module\Misc\Api\RecipientsInterface;
use PunBB\Module\Misc\Model\Recipient;

/**
 * The query points of mailing a member, with the query arrays misc.php built.
 * A query a point changed answers instead; a statement a point changed runs
 * instead, and the repository is handed nothing to store. The member is left
 * in $recipient_info, with any column a point added.
 */
final class RecipientsPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	public function afterFind(RecipientsInterface $subject, ?RecipientInterface $result, int $userId): ?RecipientInterface {
		$query = array(
			'SELECT'	=> 'u.username, u.email, u.email_setting',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id='.$userId
		);

		$row = null;
		if ($this->queries->changed('mi_email_qr_get_form_email_data', RecipientsInterface::class.'::find', $query))
		{
			$row = PluggedQuery::rows($query)[0] ?? null;
			$result = $row !== null ? new Recipient($userId, Markers::markup($row['username'] ?? ''), Markers::markup($row['email'] ?? ''), (int) Markers::markup($row['email_setting'] ?? 0)) : null;
		}

		$GLOBALS['recipient_info'] = $row ?? ($result !== null ? array('username' => $result->username(), 'email' => $result->email(), 'email_setting' => $result->emailSetting()) : false);

		return $result;
	}

	/** @return list<MailSentInterface>|null */
	public function beforeRecordMailSent(RecipientsInterface $subject, MailSentInterface ...$sent): ?array {
		$kept = array();
		foreach ($sent as $mail)
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'last_email_sent='.$mail->at(),
				'WHERE'		=> 'id='.$mail->userId(),
			);

			if ($this->queries->changed('mi_email_qr_update_last_email_sent', RecipientsInterface::class.'::recordMailSent', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $mail;
		}

		return count($kept) !== count($sent) ? $kept : null;
	}
}
