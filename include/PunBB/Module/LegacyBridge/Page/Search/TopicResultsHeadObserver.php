<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\TopicResultsHeadAssembling;

/**
 * Renders se_results_topics_pre_item_header_output with the summary's labels
 * in $forum_page['item_header'], which is read back.
 */
final class TopicResultsHeadObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(TopicResultsHeadAssembling $event): void {
		if (!LegacyScope::attached('se_results_topics_pre_item_header_output'))
			return;

		ForumPage::set('item_header', array(
			'subject'	=> SearchParts::group($event, TopicResultsHeadAssembling::SUBJECT),
			'info'		=> SearchParts::group($event, TopicResultsHeadAssembling::INFO),
		));

		$event->append($this->scope->renderObserved('se_results_topics_pre_item_header_output', $event));

		$header = ForumPage::get('item_header');
		$header = is_array($header) ? $header : array();
		SearchParts::replace($event, TopicResultsHeadAssembling::SUBJECT, $header['subject'] ?? null);
		SearchParts::replace($event, TopicResultsHeadAssembling::INFO, $header['info'] ?? null);
	}
}
