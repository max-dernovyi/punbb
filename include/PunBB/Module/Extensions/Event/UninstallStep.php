<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of uninstalling an extension or a hotfix: the uninstall asked for,
 * before the extension is looked up; the uninstall confirmed, before the
 * extension's code runs; and the extension gone, before the browser is sent on.
 */
final class UninstallStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const UNINSTALLED = 'uninstalled';

	private const STEPS = array(self::SELECTED, self::SUBMITTED, self::UNINSTALLED);

	/** @param ?InstalledExtensionInterface $extension once it is looked up */
	public function __construct(private readonly string $step, private readonly ?InstalledExtensionInterface $extension = null) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Uninstalling an extension has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function extension(): ?InstalledExtensionInterface {
		return $this->extension;
	}
}
