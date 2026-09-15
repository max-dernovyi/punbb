<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Login\Event\LoginStep;

/**
 * Runs the point at each step of signing in, with the attempt in the variables
 * login.php held it in: $form_username, $form_password and $save_pass, the
 * account in $user_id, $group_id, $db_password_hash, $form_password_hash and
 * $salt, $authorized and $errors. The errors are read back until the password
 * is checked, and whether the visitor is let in once it is.
 */
final class LoginStepObserver {
	public const POINTS = array(
		LoginStep::SUBMITTED	=> 'li_login_form_submitted',
		LoginStep::CHECKED		=> 'li_login_pre_auth_message',
		LoginStep::SIGNED_IN	=> 'li_login_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(LoginStep $event): void {
		$GLOBALS['form_username'] = $event->username();
		$GLOBALS['save_pass'] = $event->savesPassword();
		$GLOBALS['errors'] = $event->errors();

		// The page read the password into a variable its points saw; the event does not carry it
		$password = $_POST['req_password'] ?? null;
		$GLOBALS['form_password'] = is_string($password) ? \forum_trim($password) : '';

		$credentials = $event->credentials();
		if ($credentials !== null)
		{
			$GLOBALS['user_id'] = $credentials->userId();
			$GLOBALS['group_id'] = $credentials->groupId();
			$GLOBALS['form_password_hash'] = $credentials->passwordHash();
			$GLOBALS['salt'] = $credentials->salt();
		}

		if ($event->step() !== LoginStep::SUBMITTED)
			$GLOBALS['authorized'] = $event->authorized();

		$point = self::POINTS[$event->step()];
		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		if ($event->step() === LoginStep::CHECKED)
			$event->authorize(!empty($GLOBALS['authorized']) && $credentials !== null);

		if ($event->step() !== LoginStep::SIGNED_IN)
			$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));
	}
}
