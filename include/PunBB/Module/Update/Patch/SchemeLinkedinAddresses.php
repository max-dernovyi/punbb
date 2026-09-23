<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;

/** The LinkedIn addresses a board between 1.3 and 1.4.1 stored without a scheme. */
final class SchemeLinkedinAddresses implements DataPatchInterface {
	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly BoardDataInterface $data
	) {}

	public function apply(int $startAt): PatchStep {
		$version = $this->settings->version() ?? '';

		if (version_compare($version, '1.3', '>') && version_compare($version, '1.4.1', '<'))
			$this->data->schemeLinkedinAddresses();

		return new PatchStep();
	}
}
