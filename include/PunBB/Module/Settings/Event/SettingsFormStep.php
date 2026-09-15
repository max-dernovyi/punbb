<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Settings\Api\Data\SubmittedSettingsInterface;

/**
 * A step of saving a section's form, where an observer may change the settings
 * posted: the form submitted, before its section is chosen; the section about
 * to be validated, its settings as posted; the settings about to be stored,
 * once validated; and the settings stored, before the browser is sent back.
 */
final class SettingsFormStep implements EventInterface {
	public const SUBMITTED = 'submitted';

	public const VALIDATING = 'validating';

	public const UPDATING = 'updating';

	public const UPDATED = 'updated';

	private const STEPS = array(self::SUBMITTED, self::VALIDATING, self::UPDATING, self::UPDATED);

	/** @param string $section the section posted to: 'setup', 'features', or one an extension added */
	public function __construct(private readonly string $step, private readonly string $section, private readonly SubmittedSettingsInterface $settings) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Saving the settings has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function section(): string {
		return $this->section;
	}

	public function settings(): SubmittedSettingsInterface {
		return $this->settings;
	}
}
