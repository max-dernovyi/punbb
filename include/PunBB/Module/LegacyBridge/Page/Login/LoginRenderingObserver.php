<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Login\Event\LoginRendering;

/**
 * Renders the login form's markup points at their positions, with the form's
 * counts in $forum_page, read back for the fields that follow. At the start
 * the form's action and hidden fields are in $forum_page too, and before the
 * errors the errors; both are read back.
 */
final class LoginRenderingObserver {
	public const POINTS = array(
		LoginRendering::OUTPUT_START				=> 'li_login_output_start',
		LoginRendering::PRE_LOGIN_ERRORS			=> 'li_pre_login_errors',
		LoginRendering::PRE_LOGIN_GROUP				=> 'li_login_pre_login_group',
		LoginRendering::PRE_USERNAME				=> 'li_login_pre_username',
		LoginRendering::PRE_PASS					=> 'li_login_pre_pass',
		LoginRendering::PRE_REMEMBER_ME_CHECKBOX	=> 'li_login_pre_remember_me_checkbox',
		LoginRendering::PRE_GROUP_END				=> 'li_login_pre_group_end',
		LoginRendering::GROUP_END					=> 'li_login_group_end',
		LoginRendering::END							=> 'li_end',
	);

	/** The group of parts each position publishes in $forum_page, by key. */
	private const GROUPS = array(
		LoginRendering::OUTPUT_START		=> array('hidden_fields', LoginRendering::HIDDEN_FIELDS),
		LoginRendering::PRE_LOGIN_ERRORS	=> array('errors', LoginRendering::ERRORS),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(LoginRendering $event): void {
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

		if ($event->position() === LoginRendering::OUTPUT_START)
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
