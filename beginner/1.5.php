<?php declare(strict_types = 1);

use function PHPStan\dumpType;
use function PHPStan\Testing\assertType;

$n = 5;
$n = $n + 1;
\PHPStan\dumpType($n);

$count = 0;
foreach (['a', 'b', 'c'] as $s) {
	$count++;
}
\PHPStan\dumpType($count);

$total = 0;
foreach ($_GET as $value) {
	$total++;
}
\PHPStan\dumpType($total);

$r = rand();
\PHPStan\dumpType($r);
\PHPStan\dumpType($r + 1);
