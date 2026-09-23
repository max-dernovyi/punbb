<?php

declare(strict_types=1);

namespace PunBBModule\Autograph\Observer;

use PunBB\Module\Index\Event\IndexRendering;
use PunBBModule\Guestbook\Model\Entries;

final class SignatureObserver {
	public function __construct(private readonly Entries $entries) {}

	public function observe(IndexRendering $event): void {
		if ($event->position() === IndexRendering::INFO_END)
			$event->append('<p id="autograph">Signed below '.$this->entries->count().' entries</p>');
	}
}
