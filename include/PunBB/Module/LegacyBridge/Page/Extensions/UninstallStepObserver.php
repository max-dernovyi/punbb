<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\UninstallStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of uninstalling an extension, with the extension
 * in $id and its row in $ext_data once it is looked up.
 */
final class UninstallStepObserver {
	public const POINTS = array(
		UninstallStep::SELECTED		=> 'aex_uninstall_selected',
		UninstallStep::SUBMITTED	=> 'aex_uninstall_comply_form_submitted',
		UninstallStep::UNINSTALLED	=> 'aex_uninstall_comply_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope, private readonly ExtensionRows $rows) {}

	public function observe(UninstallStep $event): void {
		$extension = $event->extension();
		if ($extension !== null)
		{
			$GLOBALS['id'] = $extension->id();
			$GLOBALS['ext_data'] = $this->rows->extension($extension);
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
