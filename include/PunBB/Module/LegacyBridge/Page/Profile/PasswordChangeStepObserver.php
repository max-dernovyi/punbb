<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\PasswordChangeStep;

/**
 * Runs the point at each step of changing a password, with the member in
 * $user, the key a reset mail carries in $key, the errors in $errors, read
 * back once the form is submitted, and the password stored in $new_password_hash.
 */
final class PasswordChangeStepObserver {
	/** @var array<string, array{string, string}> step => the point with a key, and without one */
	public const POINTS = array(
		PasswordChangeStep::SELECTED		=> array('pf_change_pass_selected', 'pf_change_pass_selected'),
		PasswordChangeStep::KEY_SUPPLIED	=> array('pf_change_pass_key_supplied', 'pf_change_pass_key_supplied'),
		PasswordChangeStep::SUBMITTED		=> array('pf_change_pass_key_form_submitted', 'pf_change_pass_normal_form_submitted'),
		PasswordChangeStep::CHANGED			=> array('pf_change_pass_key_pre_redirect', 'pf_change_pass_normal_pre_redirect'),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PasswordChangeStep $event): void {
		ProfileState::publish($event->user());

		if ($event->withKey())
			$GLOBALS['key'] = $event->key();

		$GLOBALS['errors'] = $event->errors();
		if ($event->step() === PasswordChangeStep::CHANGED)
			$GLOBALS['new_password_hash'] = $event->hash();

		$point = self::POINTS[$event->step()][$event->withKey() ? 0 : 1];
		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		if ($event->step() === PasswordChangeStep::SUBMITTED)
			$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));
	}
}
