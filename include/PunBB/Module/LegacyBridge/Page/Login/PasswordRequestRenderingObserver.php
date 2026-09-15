<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Login\Event\PasswordRequestRendering;

/**
 * Renders the markup points of the form asking for a new password at their
 * positions, with the form's action and counts in $forum_page, the counts read
 * back for the fields that follow. Before the errors the errors are in
 * $forum_page too, and read back.
 */
final class PasswordRequestRenderingObserver {
	public const POINTS = array(
		PasswordRequestRendering::OUTPUT_START				=> 'li_forgot_pass_output_start',
		PasswordRequestRendering::PRE_NEW_PASSWORD_ERRORS	=> 'li_forgot_pass_pre_new_password_errors',
		PasswordRequestRendering::PRE_GROUP					=> 'li_forgot_pass_pre_group',
		PasswordRequestRendering::PRE_EMAIL					=> 'li_forgot_pass_pre_email',
		PasswordRequestRendering::PRE_GROUP_END				=> 'li_forgot_pass_pre_group_end',
		PasswordRequestRendering::GROUP_END					=> 'li_forgot_pass_group_end',
		PasswordRequestRendering::END						=> 'li_forgot_pass_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PasswordRequestRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$errors = $event->position() === PasswordRequestRendering::PRE_NEW_PASSWORD_ERRORS;
		if ($errors)
		{
			$parts = array();
			foreach ($event->names() as $name)
				$parts[$name] = (string) $event->entry($name);

			ForumPage::set('errors', $parts);
		}

		ForumPage::set('form_action', $event->action());
		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($errors)
		{
			foreach ($event->names() as $name)
				$event->remove($name);

			foreach (Markers::entries(ForumPage::get('errors')) as $name => $markup)
				$event->set((string) $name, $markup);
		}
	}
}
