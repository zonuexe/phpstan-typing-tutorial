<?php declare(strict_types = 1);

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
