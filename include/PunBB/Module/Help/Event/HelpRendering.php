<?php

declare(strict_types=1);

namespace PunBB\Module\Help\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the help page, which an observer may add markup at.
 */
final class HelpRendering implements EventInterface {
	public const START = 'start';

	/** The last of the BBCode text styles. */
	public const TEXT_STYLES = 'text_styles';

	/** The last of the BBCode links. */
	public const LINKS = 'links';

	/** After the last BBCode box. */
	public const BBCODE = 'bbcode';

	/** After the image box. */
	public const IMAGES = 'images';

	/** After the section shown, whichever it is. */
	public const SECTION = 'section';

	public const END = 'end';

	private const POSITIONS = array(self::START, self::TEXT_STYLES, self::LINKS, self::BBCODE, self::IMAGES, self::SECTION, self::END);

	private string $markup = '';

	public function __construct(private readonly string $position, private readonly string $section) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The help page has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	/** The section asked for: 'bbcode', 'img', 'smilies', or another the page shows nothing for. */
	public function section(): string {
		return $this->section;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
