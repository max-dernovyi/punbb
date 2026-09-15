<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ActivationMailing;

/**
 * Runs pf_change_email_normal_pre_activation_email_sent with the member in
 * $user, the address in $new_email, its key in $new_email_key, and the mail in
 * $mail_subject and $mail_message, both read back.
 */
final class ActivationMailingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ActivationMailing $event): void {
		if (!LegacyScope::attached('pf_change_email_normal_pre_activation_email_sent'))
			return;

		ProfileState::publish($event->user());
		$GLOBALS['new_email'] = $event->email();
		$GLOBALS['new_email_key'] = $event->key();
		$GLOBALS['mail_subject'] = $event->subject();
		$GLOBALS['mail_message'] = $event->message();

		$this->scope->observe('pf_change_email_normal_pre_activation_email_sent', $event);

		$event->compose(Markers::markup($GLOBALS['mail_subject'] ?? ''), Markers::markup($GLOBALS['mail_message'] ?? ''));
	}
}
