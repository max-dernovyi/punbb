<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\EmailChangeStep;

/**
 * Runs the point at each step of changing an address, with the member in
 * $user, the key a confirmation mail carries in $key, the address asked for in
 * $new_email, the other members with it in $dupe_list, and the errors in
 * $errors, read back once the form is submitted.
 */
final class EmailChangeStepObserver {
	public const POINTS = array(
		EmailChangeStep::SELECTED		=> 'pf_change_email_selected',
		EmailChangeStep::KEY_SUPPLIED	=> 'pf_change_email_key_supplied',
		EmailChangeStep::SUBMITTED		=> 'pf_change_email_normal_form_submitted',
		EmailChangeStep::BANNED			=> 'pf_change_email_normal_banned_email',
		EmailChangeStep::DUPLICATE		=> 'pf_change_email_normal_dupe_email',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(EmailChangeStep $event): void {
		ProfileState::publish($event->user());

		$GLOBALS['errors'] = $event->errors();

		switch ($event->step())
		{
			case EmailChangeStep::KEY_SUPPLIED:
				$GLOBALS['key'] = $event->key();
				break;

			case EmailChangeStep::BANNED:
				$GLOBALS['new_email'] = $event->email();
				break;

			case EmailChangeStep::DUPLICATE:
				$GLOBALS['new_email'] = $event->email();
				$GLOBALS['dupe_list'] = $event->duplicates();
				break;
		}

		if (!LegacyScope::attached(self::POINTS[$event->step()]))
			return;

		$this->scope->observe(self::POINTS[$event->step()], $event);

		if (!in_array($event->step(), array(EmailChangeStep::SELECTED, EmailChangeStep::KEY_SUPPLIED), true))
			$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));
	}
}
