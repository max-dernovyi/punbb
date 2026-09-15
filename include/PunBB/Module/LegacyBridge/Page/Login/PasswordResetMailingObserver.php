<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Login\Event\PasswordResetMailing;

/**
 * Runs the points of mailing reset keys, which login.php ran after the answer
 * was delivered, with what they read in the variables it held it in: $email
 * and $users_with_email; $mail_subject and $mail_message, read back once
 * composed; the account in $cur_hit with $forgot_pass_timeout, read back; and
 * $new_password_key with $cur_mail_message, read back once addressed.
 */
final class PasswordResetMailingObserver {
	public const POINTS = array(
		PasswordResetMailing::STARTING	=> 'li_forgot_pass_pre_email',
		PasswordResetMailing::COMPOSED	=> 'li_forgot_pass_new_general_replace_data',
		PasswordResetMailing::CHECKING	=> 'li_forgot_pass_pre_flood_check',
		PasswordResetMailing::ADDRESSED	=> 'li_forgot_pass_new_user_replace_data',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PasswordResetMailing $event): void {
		$GLOBALS['email'] = $event->email();
		$GLOBALS['users_with_email'] = array_map(ResettableRows::row(...), $event->accounts());

		if ($event->step() !== PasswordResetMailing::STARTING)
		{
			$GLOBALS['mail_subject'] = $event->subject();
			$GLOBALS['mail_message'] = $event->message();
		}

		$account = $event->account();
		if ($account !== null)
		{
			$GLOBALS['cur_hit'] = ResettableRows::row($account);
			$GLOBALS['forgot_pass_timeout'] = $event->keyLifetime();
		}

		if ($event->step() === PasswordResetMailing::ADDRESSED)
		{
			$GLOBALS['new_password_key'] = $event->key();
			$GLOBALS['cur_mail_message'] = $event->message();
		}

		$point = self::POINTS[$event->step()];
		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		match ($event->step()) {
			PasswordResetMailing::COMPOSED	=> $event->compose(Markers::markup($GLOBALS['mail_subject'] ?? ''), Markers::markup($GLOBALS['mail_message'] ?? '')),
			PasswordResetMailing::CHECKING	=> $event->setKeyLifetime((int) Markers::markup($GLOBALS['forgot_pass_timeout'] ?? 0)),
			PasswordResetMailing::ADDRESSED	=> $event->address(Markers::markup($GLOBALS['cur_mail_message'] ?? '')),
			default							=> null,
		};
	}
}
