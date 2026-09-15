<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Misc\Api\Data\MailSentInterface;
use PunBB\Module\Misc\Api\Data\RecipientInterface;
use PunBB\Module\Misc\Api\RecipientsInterface;

final class RecipientsInterceptor implements RecipientsInterface {
	public function __construct(private readonly RecipientsInterface $subject, private readonly PluginChain $plugins) {}

	public function find(int $userId): ?RecipientInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function recordMailSent(MailSentInterface ...$sent): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->recordMailSent(...));
	}
}
