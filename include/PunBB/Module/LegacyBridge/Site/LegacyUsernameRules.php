<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Language\LanguageInterface;

/**
 * validate_username() of include/functions.php, with the extension code
 * attached to it, over the profile pack it reads its messages from.
 */
final class LegacyUsernameRules implements UsernameRulesInterface {
	public function __construct(private readonly LanguageInterface $language) {}

	public function validate(string $username, ?int $exceptUserId = null): array {
		$this->language->strings('profile');

		$errors = \validate_username($username, $exceptUserId);

		return array_values(array_map(static fn (mixed $error): Html => new Html(Markers::markup($error)), is_array($errors) ? $errors : array()));
	}
}
