<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\PostRendering;
use PunBB\Module\Site\Config\SettingsInterface;

/**
 * Renders the posting page's markup points at their positions, with the
 * form's counts in $forum_page, read back for the fields that follow. At the
 * start the form's action, hidden fields, attributes and help links are in
 * $forum_page too, and before the errors the errors; each is read back. In the
 * guest's fieldset the field their address goes in is in
 * $forum_page['email_form_name']; at the end the forum is left in $forum_id,
 * where the jump list below the page preselects it.
 */
final class PostRenderingObserver {
	public const POINTS = array(
		PostRendering::MAIN_OUTPUT_START			=> 'po_main_output_start',
		PostRendering::PREVIEW_NEW_POST_HEAD_OPTION	=> 'po_preview_new_post_head_option',
		PostRendering::PREVIEW_NEW_POST_ENTRY_DATA	=> 'po_preview_new_post_entry_data',
		PostRendering::PRE_POST_ERRORS				=> 'po_pre_post_errors',
		PostRendering::PRE_GUEST_INFO_FIELDSET		=> 'po_pre_guest_info_fieldset',
		PostRendering::PRE_GUEST_USERNAME			=> 'po_pre_guest_username',
		PostRendering::PRE_GUEST_EMAIL				=> 'po_pre_guest_email',
		PostRendering::PRE_GUEST_INFO_FIELDSET_END	=> 'po_pre_guest_info_fieldset_end',
		PostRendering::GUEST_INFO_FIELDSET_END		=> 'po_guest_info_fieldset_end',
		PostRendering::PRE_REQ_INFO_FIELDSET		=> 'po_pre_req_info_fieldset',
		PostRendering::PRE_REQ_SUBJECT				=> 'po_pre_req_subject',
		PostRendering::PRE_POST_CONTENTS			=> 'po_pre_post_contents',
		PostRendering::PRE_CHECKBOX_FIELDSET_END	=> 'po_pre_checkbox_fieldset_end',
		PostRendering::PRE_REQ_INFO_FIELDSET_END	=> 'po_pre_req_info_fieldset_end',
		PostRendering::REQ_INFO_FIELDSET_END		=> 'po_req_info_fieldset_end',
		PostRendering::MAIN_OUTPUT_END				=> 'po_main_output_end',
		PostRendering::END							=> 'po_end',
	);

	/** $forum_page key => the group of parts it holds, by the position that publishes it */
	private const GROUPS = array(
		PostRendering::MAIN_OUTPUT_START	=> array(
			'hidden_fields'		=> PostRendering::HIDDEN_FIELDS,
			'form_attributes'	=> PostRendering::FORM_ATTRIBUTES,
			'text_options'		=> PostRendering::TEXT_OPTIONS,
		),
		PostRendering::PRE_POST_ERRORS		=> array(
			'errors'			=> PostRendering::ERRORS,
		),
	);

	/** The positions of the guest's fieldset. */
	private const GUEST = array(
		PostRendering::PRE_GUEST_INFO_FIELDSET, PostRendering::PRE_GUEST_USERNAME, PostRendering::PRE_GUEST_EMAIL,
		PostRendering::PRE_GUEST_INFO_FIELDSET_END, PostRendering::GUEST_INFO_FIELDSET_END,
	);

	public function __construct(private readonly PageScope $scope, private readonly SettingsInterface $settings) {}

	public function observe(PostRendering $event): void {
		if ($event->position() === PostRendering::END)
			$GLOBALS['forum_id'] = $event->location()->forumId();

		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$groups = self::GROUPS[$event->position()] ?? array();
		foreach ($groups as $key => $group)
		{
			$parts = array();
			foreach ($event->names($group) as $name)
				$parts[$name] = (string) $event->entry($group, $name);

			ForumPage::set($key, $parts);
		}

		if ($event->position() === PostRendering::MAIN_OUTPUT_START)
			ForumPage::set('form_action', $event->action());

		if (in_array($event->position(), self::GUEST, true))
			ForumPage::set('email_form_name', $this->settings->value('p_force_guest_email') === '1' ? 'req_email' : 'email');

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		foreach ($groups as $key => $group)
		{
			foreach ($event->names($group) as $name)
				$event->remove($group, $name);

			foreach (Markers::entries(ForumPage::get($key)) as $name => $markup)
				$event->set($group, (string) $name, $markup);
		}
	}
}
