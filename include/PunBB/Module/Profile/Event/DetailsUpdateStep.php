<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\SubmittedDetailsInterface;

/**
 * A step of saving a section of a profile: the form submitted, before its
 * section is chosen; the section about to be checked, with what it saves read
 * from the form; the section checked, where an observer may change which
 * sections store nothing; the section about to be stored; the member's new
 * name stored, before it is put on their posts, topics and forums; and the
 * section stored, before the browser is sent back to it. Until it is stored,
 * an observer may change what the section saves and the errors that stop it,
 * each markup.
 */
final class DetailsUpdateStep implements EventInterface {
	public const SUBMITTED = 'submitted';

	public const VALIDATING = 'validating';

	public const VALIDATED = 'validated';

	public const STORING = 'storing';

	public const RENAMED = 'renamed';

	public const UPDATED = 'updated';

	private const STEPS = array(self::SUBMITTED, self::VALIDATING, self::VALIDATED, self::STORING, self::RENAMED, self::UPDATED);

	/**
	 * @param string $section the section posted: 'identity', 'settings', or one an extension added
	 * @param list<string> $errors
	 * @param list<string> $skippedSections the sections whose form stores nothing here, as the avatar's stores its upload
	 * @param string $oldName the member's name before a new one, once renamed
	 */
	public function __construct(
		private readonly string $step,
		private readonly string $section,
		private readonly ProfileUserInterface $user,
		private readonly SubmittedDetailsInterface $details,
		private array $errors = array(),
		private array $skippedSections = array(),
		private readonly string $oldName = ''
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Saving a profile has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function section(): string {
		return $this->section;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function details(): SubmittedDetailsInterface {
		return $this->details;
	}

	/** @return list<string> */
	public function errors(): array {
		return $this->errors;
	}

	/** @return list<string> */
	public function skippedSections(): array {
		return $this->skippedSections;
	}

	public function oldName(): string {
		return $this->oldName;
	}

	/** @param list<string> $errors */
	public function setErrors(array $errors): void {
		if (in_array($this->step, array(self::RENAMED, self::UPDATED), true))
			throw new InvalidArgumentException('A profile\'s errors change until it is stored only');

		$this->errors = $errors;
	}

	/** @param list<string> $sections */
	public function setSkippedSections(array $sections): void {
		if ($this->step !== self::VALIDATED)
			throw new InvalidArgumentException('The sections storing nothing change once a section is checked only');

		$this->skippedSections = $sections;
	}
}
