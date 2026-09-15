<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Routing;

/**
 * A SEF scheme's rewrite rules, pattern => target. The first pattern matching
 * a path is replaced into its target; the target's query becomes parameters,
 * split on & and = with no further parsing, each value URL-decoded.
 */
final readonly class RewriteRules {
	/** @param array<mixed> $rules pattern => target, in order; an entry that is not a pair of strings is skipped */
	public function __construct(private array $rules) {}

	public function rewrite(string $path): ?Rewrite {
		foreach ($this->rules as $pattern => $target)
		{
			if (!is_string($pattern) || !is_string($target) || preg_match($pattern, $path) !== 1)
				continue;

			$parts = explode('?', (string) preg_replace($pattern, $target, $path));

			$parameters = array();
			if (isset($parts[1]))
			{
				foreach (explode('&', $parts[1]) as $parameter)
				{
					$pair = explode('=', $parameter);
					$parameters[$pair[0]] = urldecode($pair[1] ?? '');
				}
			}

			return new Rewrite($parts[0], $parameters);
		}

		return null;
	}
}
