<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ProfileSectionRequested;
use PunBB\Module\Site\Format\FormatterInterface;

/**
 * Runs pf_change_details_new_section with the section in $section, the member
 * in $user and their signature, as the board shows it, in $parsed_signature.
 * Code there that answers a section of its own includes header.php and
 * buffers its markup; one that does not end the page itself has it ended: the
 * markup fills the template's main region and footer.php sends the page.
 */
final class ProfileSectionObserver {
	public function __construct(private readonly PageScope $scope, private readonly FormatterInterface $formatter) {}

	public function observe(ProfileSectionRequested $event): void {
		if (!LegacyScope::attached('pf_change_details_new_section'))
			return;

		$user = $event->user();

		ProfileState::publish($user);
		$GLOBALS['section'] = $event->section();

		if ($user->signature() !== '')
			$GLOBALS['parsed_signature'] = $this->formatter->signature($user->signature())->html;

		$opened = isset($GLOBALS['tpl_main']);

		$this->scope->observe('pf_change_details_new_section', $event);

		if ($opened || !isset($GLOBALS['tpl_main']))
			return;

		$GLOBALS['tpl_main'] = str_replace('<!-- forum_main -->', Markers::markup(\forum_trim((string) ob_get_contents())), Markers::markup($GLOBALS['tpl_main']));
		ob_end_clean();

		LegacyScope::requireGlobally(LegacyChromeSource::root().'footer.php');
	}
}
