<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Routing;

/**
 * The plain URL a rewrite rule turned a pretty one into.
 */
final readonly class Rewrite {
	/**
	 * @param string $target the path the rule names, before any query
	 * @param array<array-key, string> $parameters the rule's query, name => URL-decoded value, in order
	 */
	public function __construct(
		public string $target,
		public array $parameters
	) {}
}
