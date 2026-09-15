<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The visitor as include/common.php left them in $forum_user, read when asked.
 */
final class LegacyVisitor implements VisitorInterface {
	public function id(): int {
		return (int) Markers::markup($this->user('id'));
	}

	public function username(): string {
		return Markers::markup($this->user('username'));
	}

	public function groupId(): int {
		return (int) Markers::markup($this->user('g_id'));
	}

	public function isGuest(): bool {
		return (bool) $this->user('is_guest');
	}

	public function isAdministrator(): bool {
		return $this->groupId() === (int) Markers::markup(\FORUM_ADMIN);
	}

	public function isModerating(): bool {
		return (bool) $this->user('is_admmod');
	}

	public function can(GroupPermission $permission): bool {
		return Markers::markup($this->user($permission->value)) === '1';
	}

	public function language(): string {
		return Markers::markup($this->user('language'));
	}

	public function style(): string {
		return Markers::markup($this->user('style'));
	}

	public function lastVisit(): int {
		return (int) Markers::markup($this->user('last_visit'));
	}

	/** Read through get_tracked_topics(), so its point still runs, and left in $tracked_topics where the page scripts kept it. */
	public function trackedTopics(): TrackedTopics {
		$tracked = \get_tracked_topics();
		$GLOBALS['tracked_topics'] = $tracked;

		return is_array($tracked) ? new TrackedTopics(self::moments($tracked['topics'] ?? null), self::moments($tracked['forums'] ?? null)) : new TrackedTopics();
	}

	public function previousUrl(): string {
		return Markers::markup($this->user('prev_url'));
	}

	/** As include/common.php left it: the visitor's own, or the board's default. */
	public function topicsPerPage(): int {
		return (int) Markers::markup($this->user('disp_topics'));
	}

	public function postsPerPage(): int {
		return (int) Markers::markup($this->user('disp_posts'));
	}

	public function showsAvatars(): bool {
		return Markers::markup($this->user('show_avatars')) !== '0';
	}

	public function showsSignatures(): bool {
		return Markers::markup($this->user('show_sig')) !== '0';
	}

	public function subscribesOnReply(): bool {
		return Markers::markup($this->user('auto_notify')) === '1';
	}

	/** Through get_tracked_topics() and set_tracked_topics(), so their points still run; left in $tracked_topics. */
	public function readTopic(int $topicId, int $at): void {
		$tracked = \get_tracked_topics();
		$tracked = is_array($tracked) ? $tracked : array();
		$topics = is_array($tracked['topics'] ?? null) ? $tracked['topics'] : array();
		$topics[$topicId] = $at;
		$tracked['topics'] = $topics;

		\set_tracked_topics($tracked);
		$GLOBALS['tracked_topics'] = $tracked;
	}

	/** Through get_tracked_topics() and set_tracked_topics(), so their points still run; left in $tracked_topics. */
	public function readForum(int $forumId, int $at): void {
		$tracked = \get_tracked_topics();
		$tracked = is_array($tracked) ? $tracked : array();
		$forums = is_array($tracked['forums'] ?? null) ? $tracked['forums'] : array();
		$forums[$forumId] = $at;
		$tracked['forums'] = $forums;

		\set_tracked_topics($tracked);
		$GLOBALS['tracked_topics'] = $tracked;
	}

	/** Through set_tracked_topics(), so its point still runs. */
	public function forgetTrackedTopics(): void {
		\set_tracked_topics(null);
	}

	/** Through get_remote_address(), so its point still runs. */
	public function address(): string {
		return Markers::markup(\get_remote_address());
	}

	public function loggedAt(): ?int {
		$logged = $this->user('logged');

		return $logged !== null && $logged !== '' ? (int) Markers::markup($logged) : null;
	}

	public function email(): string {
		return Markers::markup($this->user('email'));
	}

	public function lastPostAt(): ?int {
		$posted = $this->user('last_post');

		return $posted !== null && $posted !== '' ? (int) Markers::markup($posted) : null;
	}

	public function postFloodInterval(): int {
		return (int) Markers::markup($this->user('g_post_flood'));
	}

	public function lastEmailSentAt(): ?int {
		$sent = $this->user('last_email_sent');

		return $sent !== null && $sent !== '' ? (int) Markers::markup($sent) : null;
	}

	public function emailFloodInterval(): int {
		return (int) Markers::markup($this->user('g_email_flood'));
	}

	public function lastSearchAt(): ?int {
		$searched = $this->user('last_search');

		return $searched !== null && $searched !== '' ? (int) Markers::markup($searched) : null;
	}

	public function searchFloodInterval(): int {
		return (int) Markers::markup($this->user('g_search_flood'));
	}

	/** @return array<int, int> */
	private static function moments(mixed $tracked): array {
		$moments = array();
		foreach (is_array($tracked) ? $tracked : array() as $id => $moment)
			$moments[(int) $id] = (int) Markers::markup($moment);

		return $moments;
	}

	private function user(string $key): mixed {
		$user = $GLOBALS['forum_user'] ?? null;

		return is_array($user) ? ($user[$key] ?? null) : null;
	}
}
