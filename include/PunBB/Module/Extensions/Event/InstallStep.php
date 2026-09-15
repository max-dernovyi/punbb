<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of installing an extension or a hotfix: the install asked for, before
 * the manifest is read; the install confirmed, before the extension's code
 * runs; and the extension installed, before the browser is sent on.
 */
final class InstallStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const INSTALLED = 'installed';

	private const STEPS = array(self::SELECTED, self::SUBMITTED, self::INSTALLED);

	/**
	 * @param bool $hotfix whether a hotfix is fetched from the hotfix service
	 * @param string $id the extension, once its manifest is read
	 * @param ?ManifestInterface $manifest once it is read
	 */
	public function __construct(private readonly string $step, private readonly bool $hotfix, private readonly string $id = '', private readonly ?ManifestInterface $manifest = null) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Installing an extension has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function hotfix(): bool {
		return $this->hotfix;
	}

	public function id(): string {
		return $this->id;
	}

	public function manifest(): ?ManifestInterface {
		return $this->manifest;
	}
}
