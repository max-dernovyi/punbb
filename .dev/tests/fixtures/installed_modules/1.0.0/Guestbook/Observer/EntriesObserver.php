<?php

declare(strict_types=1);

namespace PunBBModule\Guestbook\Observer;

use PunBB\Module\Index\Event\IndexRendering;
use PunBBModule\Guestbook\Model\Entries;

final class EntriesObserver {
	public function __construct(private readonly Entries $entries) {}

	public function observe(IndexRendering $event): void {
		if ($event->position() === IndexRendering::INFO_END)
			$event->append('<p id="guestbook">Guestbook entries: '.$this->entries->count().'</p>');
	}
}
