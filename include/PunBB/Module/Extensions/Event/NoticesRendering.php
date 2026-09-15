<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position on the page showing what an extension's install or uninstall code
 * asked the administrator to read, which an observer may add markup at: before it and after it.
 */
final class NoticesRendering implements EventInterface {
	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	private const POSITIONS = array(self::OUTPUT_START, self::END);

	private string $markup = '';

	/** @param bool $installing whether the extension was installed; false when it was uninstalled */
	public function __construct(private readonly string $position, private readonly bool $installing, private readonly string $id) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The notices page has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function installing(): bool {
		return $this->installing;
	}

	public function id(): string {
		return $this->id;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
