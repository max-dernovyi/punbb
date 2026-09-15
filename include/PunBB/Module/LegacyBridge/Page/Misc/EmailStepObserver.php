<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\EmailStep;

/**
 * Runs the point at each step of mailing a member, with the mail in the
 * variables misc.php held it in: $recipient_id, $subject and $message once
 * checked, $errors, and $mail_subject and $mail_message once composed. The
 * errors are read back once checked, the subject and message once composed.
 */
final class EmailStepObserver {
	public const POINTS = array(
		EmailStep::SELECTED		=> 'mi_email_selected',
		EmailStep::SUBMITTED	=> 'mi_email_form_submitted',
		EmailStep::VALIDATED	=> 'mi_email_end_validation',
		EmailStep::COMPOSED		=> 'mi_email_new_replace_data',
		EmailStep::SENT			=> 'mi_email_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(EmailStep $event): void {
		$GLOBALS['recipient_id'] = $event->recipientId();

		if ($event->step() === EmailStep::SELECTED)
			$GLOBALS['errors'] = array();

		if (!in_array($event->step(), array(EmailStep::SELECTED, EmailStep::SUBMITTED), true))
		{
			$GLOBALS['subject'] = $event->subject();
			$GLOBALS['message'] = $event->message();
			$GLOBALS['errors'] = $event->errors();
		}

		if ($event->step() === EmailStep::COMPOSED)
		{
			$GLOBALS['mail_subject'] = $event->mailSubject();
			$GLOBALS['mail_message'] = $event->mailMessage();
		}

		$point = self::POINTS[$event->step()];
		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		match ($event->step()) {
			EmailStep::VALIDATED	=> $event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null))),
			EmailStep::COMPOSED		=> $event->compose(Markers::markup($GLOBALS['mail_subject'] ?? ''), Markers::markup($GLOBALS['mail_message'] ?? '')),
			default					=> null,
		};
	}
}
