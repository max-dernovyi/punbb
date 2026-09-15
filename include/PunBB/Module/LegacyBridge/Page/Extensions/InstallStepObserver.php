<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\InstallStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of installing an extension, with the extension
 * in $id and its manifest in $ext_data once they are read.
 */
final class InstallStepObserver {
	public const POINTS = array(
		InstallStep::SELECTED	=> 'aex_install_selected',
		InstallStep::SUBMITTED	=> 'aex_install_comply_form_submitted',
		InstallStep::INSTALLED	=> 'aex_install_comply_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly ExtensionRows $rows) {}

	public function observe(InstallStep $event): void {
		$manifest = $event->manifest();
		if ($manifest !== null)
		{
			$GLOBALS['id'] = $event->id();
			$GLOBALS['ext_data'] = $this->rows->manifest($manifest);
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
