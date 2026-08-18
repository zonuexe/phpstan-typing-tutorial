<?php declare(strict_types = 1);

function label(string $title): string
{
	return "label:{$title}";
}

\PHPStan\Testing\assertType('string', label('foo'));
