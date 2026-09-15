<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Security;

/**
 * The tokens a form carries to prove the visitor sent it: each is bound to the
 * visit and to the URL the form posts to.
 */
interface CsrfTokensInterface {
	/** The token for a form posting to $target. */
	public function token(string $target): string;

	/** Whether $submitted, as the request carries it, is the token for $target. */
	public function matches(mixed $submitted, string $target): bool;

	/** Whether a form whose token does not match is shown to the visitor to confirm; the board's configuration can turn that off. */
	public function confirms(): bool;
}
