<?php declare(strict_types = 1);

/**
 * チュートリアル本文 (Markdown) と PHP ファイル・PHPStan の実際の出力が矛盾していないか検査する
 *
 * 使い方:
 *
 *     php tools/check-docs.php              # 全ての README.md を検査
 *     php tools/check-docs.php beginner/README.md
 *
 * 詳細は CONTRIBUTING.md を参照。
 */

const PHPSTAN = __DIR__ . '/../vendor/bin/phpstan';
const CONFIG = __DIR__ . '/../phpstan.dist.neon';
const ANSWERS_DIR = __DIR__ . '/../answers';
const DUMP_IDENTIFIERS = ['phpstan.dumpType', 'phpstan.dumpPhpDocType'];
const TAB_WIDTH = 4;

/**
 * 注釈コメントの正規表現
 *
 * 行末:   `\PHPStan\dumpType($x); // DumpedType: int`
 * 行全体: `// Error: Function f() has no return type specified.`
 */
const ANNOTATION_TRAILING = '~^(?<code>.*?\S)\s+(?://|#)\s*(?<kind>DumpedType|Dumped type|Error):\s*(?<value>\S.*?)\s*$~u';
const ANNOTATION_LINE = '~^\s*(?://|#)\s*(?<kind>DumpedType|Dumped type|Error):\s*(?<value>\S.*?)\s*$~u';
const ELLIPSIS_LINE = '~^\s*(?://|#)\s*(?:\.\.\.|…)\s*$~u';

/**
 * @param list<string> $argv
 */
function main(array $argv): int
{
	$root = realpath(__DIR__ . '/..');
	assert(is_string($root));

	$targets = array_slice($argv, 1);
	if ($targets === []) {
		$targets = array_map(
			static fn (string $path): string => substr($path, strlen($root) + 1),
			array_merge(glob($root . '/*/README.md') ?: []),
		);
		sort($targets);
	}

	$reporter = new Reporter();

	foreach ($targets as $target) {
		checkMarkdown($root, $target, $reporter);
	}

	checkAnswers($root, $reporter);

	return $reporter->finish();
}

final class Reporter
{
	/** @var list<string> */
	private array $failures = [];
	private int $checks = 0;

	public function ok(string $message): void
	{
		$this->checks++;
		fwrite(STDOUT, "  ✔ {$message}\n");
	}

	public function fail(string $message): void
	{
		$this->checks++;
		$this->failures[] = $message;
		fwrite(STDOUT, "  ✘ {$message}\n");
	}

	public function note(string $message): void
	{
		fwrite(STDOUT, "  · {$message}\n");
	}

	public function section(string $title): void
	{
		fwrite(STDOUT, "\n{$title}\n");
	}

	public function finish(): int
	{
		$failed = count($this->failures);
		fwrite(STDOUT, "\n");
		if ($failed === 0) {
			fwrite(STDOUT, "[OK] {$this->checks} checks passed\n");
			return 0;
		}

		fwrite(STDOUT, "[ERROR] {$failed} of {$this->checks} checks failed:\n");
		foreach ($this->failures as $failure) {
			fwrite(STDOUT, "  - {$failure}\n");
		}

		return 1;
	}
}

/**
 * @phpstan-type Block array{start: int, info: string, attrs: array<string, string>, lines: list<string>}
 */
final class Markdown
{
	/**
	 * Markdown 中の ```php ... ``` ブロックを抜き出す
	 *
	 * 行頭 (0〜3スペース) から始まるフェンスのみ対象とし、`>` 引用中のフェンスは無視する
	 *
	 * @return list<Block>
	 */
	public static function codeBlocks(string $markdown): array
	{
		$blocks = [];
		$lines = preg_split('/\R/u', $markdown) ?: [];
		$fence = null;
		$current = null;

		foreach ($lines as $i => $line) {
			if ($fence === null) {
				if (preg_match('/^ {0,3}(`{3,}|~{3,})\s*(.*)$/u', $line, $m) === 1) {
					$fence = $m[1];
					$info = trim($m[2]);
					$words = preg_split('/\s+/', $info, -1, PREG_SPLIT_NO_EMPTY) ?: [];
					$lang = array_shift($words) ?? '';
					$attrs = [];
					foreach ($words as $word) {
						[$key, $value] = array_pad(explode('=', $word, 2), 2, '');
						$attrs[$key] = $value;
					}
					$current = ['start' => $i + 1, 'info' => $lang, 'attrs' => $attrs, 'lines' => []];
				}
				continue;
			}

			if (preg_match('/^ {0,3}' . preg_quote($fence, '/') . '\s*$/u', $line) === 1) {
				assert($current !== null);
				$blocks[] = $current;
				$fence = null;
				$current = null;
				continue;
			}

			assert($current !== null);
			$current['lines'][] = $line;
		}

		return $blocks;
	}
}

/**
 * コードブロックから注釈を分離した結果
 *
 * @phpstan-type Expectation array{dumps: list<string>, errors: list<string>}
 */
final class Snippet
{
	/**
	 * @param list<string> $code       注釈を取り除いたコード行
	 * @param array<int, Expectation> $expectations  $code の行番号(0始まり) => 期待する出力
	 * @param array<int, int> $sourceLines  $code の行番号(0始まり) => Markdown の行番号(1始まり)
	 * @param list<int> $ellipses     $code の中で「ここに任意の行が入る」ことを示す位置 ($code の行番号(0始まり)の直前)
	 */
	public function __construct(
		public array $code,
		public array $expectations,
		public array $sourceLines,
		public array $ellipses,
	) {
	}

	/**
	 * @param list<string> $lines
	 */
	public static function parse(array $lines, int $markdownStart, bool $allowEllipsis): self
	{
		$code = [];
		$expectations = [];
		$sourceLines = [];
		$ellipses = [];
		/** @var Expectation $pending 次のコード行に適用する注釈 */
		$pending = ['dumps' => [], 'errors' => []];

		foreach ($lines as $offset => $line) {
			if ($allowEllipsis && preg_match(ELLIPSIS_LINE, $line) === 1) {
				$ellipses[] = count($code);
				continue;
			}

			if (preg_match(ANNOTATION_LINE, $line, $m) === 1) {
				self::push($pending, $m['kind'], $m['value']);
				continue;
			}

			$expectation = $pending;
			$pending = ['dumps' => [], 'errors' => []];

			if (preg_match(ANNOTATION_TRAILING, $line, $m) === 1) {
				self::push($expectation, $m['kind'], $m['value']);
				$line = $m['code'];
			}

			$index = count($code);
			$code[] = $line;
			$sourceLines[$index] = $markdownStart + 1 + $offset;
			if ($expectation['dumps'] !== [] || $expectation['errors'] !== []) {
				$expectations[$index] = $expectation;
			}
		}

		return new self($code, $expectations, $sourceLines, $ellipses);
	}

	/**
	 * @param Expectation $expectation
	 */
	private static function push(array &$expectation, string $kind, string $value): void
	{
		if ($kind === 'Error') {
			$expectation['errors'][] = $value;
		} else {
			$expectation['dumps'][] = $value;
		}
	}
}

/**
 * @param list<string> $lines
 * @return list<string>
 */
function normalize(array $lines): array
{
	return array_map(
		static fn (string $line): string => rtrim(str_replace("\t", str_repeat(' ', TAB_WIDTH), $line)),
		$lines,
	);
}

/**
 * スニペットの各行がファイルの何行目に対応するかを求める (連続する行として一致しなければ null)
 *
 * @param list<string> $snippet
 * @param list<string> $file
 * @param list<int> $ellipses
 * @return array<int, int>|null  スニペット行番号(0始まり) => ファイル行番号(1始まり)
 */
function locate(array $snippet, array $file, array $ellipses): ?array
{
	// 省略記号でセグメントに分割し、順番に前方一致させる
	$segments = [];
	$prev = 0;
	foreach ($ellipses as $position) {
		$segments[] = [$prev, array_slice($snippet, $prev, $position - $prev)];
		$prev = $position;
	}
	$segments[] = [$prev, array_slice($snippet, $prev)];

	$mapping = [];
	$cursor = 0;
	foreach ($segments as [$offset, $segment]) {
		if ($segment === []) {
			continue;
		}
		$found = null;
		$last = count($file) - count($segment);
		for ($i = $cursor; $i <= $last; $i++) {
			if (array_slice($file, $i, count($segment)) === $segment) {
				$found = $i;
				break;
			}
		}
		if ($found === null) {
			return null;
		}
		foreach ($segment as $j => $_) {
			$mapping[$offset + $j] = $found + $j + 1;
		}
		$cursor = $found + count($segment);
	}

	return $mapping;
}

/**
 * PHPStan を実行し、ファイルの絶対パス => 行番号 => メッセージ一覧 を返す
 *
 * @param list<string> $paths
 * @return array{files: array<string, array<int, list<array{line: int, message: string, identifier: string}>>>, errors: list<string>}
 */
function analyse(array $paths): array
{
	$command = array_merge(
		[PHP_BINARY, PHPSTAN, 'analyse', '--error-format=json', '--no-progress', '--no-interaction', '-c', CONFIG, '--'],
		$paths,
	);
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if ($process === false) {
		throw new RuntimeException('Failed to run PHPStan');
	}
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);

	$json = json_decode(is_string($stdout) ? $stdout : '', true);
	if (!is_array($json) || !isset($json['files'], $json['errors'])) {
		throw new RuntimeException("PHPStan returned unexpected output:\n{$stdout}\n{$stderr}");
	}

	$files = [];
	assert(is_array($json['files']));
	foreach ($json['files'] as $path => $result) {
		assert(is_string($path));
		assert(is_array($result) && is_array($result['messages']));
		foreach ($result['messages'] as $message) {
			assert(is_array($message) && is_int($message['line']) && is_string($message['message']));
			$identifier = $message['identifier'] ?? '';
			assert(is_string($identifier));
			$files[$path][$message['line']][] = [
				'line' => $message['line'],
				'message' => $message['message'],
				'identifier' => $identifier,
			];
		}
	}
	assert(is_array($json['errors']));
	$errors = array_values(array_map(static fn ($e): string => is_string($e) ? $e : json_encode($e, JSON_THROW_ON_ERROR), $json['errors']));

	return ['files' => $files, 'errors' => $errors];
}

/**
 * ある行の実際の出力を注釈と同じ形 (dumps / errors) に整理する
 *
 * @param list<array{line: int, message: string, identifier: string}> $messages
 * @return array{dumps: list<string>, errors: list<string>}
 */
function actualOf(array $messages): array
{
	$dumps = [];
	$errors = [];
	foreach ($messages as $message) {
		if (in_array($message['identifier'], DUMP_IDENTIFIERS, true)) {
			$dumps[] = preg_replace('/^Dumped type: /', '', $message['message']) ?? $message['message'];
		} else {
			$errors[] = $message['message'];
		}
	}
	return ['dumps' => $dumps, 'errors' => $errors];
}

/**
 * @param array{dumps: list<string>, errors: list<string>} $expected
 * @param array{dumps: list<string>, errors: list<string>} $actual
 * @return list<string> 差分の説明 (空なら一致)
 */
function diff(array $expected, array $actual): array
{
	$problems = [];
	foreach (['dumps' => 'DumpedType', 'errors' => 'Error'] as $key => $label) {
		$e = $expected[$key];
		$a = $actual[$key];
		sort($e);
		sort($a);
		if ($e === $a) {
			continue;
		}
		foreach (array_diff($e, $a) as $missing) {
			$problems[] = "expected {$label}: {$missing}";
		}
		foreach (array_diff($a, $e) as $extra) {
			$problems[] = "actual {$label}: {$extra}";
		}
		if ($problems === []) {
			$problems[] = "{$label} count differs (expected " . count($e) . ', actual ' . count($a) . ')';
		}
	}
	return $problems;
}

function checkMarkdown(string $root, string $target, Reporter $reporter): void
{
	$reporter->section($target);
	$path = $root . '/' . $target;
	$markdown = file_get_contents($path);
	if ($markdown === false) {
		$reporter->fail("{$target}: cannot read");
		return;
	}
	$dir = dirname($path);

	// 相対リンク先の存在チェック
	preg_match_all('/\]\((\.\.?\/[^)\s#]+)(?:#[^)]*)?\)/u', $markdown, $links);
	foreach (array_unique($links[1]) as $link) {
		if (file_exists($dir . '/' . $link)) {
			$reporter->ok("link {$link}");
		} else {
			$reporter->fail("{$target}: link target not found: {$link}");
		}
	}

	$blocks = Markdown::codeBlocks($markdown);
	$fileBlocks = [];
	$standalone = [];
	$unchecked = 0;
	foreach ($blocks as $block) {
		if ($block['info'] !== 'php') {
			continue;
		}
		if (isset($block['attrs']['file'])) {
			$fileBlocks[] = $block;
		} elseif (isset($block['attrs']['phpstan'])) {
			$standalone[] = $block;
		} else {
			$unchecked++;
		}
	}
	if ($unchecked > 0) {
		$reporter->note("{$unchecked} php block(s) without `file=` or `phpstan` attribute are not checked");
	}

	// file= ブロック: 参照される全ファイルをまとめて解析
	$files = [];
	foreach ($fileBlocks as $block) {
		$file = realpath($dir . '/' . $block['attrs']['file']);
		if ($file === false) {
			$reporter->fail("{$target}:{$block['start']}: file not found: {$block['attrs']['file']}");
			continue;
		}
		$files[$file] = true;
	}
	$analysis = $files === [] ? ['files' => [], 'errors' => []] : analyse(array_keys($files));
	foreach ($analysis['errors'] as $error) {
		$reporter->fail("{$target}: PHPStan error: {$error}");
	}

	foreach ($fileBlocks as $block) {
		$label = "{$target}:{$block['start']} (file={$block['attrs']['file']})";
		$file = realpath($dir . '/' . $block['attrs']['file']);
		if ($file === false) {
			continue;
		}
		$snippet = Snippet::parse($block['lines'], $block['start'], true);
		$fileLines = normalize(preg_split('/\R/u', (string) file_get_contents($file)) ?: []);
		$mapping = locate(normalize($snippet->code), $fileLines, $snippet->ellipses);
		if ($mapping === null) {
			$reporter->fail("{$label}: code block does not match the file contents");
			continue;
		}
		$problems = compareExpectations($snippet, $mapping, $analysis['files'][$file] ?? [], false);
		if ($problems === []) {
			$reporter->ok("{$label}: matches file" . (count($snippet->expectations) > 0 ? ' and ' . count($snippet->expectations) . ' annotated line(s)' : ''));
		} else {
			foreach ($problems as $problem) {
				$reporter->fail("{$label}: {$problem}");
			}
		}
	}

	// phpstan ブロック: 単体で解析
	$tmpDir = sys_get_temp_dir() . '/phpstan-typing-tutorial-' . getmypid();
	@mkdir($tmpDir);
	foreach ($standalone as $n => $block) {
		$label = "{$target}:{$block['start']} (phpstan)";
		$snippet = Snippet::parse($block['lines'], $block['start'], false);
		$code = implode("\n", $snippet->code) . "\n";
		$offset = 0;
		if (!str_starts_with(ltrim($code), '<?php')) {
			$code = "<?php declare(strict_types = 1);\n\n" . $code;
			$offset = 2;
		}
		$tmpFile = "{$tmpDir}/snippet-{$n}.php";
		file_put_contents($tmpFile, $code);
		$result = analyse([$tmpFile]);
		$mapping = [];
		foreach (array_keys($snippet->code) as $i) {
			$mapping[$i] = $i + $offset + 1;
		}
		$problems = array_merge(
			array_map(static fn (string $e): string => "PHPStan error: {$e}", $result['errors']),
			compareExpectations($snippet, $mapping, $result['files'][$tmpFile] ?? [], true),
		);
		unlink($tmpFile);
		if ($problems === []) {
			$reporter->ok("{$label}: analysed" . (count($snippet->expectations) > 0 ? ', ' . count($snippet->expectations) . ' annotated line(s) match' : ', no errors'));
		} else {
			foreach ($problems as $problem) {
				$reporter->fail("{$label}: {$problem}");
			}
		}
	}
	@rmdir($tmpDir);
}

/**
 * @param array<int, int> $mapping  スニペット行番号(0始まり) => 解析対象ファイルの行番号(1始まり)
 * @param array<int, list<array{line: int, message: string, identifier: string}>> $messages  行番号 => メッセージ
 * @param bool $strict  注釈のない行にエラーがあれば失敗にする (dumpType の出力は除く)
 * @return list<string>
 */
function compareExpectations(Snippet $snippet, array $mapping, array $messages, bool $strict): array
{
	$problems = [];
	$reverse = array_flip($mapping);
	foreach ($snippet->expectations as $index => $expected) {
		$fileLine = $mapping[$index];
		$actual = actualOf($messages[$fileLine] ?? []);
		foreach (diff($expected, $actual) as $problem) {
			$problems[] = "line {$snippet->sourceLines[$index]}: {$problem}";
		}
	}
	if ($strict) {
		foreach ($messages as $fileLine => $lineMessages) {
			$index = $reverse[$fileLine] ?? null;
			if ($index !== null && isset($snippet->expectations[$index])) {
				continue;
			}
			foreach (actualOf($lineMessages)['errors'] as $error) {
				$where = $index === null ? "(generated line {$fileLine})" : "line {$snippet->sourceLines[$index]}";
				$problems[] = "{$where}: unexpected error: {$error}";
			}
		}
	}
	return $problems;
}

/**
 * answers/ 以下の解答例が PHPStan でエラーにならないことを確認する (dumpType の出力は許容)
 *
 * 演習ファイルと同名のシンボルを定義するため、ファイルごとに個別に解析する
 */
function checkAnswers(string $root, Reporter $reporter): void
{
	$reporter->section('answers/');
	$answers = glob(ANSWERS_DIR . '/*/*.php') ?: [];
	sort($answers);
	if ($answers === []) {
		$reporter->note('no answer files');
		return;
	}
	foreach ($answers as $answer) {
		$real = realpath($answer);
		if ($real === false) {
			continue;
		}
		$label = substr($real, strlen($root) + 1);
		$result = analyse([$real]);
		$errors = $result['errors'];
		foreach ($result['files'][$real] ?? [] as $line => $messages) {
			foreach (actualOf($messages)['errors'] as $error) {
				$errors[] = "line {$line}: {$error}";
			}
		}
		if ($errors === []) {
			$reporter->ok("{$label}: no errors");
		} else {
			foreach ($errors as $error) {
				$reporter->fail("{$label}: {$error}");
			}
		}
	}
}

$argv = $_SERVER['argv'] ?? [];
$argv = is_array($argv) ? array_values(array_filter($argv, 'is_string')) : [];
exit(main($argv));
