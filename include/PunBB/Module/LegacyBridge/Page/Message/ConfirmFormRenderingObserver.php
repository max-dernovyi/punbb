<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\ConfirmFormRendering;

/**
 * Renders fn_csrf_confirm_form_pre_header_load before the confirmation form,
 * with its action and hidden fields in $forum_page, and fn_csrf_confirm_form_end after it.
 */
final class ConfirmFormRenderingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ConfirmFormRendering $event): void {
		if ($event->position() === ConfirmFormRendering::END)
		{
			$event->append($this->scope->renderObserved('fn_csrf_confirm_form_end', $event));
			return;
		}

		if (!LegacyScope::attached('fn_csrf_confirm_form_pre_header_load'))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['form_action'] = $event->action();
		$page['hidden_fields'] = array();
		foreach ($event->names() as $name)
			$page['hidden_fields'][$name] = (string) $event->entry($name);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved('fn_csrf_confirm_form_pre_header_load', $event));

		$fields = Markers::entries(is_array($GLOBALS['forum_page'] ?? null) ? ($GLOBALS['forum_page']['hidden_fields'] ?? null) : null);

		foreach ($event->names() as $name)
			if (!isset($fields[$name]))
				$event->remove($name);

		foreach ($fields as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
