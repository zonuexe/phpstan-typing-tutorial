<?php declare(strict_types = 1);

function label($title)
{
	return "label:{$title}";
}

\PHPStan\Testing\assertType('string', label('foo'));
