<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Plugin;

use LogicException;

/** A plugin broke its contract while a call ran through it. */
final class PluginException extends LogicException {}
