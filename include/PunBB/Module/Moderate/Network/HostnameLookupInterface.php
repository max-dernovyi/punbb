<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Network;

/**
 * The name the address of a post resolves to, as its reverse zone says it.
 */
interface HostnameLookupInterface {
	/** The name $address resolves to; the address itself when it resolves to none, '' when it is no address. */
	public function hostname(string $address): string;
}
