<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Login\Event\PasswordRequestStep;

/**
 * Runs li_forgot_pass_selected, and li_forgot_pass_end_validation with the
 * address in $email and $errors, read back.
 */
final class PasswordRequestStepObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PasswordRequestStep $event): void {
		if ($event->step() === PasswordRequestStep::SELECTED)
		{
			$this->scope->observe('li_forgot_pass_selected', $event);
			return;
		}

		$GLOBALS['email'] = $event->email();
		$GLOBALS['errors'] = $event->errors();

		if (!LegacyScope::attached('li_forgot_pass_end_validation'))
			return;

		$this->scope->observe('li_forgot_pass_end_validation', $event);

		$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));
	}
}
