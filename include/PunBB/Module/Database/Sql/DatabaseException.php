<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql;

use LogicException;

/** A statement or a row was used in a way its code got wrong. */
final class DatabaseException extends LogicException {}
