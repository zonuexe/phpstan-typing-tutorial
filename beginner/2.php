<?php declare(strict_types = 1);

use function PHPStan\dumpType;
use function PHPStan\Testing\assertType;

function label($title)
{
	return "label:{$title}";
}

\PHPStan\Testing\assertType('string', label('foo'));
