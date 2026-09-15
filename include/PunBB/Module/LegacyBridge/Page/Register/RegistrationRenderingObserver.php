<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Register\Event\RegistrationRendering;

/**
 * Renders the registration form's markup points at their positions, with the
 * form's action and counts in $forum_page, the counts read back for the fields
 * that follow. At the start the notes above the form are in
 * $forum_page['frm_info'], before the errors the errors, and before the
 * language the languages in $languages; each is read back.
 */
final class RegistrationRenderingObserver {
	public const POINTS = array(
		RegistrationRendering::OUTPUT_START			=> 'rg_register_output_start',
		RegistrationRendering::PRE_REGISTER_ERRORS	=> 'rg_pre_register_errors',
		RegistrationRendering::PRE_GROUP			=> 'rg_register_pre_group',
		RegistrationRendering::PRE_EMAIL			=> 'rg_register_pre_email',
		RegistrationRendering::PRE_USERNAME			=> 'rg_register_pre_username',
		RegistrationRendering::PRE_PASSWORD			=> 'rg_register_pre_password',
		RegistrationRendering::PRE_CONFIRM_PASSWORD	=> 'rg_register_pre_confirm_password',
		RegistrationRendering::PRE_EMAIL_CONFIRM	=> 'rg_register_pre_email_confirm',
		RegistrationRendering::PRE_LANGUAGE			=> 'rg_register_pre_language',
		RegistrationRendering::PRE_GROUP_END		=> 'rg_register_pre_group_end',
		RegistrationRendering::GROUP_END			=> 'rg_register_group_end',
		RegistrationRendering::END					=> 'rg_end',
	);

	/** The group of parts each position publishes in $forum_page, by key. */
	private const GROUPS = array(
		RegistrationRendering::OUTPUT_START			=> array('frm_info', RegistrationRendering::INFO),
		RegistrationRendering::PRE_REGISTER_ERRORS	=> array('errors', RegistrationRendering::ERRORS),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(RegistrationRendering $event): void {
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

		$languages = $event->position() === RegistrationRendering::PRE_LANGUAGE;
		if ($languages)
			$GLOBALS['languages'] = $event->languages();

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

		if ($languages)
			$event->offerLanguages(array_values(Markers::entries(self::global('languages'))));
	}

	/** The global $name as extension code left it, which may have unset it. */
	private static function global(string $name): mixed {
		return $GLOBALS[$name] ?? null;
	}
}
