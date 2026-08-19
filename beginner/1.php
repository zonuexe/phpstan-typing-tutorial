<?php declare(strict_types = 1);

use function PHPStan\dumpType;
use function PHPStan\Testing\assertType;

$a = 'foo';
$b = 'bar';
$c = $a . $b;

\PHPStan\dumpType($a);
\PHPStan\dumpType($b);
\PHPStan\dumpType($c);

$n = 5;
$m = 2;
$l = $n / $m;
\PHPStan\dumpType(compact('n', 'm', 'l'));
