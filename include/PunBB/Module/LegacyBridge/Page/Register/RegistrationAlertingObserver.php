<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Register\Event\RegistrationAlerting;

/**
 * Runs rg_register_banned_email and rg_register_dupe_email with the mail in
 * $mail_subject and $mail_message, both read back, and the account in
 * $user_info, $new_uid and $dupe_list.
 */
final class RegistrationAlertingObserver {
	public const POINTS = array(
		RegistrationAlerting::BANNED_EMAIL		=> 'rg_register_banned_email',
		RegistrationAlerting::DUPLICATE_EMAIL	=> 'rg_register_dupe_email',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(RegistrationAlerting $event): void {
		$point = self::POINTS[$event->alert()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['mail_subject'] = $event->subject();
		$GLOBALS['mail_message'] = $event->message();
		$GLOBALS['user_info'] = NewAccountRows::row($event->account());
		$GLOBALS['new_uid'] = $event->userId();
		$GLOBALS['dupe_list'] = $event->duplicates();

		$this->scope->observe($point, $event);

		$event->compose(Markers::markup($GLOBALS['mail_subject'] ?? ''), Markers::markup($GLOBALS['mail_message'] ?? ''));
	}
}
