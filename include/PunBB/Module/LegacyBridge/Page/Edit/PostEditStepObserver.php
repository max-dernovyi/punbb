<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Edit;

use PunBB\Module\Edit\Event\PostEditStep;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of an edit, with it in the variables edit.php
 * held it in: $can_edit_subject, $errors, and once the form is read $subject,
 * $message and $hide_smilies. The errors are read back once submitted, and
 * everything is once checked.
 */
final class PostEditStepObserver {
	public const POINTS = array(
		PostEditStep::SELECTED	=> 'ed_post_selected',
		PostEditStep::SUBMITTED	=> 'ed_form_submitted',
		PostEditStep::VALIDATED	=> 'ed_end_validation',
		PostEditStep::EDITING	=> 'ed_pre_post_edited',
		PostEditStep::EDITED	=> 'ed_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostEditStep $event): void {
		$GLOBALS['can_edit_subject'] = $event->post()->isTopic();

		if ($event->step() === PostEditStep::SELECTED)
		{
			$this->scope->observe(self::POINTS[PostEditStep::SELECTED], $event);
			return;
		}

		$GLOBALS['errors'] = $event->errors();

		if ($event->step() !== PostEditStep::SUBMITTED)
		{
			if ($event->post()->isTopic())
				$GLOBALS['subject'] = $event->subject() ?? '';

			$GLOBALS['message'] = $event->message();
			$GLOBALS['hide_smilies'] = $event->hidesSmilies() ? 1 : 0;
		}

		$point = self::POINTS[$event->step()];
		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		if ($event->step() === PostEditStep::VALIDATED)
			$event->change(isset($GLOBALS['subject']) ? Markers::markup($GLOBALS['subject']) : null, Markers::markup($GLOBALS['message'] ?? ''), !empty($GLOBALS['hide_smilies']));

		if (in_array($event->step(), array(PostEditStep::SUBMITTED, PostEditStep::VALIDATED), true))
			$event->setErrors(array_values(Markers::entries($GLOBALS['errors'] ?? null)));
	}
}
