<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Hook;

/**
 * Legacy hook id => what now covers the point: a final event class, or
 * <Api contract>::<method> for a plugin. Filled as pages move onto modules.
 */
final class HookMap {
	/** @var array<string, string> */
	public const COVERED = array();

	/** @param array<string, string> $covered */
	public function __construct(private readonly array $covered = self::COVERED) {}

	public function coveredBy(string $point): ?string {
		return $this->covered[$point] ?? null;
	}
}
