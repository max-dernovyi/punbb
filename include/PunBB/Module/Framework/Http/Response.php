<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Http;

/**
 * What a controller answers with. Nothing reaches the client until the front
 * controller sends it, so a controller returns instead of exiting.
 */
final readonly class Response {
	/** @param array<string, string> $headers name => value */
	public function __construct(
		public string $body,
		public int $status = 200,
		public array $headers = array()
	) {}

	public function send(): void {
		$this->sendHead();

		echo $this->body;
	}

	/**
	 * The status and the headers, for a caller that sends the body itself. Once
	 * output has reached the client they can no longer be sent, and are not.
	 */
	public function sendHead(): void {
		if (headers_sent())
			return;

		http_response_code($this->status);

		foreach ($this->headers as $name => $value)
			header($name.': '.$value);
	}
}
