<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * A position in the registration form, which an observer may add markup at.
 * The form numbers its field group, items and fields in order; markup that
 * adds any of them counts them, and the form numbers on from there. At the
 * start the notes above the form, before the errors the errors, each named,
 * may still change; before the language the languages offered may too, and a
 * choice is offered only among more than one.
 */
final class RegistrationRendering implements EventInterface {
	use PartsByName;

	public const OUTPUT_START = 'output_start';

	public const PRE_REGISTER_ERRORS = 'pre_register_errors';

	public const PRE_GROUP = 'pre_group';

	public const PRE_EMAIL = 'pre_email';

	public const PRE_USERNAME = 'pre_username';

	public const PRE_PASSWORD = 'pre_password';

	/** Only when the visitor chooses their password. */
	public const PRE_CONFIRM_PASSWORD = 'pre_confirm_password';

	public const PRE_EMAIL_CONFIRM = 'pre_email_confirm';

	public const PRE_LANGUAGE = 'pre_language';

	public const PRE_GROUP_END = 'pre_group_end';

	public const GROUP_END = 'group_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::OUTPUT_START, self::PRE_REGISTER_ERRORS, self::PRE_GROUP, self::PRE_EMAIL, self::PRE_USERNAME, self::PRE_PASSWORD,
		self::PRE_CONFIRM_PASSWORD, self::PRE_EMAIL_CONFIRM, self::PRE_LANGUAGE, self::PRE_GROUP_END, self::GROUP_END, self::END,
	);

	/** The groups of parts the event carries. */
	public const INFO = 'info';

	public const ERRORS = 'errors';

	private string $markup = '';

	/**
	 * @param string $action the URL the form posts to
	 * @param list<string> $languages
	 */
	public function __construct(
		private readonly string $position,
		private readonly string $action,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount,
		?Parts $info = null,
		?Parts $errors = null,
		private array $languages = array()
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The registration form has no position "%s"', $position));

		$this->parts = array(
			self::INFO		=> $info ?? new Parts(),
			self::ERRORS	=> $errors ?? new Parts(),
		);
	}

	public function position(): string {
		return $this->position;
	}

	public function action(): string {
		return $this->action;
	}

	public function groupCount(): int {
		return $this->groupCount;
	}

	public function itemCount(): int {
		return $this->itemCount;
	}

	public function fieldCount(): int {
		return $this->fieldCount;
	}

	/** The form's counts once the markup added here is counted in. */
	public function count(int $groups, int $items, int $fields): void {
		$this->groupCount = $groups;
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}

	/** @return list<string> the languages offered, by the name of their pack */
	public function languages(): array {
		return $this->languages;
	}

	/** @param list<string> $languages */
	public function offerLanguages(array $languages): void {
		if ($this->position !== self::PRE_LANGUAGE)
			throw new InvalidArgumentException('The languages offered change only before the language is chosen');

		$this->languages = $languages;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
