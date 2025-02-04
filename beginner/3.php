<?php declare(strict_types = 1);

final readonly class Author {
	/**
	 * @param non-empty-string $name
	 */
	public function __construct(
		public string $name,
	) {}
}

final readonly class Book {
	/**
	 * @param non-empty-string $title
	 * @param non-empty-array<Author> $authors
	 */
	public function __construct(
		public string $title,
		public array $authors,
	) {}
}

/**
 * @param non-empty-string $word
 * @param 'asc'|'desc' $order
 * @param positive-int $page
 * @return list<Book>
 */
function search(string $word, string $order, int $page): array
{
	// 本来は検索エンジンからデータを取得する
	return match ($page) {
		1 => [new Book('', [])],
		default => [],
	};
}

$word = filter_var($_GET['word'] ?? '');
$order = filter_var($_GET['order'] ?? 'asc');
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

\PHPStan\dumpType(compact('word', 'order', 'page'));

$books = search($word, $order, $page);

\PHPStan\dumpType(compact('books'));
