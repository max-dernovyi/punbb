<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\RedirectJsonSending;

/**
 * Runs fn_redirect_pre_send_json, which redirect() and csrf_confirm_form()
 * share, with the reply as $json_data; what the reply carries is read back.
 */
final class RedirectJsonObserver {
	public function __construct(private readonly PageScope $scope, private readonly ShownRedirect $shown) {}

	public function observe(RedirectJsonSending $event): void {
		$json_data = array('code' => $event->code(), 'message' => $event->message());
		$locals = array('json_data' => &$json_data);

		$destination_url = $event->destination();
		$message = $this->shown->message;

		if ($destination_url !== null)
		{
			$json_data['destination_url'] = $destination_url;
			$locals += array('destination_url' => &$destination_url, 'message' => &$message);
		}
		else
		{
			$json_data += array('csrf_token' => $event->token(), 'prev_url' => $event->previousUrl());
			foreach ($event->names() as $name)
				$json_data['post_data'][$name] = $event->entry($name);
		}

		$this->scope->observe('fn_redirect_pre_send_json', $event, $locals);

		$event->change((int) Markers::markup(self::field($json_data, 'code', $event->code())), Markers::markup(self::field($json_data, 'message', $event->message())));

		if ($event->destination() !== null)
		{
			$event->changeDestination(Markers::markup(self::field($json_data, 'destination_url', $event->destination())));
			return;
		}

		$event->changeConfirmation(Markers::markup(self::field($json_data, 'csrf_token', $event->token())), Markers::markup(self::field($json_data, 'prev_url', $event->previousUrl())));

		$fields = Markers::entries(self::field($json_data, 'post_data', null));
		foreach ($event->names() as $name)
			if (!isset($fields[$name]))
				$event->remove($name);

		foreach ($fields as $name => $value)
			$event->set((string) $name, $value);
	}

	/** $key of the reply the hook left, which may be anything; $default when it is not there. */
	private static function field(mixed $reply, string $key, mixed $default): mixed {
		return is_array($reply) ? ($reply[$key] ?? $default) : $default;
	}
}
