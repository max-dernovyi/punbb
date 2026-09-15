<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Controller;

use Closure;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Moderate\Event\ModerationFormRendering;
use PunBB\Module\Moderate\View\FormView;
use PunBB\Module\Moderate\View\Paging;
use PunBB\Module\Site\Url\UrlsInterface;

/**
 * What the moderation of posts and of topics share: reading the request as
 * moderate.php read it, and building a form confirming a change.
 */
final class Moderation {
	/**
	 * A form confirming a change of posts or topics, placed position by position:
	 * its start, what $fill numbers and places, and its end.
	 *
	 * @param list<int> $ids the posts or topics changed
	 * @param array<string, string> $hiddenFields
	 * @param array<string, mixed> $values what the template shows
	 * @param Closure(FormView, Closure(string): Html): void $fill
	 */
	public static function form(EventDispatcher $events, TemplateRenderer $templates, string $form, string $template, int $forumId, array $ids, array $hiddenFields, array $values, Closure $fill): Html {
		$view = new FormView(ModerationFormRendering::POSITIONS[$form], $values);

		$at = function (string $position) use ($events, $form, $forumId, $ids, $hiddenFields, $view): Html {
			[$groups, $items, $fields] = $view->counts();

			$start = $position === ModerationFormRendering::OUTPUT_START;
			$event = new ModerationFormRendering($form, $position, $forumId, $ids, $start ? $hiddenFields : array(), $groups, $items, $fields);
			$events->dispatch($event);

			if ($start)
				$view->show('hiddenFields', new Html(implode("\n\t\t\t\t", array_map(static fn (string $name): string => (string) $event->entry($name), $event->names()))));

			return $view->place($event);
		};

		$start = $at(ModerationFormRendering::OUTPUT_START);
		$fill($view, $at);
		$body = $templates->render($template, $view->variables());
		$end = $at(ModerationFormRendering::END);

		return (new Html($start->html.$body.$end->html))->trim();
	}

	/**
	 * The head's links to the first, previous, next and last pages of a listing at $name.
	 *
	 * @param list<int|string> $arguments
	 * @return array<string, Html>
	 */
	public static function navigation(UrlsInterface $urls, string $name, array $arguments, Paging $paging, Html $page): array {
		$navigation = array();

		if ($paging->page < $paging->pages)
		{
			$navigation['last'] = Html::format('<link rel="last" href="%s" title="%s %s" />', $urls->sublink($name, 'page', $paging->pages, $arguments), $page, $paging->pages);
			$navigation['next'] = Html::format('<link rel="next" href="%s" title="%s %s" />', $urls->sublink($name, 'page', $paging->page + 1, $arguments), $page, $paging->page + 1);
		}

		if ($paging->page > 1)
		{
			$navigation['prev'] = Html::format('<link rel="prev" href="%s" title="%s %s" />', $urls->sublink($name, 'page', $paging->page - 1, $arguments), $page, $paging->page - 1);
			$navigation['first'] = Html::format('<link rel="first" href="%s" title="%s 1" />', $urls->link($name, $arguments), $page);
		}

		return $navigation;
	}

	/** The parts joined with spaces; null when there are none. */
	public static function joined(Parts $parts): ?Html {
		return !$parts->isEmpty() ? new Html($parts->join(' ')) : null;
	}

	/**
	 * The posts or topics a form selects: a list of checkboxes, or the ids a
	 * confirmation carries on; none when it selects nothing.
	 *
	 * @return list<int>
	 */
	public static function ids(mixed $selected): array {
		if ($selected === null || $selected === '' || $selected === '0' || $selected === array())
			return array();

		return array_map(self::integer(...), is_array($selected) ? array_values($selected) : explode(',', is_scalar($selected) ? (string) $selected : ''));
	}

	/** Whether a name or subject read counts as found, as the page script's truth test took it. */
	public static function found(?string $text): bool {
		return $text !== null && $text !== '' && $text !== '0';
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	public static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** A value the request carries, as intval() took it. */
	public static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** @param array<string, Html> $strings */
	public static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
