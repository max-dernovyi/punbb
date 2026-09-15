<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use InvalidArgumentException;
use PunBB\Module\Users\Api\Data\SearchFieldInterface;
use PunBB\Module\Users\Api\Data\UserSearchInterface;

final readonly class UserSearch implements UserSearchInterface {
	/** The criteria of the form after its text fields, in the order a link carries them. */
	private const DATES = array('last_post_after', 'last_post_before', 'registered_after', 'registered_before');

	/**
	 * @param list<SearchFieldInterface> $fields
	 * @param list<string> $criteria each criterion as a link carries it, encoded for an attribute
	 */
	private function __construct(
		private array $fields,
		private ?int $postsMoreThan,
		private ?int $postsLessThan,
		private ?int $lastPostAfter,
		private ?int $lastPostBefore,
		private ?int $registeredAfter,
		private ?int $registeredBefore,
		private int $groupId,
		private string $orderBy,
		private bool $descending,
		private array $criteria
	) {
		if (!in_array($orderBy, self::ORDERS, true))
			throw new InvalidArgumentException(sprintf('Users cannot be ordered by "%s"', $orderBy));
	}

	/**
	 * The order a search request asks for, as the column and whether it
	 * descends; null when it names no order the results take.
	 *
	 * @param array<mixed> $query
	 * @return array{string, bool}|null
	 */
	public static function orderOf(array $query): ?array {
		$orderBy = self::text($query['order_by'] ?? null);
		$direction = self::text($query['direction'] ?? null);

		if (!in_array($orderBy, self::ORDERS, true) || !in_array($direction, array('ASC', 'DESC'), true))
			return null;

		return array($orderBy, $direction === 'DESC');
	}

	/**
	 * What is wrong with the criteria of a search request, as the key of its
	 * message in the admin_users pack; null when nothing is.
	 *
	 * @param array<mixed> $query
	 */
	public static function refusal(array $query): ?string {
		$more = self::text($query['posts_greater'] ?? null);
		$less = self::text($query['posts_less'] ?? null);

		if (($more !== '' && $more !== '0' || $less !== '' && $less !== '0') && !ctype_digit($more.$less))
			return 'Non numeric value message';

		foreach (self::DATES as $name)
			if (self::text($query[$name] ?? null) !== '' && self::timestamp(self::text($query[$name])) === null)
				return 'Invalid date/time message';

		return self::fromQuery($query, 'username', false)->hasCriteria() ? null : 'No search terms message';
	}

	/**
	 * The search a request asks for, in the order orderOf() read, once
	 * refusal() found nothing wrong with it.
	 *
	 * @param array<mixed> $query
	 */
	public static function fromQuery(array $query, string $orderBy, bool $descending): self {
		$criteria = array('order_by='.$orderBy, 'direction='.($descending ? 'DESC' : 'ASC'));

		$groupId = isset($query['user_group']) ? self::integer($query['user_group']) : -1;
		$criteria[] = 'user_group='.$groupId;

		$dates = array();
		foreach (self::DATES as $name)
		{
			$text = self::text($query[$name] ?? null);
			$dates[$name] = $text !== '' ? self::timestamp($text) : null;

			if ($text !== '')
				$criteria[] = $name.'='.rawurlencode($text);
		}

		$fields = array();
		foreach (is_array($query['form'] ?? null) ? $query['form'] : array() as $field => $text)
		{
			$text = self::text($text);
			if ($text === '' || !in_array($field, SearchFieldInterface::FIELDS, true))
				continue;

			$fields[] = new SearchField((string) $field, $text);
			$criteria[] = 'form%5B'.$field.'%5D='.urlencode($text);
		}

		$more = self::text($query['posts_greater'] ?? null);
		$less = self::text($query['posts_less'] ?? null);

		if ($more !== '')
			$criteria[] = 'posts_greater='.$more;

		if ($less !== '')
			$criteria[] = 'posts_less='.$less;

		return new self($fields, $more !== '' ? (int) $more : null, $less !== '' ? (int) $less : null,
			$dates['last_post_after'], $dates['last_post_before'], $dates['registered_after'], $dates['registered_before'],
			$groupId, $orderBy, $descending, $criteria);
	}

	public function fields(): array {
		return $this->fields;
	}

	public function postsMoreThan(): ?int {
		return $this->postsMoreThan;
	}

	public function postsLessThan(): ?int {
		return $this->postsLessThan;
	}

	public function lastPostAfter(): ?int {
		return $this->lastPostAfter;
	}

	public function lastPostBefore(): ?int {
		return $this->lastPostBefore;
	}

	public function registeredAfter(): ?int {
		return $this->registeredAfter;
	}

	public function registeredBefore(): ?int {
		return $this->registeredBefore;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function orderBy(): string {
		return $this->orderBy;
	}

	public function descending(): bool {
		return $this->descending;
	}

	/** The search as a link carries it, after find_user=, each criterion after &amp;. */
	public function criteria(): string {
		return '&amp;'.implode('&amp;', $this->criteria);
	}

	/** Whether the search asks for anything but an order: it finds every user otherwise. */
	private function hasCriteria(): bool {
		return $this->fields !== array() || $this->postsMoreThan !== null || $this->postsLessThan !== null || $this->lastPostAfter !== null || $this->lastPostBefore !== null
			|| $this->registeredAfter !== null || $this->registeredBefore !== null || $this->groupId > -1;
	}

	private static function timestamp(string $text): ?int {
		$timestamp = strtotime($text);

		return $timestamp === false || $timestamp === -1 ? null : $timestamp;
	}

	/** A value the request carries as text, trimmed as forum_trim() trims; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? preg_replace('/^[ \t\n\r\0\x0B\x{A0}]+|[ \t\n\r\0\x0B\x{A0}]+$/u', '', $value) ?? '' : '';
	}

	/** A value the request carries, as intval() took it. */
	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}
}
