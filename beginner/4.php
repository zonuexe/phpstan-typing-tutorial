<?php declare(strict_types = 0);

use function PHPStan\dumpType;
use function PHPStan\Testing\assertType;

/**
 * $s が数値文字列だったら int に変換して返す
 */
function to_int(string $s): ?int
{
	try {
		return $s;
	} catch (TypeError) {
		return null;
	}
}

\PHPStan\Testing\assertType('int', to_int('1'));
\PHPStan\Testing\assertType('int', to_int('1.1'));
\PHPStan\Testing\assertType('null', to_int('php'));
\PHPStan\Testing\assertType('int|null', to_int(random_bytes(1)));
