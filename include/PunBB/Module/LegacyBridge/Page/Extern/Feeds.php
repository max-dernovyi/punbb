<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Api\Data\FeedEntryInterface;
use PunBB\Module\Extern\Api\Data\FeedItemInterface;
use PunBB\Module\Extern\Event\FeedAssembling;
use PunBB\Module\Extern\Event\FeedRendering;
use PunBB\Module\Extern\Model\FeedItem;
use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * A feed as extern.php held it for extension code: $feed with its title, link,
 * description, type and items, each item an array of its own, and the row of
 * the post or topic an item was built from.
 */
final class Feeds {
	/** @return array<string, mixed> */
	public static function feed(FeedAssembling|FeedRendering $feed): array {
		return array(
			'title'			=> $feed->title(),
			'link'			=> $feed->link(),
			'description'	=> $feed->description(),
			'items'			=> array_map(self::item(...), $feed->items()),
			'type'			=> $feed->kind(),
		);
	}

	/** @return array<string, mixed> */
	public static function item(FeedItemInterface $item): array {
		$author = array('name' => $item->authorName());

		if ($item->authorEmail() !== null)
			$author['email'] = $item->authorEmail();

		if ($item->authorUri() !== null)
			$author['uri'] = $item->authorUri();

		return array(
			'id'			=> $item->id(),
			'title'			=> $item->title(),
			'link'			=> $item->link(),
			'description'	=> $item->description(),
			'author'		=> $author,
			'pubdate'		=> $item->published(),
		);
	}

	/**
	 * A post or a topic as the query of a feed of $kind returned it.
	 *
	 * @return array<string, mixed>
	 */
	public static function row(FeedEntryInterface $entry, string $kind): array {
		$row = $kind === FeedAssembling::TOPICS
			? array('id' => $entry->id(), 'poster' => $entry->poster(), 'posted' => $entry->posted(), 'subject' => $entry->subject(), 'message' => $entry->message(), 'hide_smilies' => $entry->hidesSmilies() ? 1 : 0)
			: array('id' => $entry->id(), 'poster' => $entry->poster(), 'message' => $entry->message(), 'hide_smilies' => $entry->hidesSmilies() ? 1 : 0, 'posted' => $entry->posted(), 'poster_id' => $entry->posterId());

		return $row + array(
			'email_setting'	=> $entry->showsEmail() ? 0 : 1,
			'email'			=> $entry->accountEmail(),
			'poster_id'		=> $entry->posterId(),
			'poster_email'	=> $entry->guestEmail() !== '' ? $entry->guestEmail() : null,
		);
	}

	/** The feed as extension code left it in $feed. */
	public static function readBack(mixed $feed, FeedAssembling $event): void {
		if (!is_array($feed))
			return;

		$event->setTitle(Markers::markup($feed['title'] ?? ''));
		$event->setLink(Markers::markup($feed['link'] ?? ''));
		$event->setDescription(Markers::markup($feed['description'] ?? ''));

		$items = array();
		foreach (is_array($feed['items'] ?? null) ? $feed['items'] : array() as $item)
		{
			if (!is_array($item))
				continue;

			$author = is_array($item['author'] ?? null) ? $item['author'] : array();

			$items[] = new FeedItem(
				(int) Markers::markup($item['id'] ?? 0),
				Markers::markup($item['title'] ?? ''),
				Markers::markup($item['link'] ?? ''),
				Markers::markup($item['description'] ?? ''),
				Markers::markup($author['name'] ?? ''),
				isset($author['email']) ? Markers::markup($author['email']) : null,
				isset($author['uri']) ? Markers::markup($author['uri']) : null,
				(int) Markers::markup($item['pubdate'] ?? 0)
			);
		}

		$event->replaceItems($items);
	}
}
