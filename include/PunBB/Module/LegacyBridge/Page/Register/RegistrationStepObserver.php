<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Register\Event\RegistrationStep;

/**
 * Runs the point at each step of registering, with the registration in the
 * variables register.php held it in: $errors, read back until the form is
 * checked; $username, $email1 and $password1 once it is; $user_info, read
 * back before the account is stored, and $new_uid once it is.
 */
final class RegistrationStepObserver {
	public const POINTS = array(
		RegistrationStep::SUBMITTED	=> 'rg_register_form_submitted',
		RegistrationStep::VALIDATED	=> 'rg_register_end_validation',
		RegistrationStep::ADDING	=> 'rg_register_pre_add_user',
		RegistrationStep::ADDED		=> 'rg_register_pre_login_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(RegistrationStep $event): void {
		$GLOBALS['errors'] = $event->errors();

		if ($event->step() !== RegistrationStep::SUBMITTED)
		{
			$GLOBALS['username'] = $event->username();
			$GLOBALS['email1'] = $event->email();
			$GLOBALS['password1'] = $event->password();
		}

		$account = $event->account();
		if ($account !== null)
			$GLOBALS['user_info'] = NewAccountRows::row($account);

		if ($event->step() === RegistrationStep::ADDED)
			$GLOBALS['new_uid'] = $event->userId();

		$point = self::POINTS[$event->step()];
		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		if (in_array($event->step(), array(RegistrationStep::SUBMITTED, RegistrationStep::VALIDATED), true))
			$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));

		if ($event->step() === RegistrationStep::ADDING && $account !== null)
			$event->replaceAccount(NewAccountRows::account($GLOBALS['user_info'] ?? null, $account));
	}
}
