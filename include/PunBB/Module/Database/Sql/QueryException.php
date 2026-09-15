<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Sql;

use RuntimeException;

/**
 * A statement the database refused. It carries the driver's message and code,
 * the statement, and the call site outside this module that ran it.
 */
final class QueryException extends RuntimeException {
	private string $sql = '';

	private string $callFile = '';

	private int $callLine = 0;

	public static function refused(string $message, int $code, string $sql): self {
		$exception = new self($message, $code);
		$exception->sql = $sql;

		return $exception;
	}

	public function sql(): string {
		return $this->sql;
	}

	public function callFile(): string {
		return $this->callFile;
	}

	public function callLine(): int {
		return $this->callLine;
	}

	/** The same failure, placed at the frame that ran the statement. */
	public function at(string $file, int $line): self {
		$exception = self::refused($this->getMessage(), $this->getCode(), $this->sql);
		$exception->callFile = $file;
		$exception->callLine = $line;

		return $exception;
	}
}
