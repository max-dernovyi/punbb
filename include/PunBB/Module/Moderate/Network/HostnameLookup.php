<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Network;

final class HostnameLookup implements HostnameLookupInterface {
	public function hostname(string $address): string {
		$hostname = @gethostbyaddr($address);

		return is_string($hostname) ? $hostname : '';
	}
}
