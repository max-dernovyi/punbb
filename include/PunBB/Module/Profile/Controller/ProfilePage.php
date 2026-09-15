<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Controller;

use Closure;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Event\ProfileRendering;
use PunBB\Module\Profile\View\FormView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * What the pages of a profile share: who may change a profile, reading the
 * request as profile.php read it, and building a page position by position.
 */
final class ProfilePage {
	/**
	 * A page of the profile, placed position by position: its start with the
	 * named markup it carries, what $fill numbers and places, and its end.
	 *
	 * @param array<string, Parts> $startParts group => the named markup the start carries
	 * @param array<string, mixed> $values what the template shows
	 * @param Closure(FormView, Closure(string, array<string, Parts>=): ProfileRendering, ProfileRendering): void $fill
	 */
	public static function render(EventDispatcher $events, TemplateRenderer $templates, string $page, string $template, ProfileUserInterface $user, array $startParts, array $values, Closure $fill): Html {
		$view = new FormView(ProfileRendering::PAGES[$page], $values);

		$at = function (string $position, array $parts = array()) use ($events, $page, $user, $view): ProfileRendering {
			/** @var array<string, Parts> $parts */
			[$groups, $items, $fields] = $view->counts();

			$event = new ProfileRendering($page, $position, $user, $groups, $items, $fields, $parts);
			$events->dispatch($event);
			$view->place($event);

			return $event;
		};

		$start = $at(ProfileRendering::OUTPUT_START, $startParts);
		$fill($view, $at, $start);

		$body = $templates->render($template, $view->variables());

		$end = $at(ProfileRendering::END);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/** The named markup of $group at $event, joined with $glue. */
	public static function joined(ProfileRendering $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	/** Whether $group at $event carries any named markup. */
	public static function carries(ProfileRendering $event, string $group): bool {
		return $event->names($group) !== array();
	}

	/**
	 * Each error as a line of the list stopping a form.
	 *
	 * @param list<string> $errors
	 */
	public static function errorParts(array $errors): Parts {
		$parts = new Parts();
		foreach ($errors as $index => $error)
			$parts->set((string) $index, '<li class="warn"><span>'.$error.'</span></li>');

		return $parts;
	}

	/**
	 * The hidden fields every section's form carries.
	 *
	 * @param array<string, string> $more fields after the token
	 */
	public static function hiddenFields(string $token, array $more = array()): Parts {
		return new Parts(array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $token)->html,
		) + $more);
	}

	/**
	 * Whether the visitor may change the profile of $user: their own, any as
	 * an administrator, and a member's who neither administers nor moderates as
	 * a moderator allowed to edit users.
	 */
	public static function editable(VisitorInterface $visitor, ProfileUserInterface $user): bool {
		return $visitor->id() === $user->id()
			|| $visitor->isAdministrator()
			|| ($visitor->can(GroupPermission::Moderate) && $visitor->can(GroupPermission::EditUsers) && !$user->isAdministrator() && !$user->moderates());
	}

	/**
	 * The crumbs a page of a profile opens with: the board, and the member's profile at $link.
	 *
	 * @return list<Crumb>
	 */
	public static function crumbs(SettingsInterface $settings, UrlsInterface $urls, ProfileUserInterface $user, Html $usersProfile, string $link = 'user'): array {
		return array(
			new Crumb($settings->value('o_board_title'), $urls->link('index')),
			new Crumb(sprintf($usersProfile->html, $user->username()), $urls->link($link, array($user->id()))),
		);
	}

	/** A value the request carries as text; null when it carries none, '' for anything that is not text. */
	public static function submitted(mixed $value): ?string {
		return $value !== null ? (is_string($value) ? $value : '') : null;
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
