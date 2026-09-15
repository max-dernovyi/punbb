<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A search found nothing, before the message saying so is shown: an observer
 * may change the link offering a new search.
 */
final class NoResultsShowing implements EventInterface {
	/**
	 * @param ?string $action the quick search that found nothing; null for a keyword or author search
	 * @param string $searchAgain markup: the link to the search form
	 */
	public function __construct(private readonly ?string $action, private string $searchAgain) {}

	public function action(): ?string {
		return $this->action;
	}

	public function searchAgain(): string {
		return $this->searchAgain;
	}

	public function setSearchAgain(string $markup): void {
		$this->searchAgain = $markup;
	}
}
