<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\ModeratedTopicAssembling;

/**
 * Runs the point at each stage of a topic's row in the moderation of its forum
 * with the topic as $cur_topic, its subject censored after the first stage,
 * and the row's parts, the rows and the checkboxes counted in $forum_page, all
 * read back.
 */
final class ModeratedTopicObserver {
	public const POINTS = array(
		ModeratedTopicAssembling::START			=> 'mr_topic_actions_row_loop_start',
		ModeratedTopicAssembling::MOVED_SUBJECT	=> 'mr_topic_actions_moved_row_pre_item_subject_merge',
		ModeratedTopicAssembling::MOVED_ROW		=> 'mr_topic_actions_moved_row_pre_output',
		ModeratedTopicAssembling::TITLE_STATUS	=> 'mr_topic_loop_normal_topic_pre_item_title_status_merge',
		ModeratedTopicAssembling::TITLE			=> 'mr_topic_loop_normal_topic_pre_item_title_merge',
		ModeratedTopicAssembling::NAV			=> 'mr_topic_loop_normal_topic_pre_item_nav_merge',
		ModeratedTopicAssembling::NORMAL_ROW	=> 'mr_topic_actions_normal_row_pre_output',
		ModeratedTopicAssembling::STATUS		=> 'mr_topic_actions_row_pre_item_status_merge',
		ModeratedTopicAssembling::ROW			=> 'mr_topic_actions_row_pre_display',
	);

	/** $forum_page's item array => the parts of the row it holds */
	private const ITEMS = array(
		'item_status'		=> ModeratedTopicAssembling::PART_STATUS,
		'item_title'		=> ModeratedTopicAssembling::PART_TITLE,
		'item_title_status'	=> ModeratedTopicAssembling::PART_TITLE_STATUS,
		'item_nav'			=> ModeratedTopicAssembling::PART_NAV,
		'item_subject'		=> ModeratedTopicAssembling::PART_SUBJECT,
	);

	public function __construct(private readonly PageScope $scope, private readonly ModerationRows $rows) {}

	public function observe(ModeratedTopicAssembling $event): void {
		$point = self::POINTS[$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		// The page's query asked who posted only for a member, where the board marks it
		$user = is_array($GLOBALS['forum_user'] ?? null) ? $GLOBALS['forum_user'] : array();
		$config = is_array($GLOBALS['forum_config'] ?? null) ? $GLOBALS['forum_config'] : array();
		$postedBy = empty($user['is_guest']) && Markers::markup($config['o_show_dot'] ?? '') === '1' ? (int) Markers::markup($user['id'] ?? 0) : null;
		$topic = $this->rows->listed($event->topic(), $postedBy);
		$topic['subject'] = $event->subject();
		$GLOBALS['cur_topic'] = $topic;

		$page = ForumPage::all();
		foreach (self::ITEMS as $item => $group)
			$page[$item] = PartGroups::group($event, $group);
		$page['item_body'] = array('subject' => PartGroups::group($event, ModeratedTopicAssembling::PART_BODY_SUBJECT), 'info' => PartGroups::group($event, ModeratedTopicAssembling::PART_BODY_INFO));
		$page['item_style'] = $event->style();
		$page['item_count'] = $event->itemCount();
		$page['fld_count'] = $event->fieldCount();
		$page['start_from'] = $event->number() - $event->itemCount() - ($event->stage() === ModeratedTopicAssembling::START ? 1 : 0);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = ForumPage::all();
		foreach (self::ITEMS as $item => $group)
			PartGroups::replace($event, $group, $page[$item] ?? null);

		$body = is_array($page['item_body'] ?? null) ? $page['item_body'] : array();
		PartGroups::replace($event, ModeratedTopicAssembling::PART_BODY_SUBJECT, $body['subject'] ?? null);
		PartGroups::replace($event, ModeratedTopicAssembling::PART_BODY_INFO, $body['info'] ?? null);

		$event->setStyle(Markers::markup($page['item_style'] ?? ''));
		$event->count((int) Markers::markup($page['item_count'] ?? $event->itemCount()), (int) Markers::markup($page['fld_count'] ?? $event->fieldCount()));
	}
}
