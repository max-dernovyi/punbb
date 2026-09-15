<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\InstallFormRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the install form's markup points at their positions; before the
 * errors, the errors are in $forum_page['errors'], read back.
 */
final class InstallFormObserver {
	public const POINTS = array(
		InstallFormRendering::OUTPUT_START	=> 'aex_install_output_start',
		InstallFormRendering::PRE_ERRORS	=> 'aex_install_ext_pre_errors',
		InstallFormRendering::END			=> 'aex_install_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(InstallFormRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$errors = $event->position() === InstallFormRendering::PRE_ERRORS;
		if ($errors)
		{
			$entries = array();
			foreach ($event->names() as $name)
				$entries[$name] = (string) $event->entry($name);

			ForumPage::set('errors', $entries);
		}

		$event->append($this->scope->renderObserved($point, $event));

		if ($errors)
		{
			foreach ($event->names() as $name)
				$event->remove($name);

			foreach (Markers::entries(ForumPage::get('errors')) as $name => $markup)
				$event->set((string) $name, $markup);
		}
	}
}
