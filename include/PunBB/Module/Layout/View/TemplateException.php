<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\View;

use LogicException;

/** A template cannot be rendered with what it was given. */
final class TemplateException extends LogicException {}
