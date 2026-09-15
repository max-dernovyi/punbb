<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position on the form confirming an uninstall, which an observer may add markup at: before it and after it.
 */
final class UninstallFormRendering implements EventInterface {
	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	private const POSITIONS = array(self::OUTPUT_START, self::END);

	private string $markup = '';

	public function __construct(private readonly string $position, private readonly InstalledExtensionInterface $extension) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The uninstall form has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function extension(): InstalledExtensionInterface {
		return $this->extension;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
