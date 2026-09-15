<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Url;

/**
 * An address a member gave as their website, as a link to it takes it.
 */
final readonly class WebAddress {
	/**
	 * @param string $href where the link goes: in its IDNA form, where the board converts international names
	 * @param string $text what the link shows: decoded from its IDNA form, where the board converts them
	 */
	public function __construct(public string $href, public string $text) {}
}
