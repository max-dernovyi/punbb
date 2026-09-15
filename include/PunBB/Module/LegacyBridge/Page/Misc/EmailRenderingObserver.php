<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\EmailRendering;

/**
 * Renders the markup points of the form mailing a member at their positions, with
 * the form's counts in $forum_page, read back for the fields that follow. At
 * the start the form's action and hidden fields are in $forum_page too, and
 * before the errors the errors; both are read back.
 */
final class EmailRenderingObserver {
	public const POINTS = array(
		EmailRendering::OUTPUT_START			=> 'mi_email_output_start',
		EmailRendering::PRE_EMAIL_ERRORS		=> 'mi_pre_email_errors',
		EmailRendering::PRE_FIELDSET			=> 'mi_email_pre_fieldset',
		EmailRendering::PRE_SUBJECT				=> 'mi_email_pre_subject',
		EmailRendering::PRE_MESSAGE_CONTENTS	=> 'mi_email_pre_message_contents',
		EmailRendering::PRE_FIELDSET_END		=> 'mi_email_pre_fieldset_end',
		EmailRendering::FIELDSET_END			=> 'mi_email_fieldset_end',
		EmailRendering::END					=> 'mi_email_end',
	);

	/** The group of parts each position publishes in $forum_page, by key. */
	private const GROUPS = array(
		EmailRendering::OUTPUT_START		=> array('hidden_fields', EmailRendering::HIDDEN_FIELDS),
		EmailRendering::PRE_EMAIL_ERRORS	=> array('errors', EmailRendering::ERRORS),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(EmailRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$group = self::GROUPS[$event->position()] ?? null;
		if ($group !== null)
		{
			$parts = array();
			foreach ($event->names($group[1]) as $name)
				$parts[$name] = (string) $event->entry($group[1], $name);

			ForumPage::set($group[0], $parts);
		}

		if ($event->position() === EmailRendering::OUTPUT_START)
			ForumPage::set('form_action', $event->action());

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($group !== null)
		{
			foreach ($event->names($group[1]) as $name)
				$event->remove($group[1], $name);

			foreach (Markers::entries(ForumPage::get($group[0])) as $name => $markup)
				$event->set($group[1], (string) $name, $markup);
		}
	}
}
