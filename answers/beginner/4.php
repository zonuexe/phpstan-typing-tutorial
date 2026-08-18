<?php declare(strict_types = 1);

use function PHPStan\dumpType;
use function PHPStan\Testing\assertType;

/**
 * $s が数値文字列だったら int に変換して返す
 *
 * @return ($s is numeric-string ? int : null)
 */
function to_int(string $s): ?int
{
	$int = filter_var($s, FILTER_VALIDATE_INT);
	if ($int !== false) {
		return $int;
	}

	$float = filter_var($s, FILTER_VALIDATE_FLOAT);
	if ($float !== false) {
		return (int)$float;
	}

	return null;
}

\PHPStan\Testing\assertType('int', to_int('1'));
\PHPStan\Testing\assertType('int', to_int('1.1'));
\PHPStan\Testing\assertType('null', to_int('php'));
\PHPStan\Testing\assertType('int|null', to_int(random_bytes(1)));
