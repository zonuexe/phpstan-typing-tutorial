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
