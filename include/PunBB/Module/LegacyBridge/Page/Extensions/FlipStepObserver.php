<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\FlipStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of switching an extension, with the extension in
 * $id and, once it is switched, whether it was disabled in $disable.
 */
final class FlipStepObserver {
	public const POINTS = array(
		FlipStep::SELECTED	=> 'aex_flip_selected',
		FlipStep::FLIPPED	=> 'aex_flip_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(FlipStep $event): void {
		$GLOBALS['id'] = $event->id();

		if ($event->step() === FlipStep::FLIPPED)
			$GLOBALS['disable'] = $event->disabling();

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
