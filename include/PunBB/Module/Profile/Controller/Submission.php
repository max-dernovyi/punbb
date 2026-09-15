<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Controller;

use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\SubmittedDetailsInterface;

/**
 * A section's form that was saved without sending the browser on: refused for
 * its errors, or an avatar uploaded, whose section is shown again.
 */
final readonly class Submission {
	/**
	 * @param ProfileUserInterface $user the member, with the avatar an upload stored
	 * @param list<string> $errors what stopped the form, each markup
	 */
	public function __construct(
		public ProfileUserInterface $user,
		public SubmittedDetailsInterface $details,
		public array $errors
	) {}
}
