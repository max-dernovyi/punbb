<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The links offered for an installed extension or hotfix in its list, before
 * they are joined with spaces: switching it, uninstalling it, downloading a
 * newer version. Markup appended goes before the extension.
 */
final class ExtensionActionsAssembling implements EventInterface {
	use MarkupEntries;

	/**
	 * @param string $list ExtensionListRendering::MANAGE or ::HOTFIXES
	 * @param array<string, string> $actions name => markup
	 */
	public function __construct(private readonly string $list, private readonly InstalledExtensionInterface $extension, array $actions) {
		if (!in_array($list, array(ExtensionListRendering::MANAGE, ExtensionListRendering::HOTFIXES), true))
			throw new InvalidArgumentException(sprintf('There is no list of %s', $list));

		$this->entries = $actions;
	}

	private string $markup = '';

	public function list(): string {
		return $this->list;
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

	private function accept(string $name): void {}
}
