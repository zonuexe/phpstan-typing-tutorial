<?php declare(strict_types = 1);

/**
 * README の PHPStan Playground リンクと演習ファイルの整合性を検査・更新する
 *
 * 使い方:
 *
 *     php tools/playground.php check              # リンク先の内容が演習ファイルと一致するか検査 (読み取りのみ)
 *     php tools/playground.php update             # 一致しない/未発行のリンクを新規発行して README を書き換える
 *     php tools/playground.php update --dry-run   # 発行せず対象だけ表示
 *
 * 引数に README のパスを渡すと対象を絞れます (省略時は全ての README.md)。
 *
 * README 中の次の形の行を対象にします:
 *
 *     > * **PHPStan Playground**: <https://phpstan.org/r/xxxxxxxx-...>   (未発行なら TODO)
 *     > * **File**: [`1.php`](./1.php)
 *
 * Playground の Share ボタンと同じ API (https://api.phpstan.org/analyse に saveResult: true) を使います。
 * 詳細は CONTRIBUTING.md を参照。
 */

const API_BASE = 'https://api.phpstan.org';
const USER_AGENT = 'phpstan-typing-tutorial/tools/playground.php';

/**
 * Playground に保存する設定。ローカルの phpstan.dist.neon (level max, bleedingEdge) に合わせる
 */
const LEVEL = '10';
const BLEEDING_EDGE = true;
const STRICT_RULES = false;
const TREAT_PHPDOC_TYPES_AS_CERTAIN = true;

/**
 * Playground の Options に相当する項目 (Playground の既定値)
 */
const OPTIONS = [
	'inferPrivatePropertyTypeFromConstructor' => true,
	'rememberPossiblyImpureFunctionValues' => true,
	'checkBenevolentUnionTypes' => false,
	'checkTooWideTypesInProtectedAndPublicMethods' => false,
	'implicitThrows' => true,
	'missingCheckedExceptionInThrows' => false,
	'reportUncheckedExceptionDeadCatch' => true,
	'uncheckedExceptionClasses' => [],
	'checkedExceptionClasses' => [],
	'tooWideImplicitThrowType' => false,
	'reportUnsafeArrayStringKeyCasting' => null,
];

const PLAYGROUND_LINE = '~^(?<prefix>>\s*\*\s*\*\*PHPStan Playground\*\*:\s*)(?<url><https://phpstan\.org/r/(?<id>[0-9a-f-]{36})>|TODO)\s*$~u';
const FILE_LINE = '~^>\s*\*\s*\*\*File\*\*:\s*\[`[^`]+`\]\((?<path>\./[^)]+\.php)\)~u';
const TODO_LINE = '~^<!--\s*TODO:.*Playground.*-->\s*$~u';

/**
 * @param list<string> $argv
 */
function main(array $argv): int
{
	$root = realpath(__DIR__ . '/..');
	assert(is_string($root));

	$args = array_slice($argv, 1);
	$dryRun = in_array('--dry-run', $args, true);
	$args = array_values(array_filter($args, static fn (string $a): bool => $a !== '--dry-run'));
	$mode = $args[0] ?? 'check';
	if (!in_array($mode, ['check', 'update'], true)) {
		fwrite(STDERR, "Usage: php tools/playground.php [check|update] [--dry-run] [README.md ...]\n");
		return 2;
	}
	$targets = array_slice($args, 1);
	if ($targets === []) {
		$targets = array_map(
			static fn (string $path): string => substr($path, strlen($root) + 1),
			glob($root . '/*/README.md') ?: [],
		);
		sort($targets);
	}

	$failed = 0;
	foreach ($targets as $target) {
		$failed += processReadme($root, $target, $mode, $dryRun);
	}

	fwrite(STDOUT, "\n");
	if ($failed === 0) {
		fwrite(STDOUT, "[OK] all Playground links are up to date\n");
		return 0;
	}
	fwrite(STDOUT, $mode === 'check'
		? "[ERROR] {$failed} Playground link(s) are missing or stale. Run: php tools/playground.php update\n"
		: "[ERROR] {$failed} Playground link(s) could not be updated\n");
	return 1;
}

/**
 * @return int 失敗 (未解決) の件数
 */
function processReadme(string $root, string $target, string $mode, bool $dryRun): int
{
	fwrite(STDOUT, "\n{$target}\n");
	$path = "{$root}/{$target}";
	$content = file_get_contents($path);
	if ($content === false) {
		fwrite(STDOUT, "  ✘ cannot read\n");
		return 1;
	}
	$dir = dirname($path);
	$lines = preg_split('/\R/u', $content) ?: [];
	$failed = 0;
	$changed = false;

	foreach ($lines as $i => $line) {
		if (preg_match(PLAYGROUND_LINE, $line, $m) !== 1) {
			continue;
		}
		$fileLine = $lines[$i + 1] ?? '';
		if (preg_match(FILE_LINE, $fileLine, $f) !== 1) {
			fwrite(STDOUT, '  ✘ line ' . ($i + 1) . ": no **File** line after the Playground line\n");
			$failed++;
			continue;
		}
		$file = $f['path'];
		$code = file_get_contents("{$dir}/{$file}");
		if ($code === false) {
			fwrite(STDOUT, '  ✘ line ' . ($i + 1) . ": file not found: {$file}\n");
			$failed++;
			continue;
		}
		$label = 'line ' . ($i + 1) . " ({$file})";

		$id = $m['id'] ?? '';
		$reason = $id === '' ? 'no link yet' : compareSample($id, $code);
		if ($reason === null) {
			fwrite(STDOUT, "  ✔ {$label}: up to date\n");
			continue;
		}
		if ($mode === 'check') {
			fwrite(STDOUT, "  ✘ {$label}: {$reason}\n");
			$failed++;
			continue;
		}
		if ($dryRun) {
			fwrite(STDOUT, "  · {$label}: would publish ({$reason})\n");
			$failed++;
			continue;
		}

		try {
			$newId = publish($code);
		} catch (RuntimeException $e) {
			fwrite(STDOUT, "  ✘ {$label}: {$e->getMessage()}\n");
			$failed++;
			continue;
		}
		$lines[$i] = $m['prefix'] . "<https://phpstan.org/r/{$newId}>";
		$changed = true;
		fwrite(STDOUT, "  ✔ {$label}: published https://phpstan.org/r/{$newId} ({$reason})\n");

		// NOTE ブロック直後の <!-- TODO: ... Playground ... --> を取り除く
		for ($j = $i + 2; $j <= $i + 5 && isset($lines[$j]); $j++) {
			if (preg_match(TODO_LINE, $lines[$j]) === 1) {
				$removeBlank = ($lines[$j - 1] ?? null) === '' && ($lines[$j + 1] ?? null) === '';
				array_splice($lines, $j, $removeBlank ? 2 : 1);
				break;
			}
		}
	}

	if ($changed) {
		file_put_contents($path, implode("\n", $lines));
		fwrite(STDOUT, "  · {$target} updated\n");
	}

	return $failed;
}

/**
 * 保存済みサンプルとローカルのコード・設定を比較する
 *
 * @return string|null 一致すれば null、そうでなければ理由
 */
function compareSample(string $id, string $code): ?string
{
	try {
		$sample = request('GET', "/sample?id={$id}");
	} catch (RuntimeException $e) {
		return "cannot fetch sample {$id}: {$e->getMessage()}";
	}
	$remoteCode = $sample['code'] ?? null;
	if (!is_string($remoteCode)) {
		return "sample {$id} has no code";
	}
	if (rtrim($remoteCode) !== rtrim($code)) {
		return 'code differs from the file';
	}
	$config = is_array($sample['config'] ?? null) ? $sample['config'] : [];
	$mismatch = [];
	if (($sample['level'] ?? null) !== LEVEL) {
		$mismatch[] = 'level';
	}
	foreach (['bleedingEdge' => BLEEDING_EDGE, 'strictRules' => STRICT_RULES, 'treatPhpDocTypesAsCertain' => TREAT_PHPDOC_TYPES_AS_CERTAIN] as $key => $expected) {
		if (($config[$key] ?? null) !== $expected) {
			$mismatch[] = $key;
		}
	}
	if ($mismatch !== []) {
		return 'config differs: ' . implode(', ', $mismatch);
	}
	return null;
}

/**
 * コードを Playground に保存して ID を返す
 */
function publish(string $code): string
{
	$options = OPTIONS + [
		'strictRules' => STRICT_RULES,
		'bleedingEdge' => BLEEDING_EDGE,
		'treatPhpDocTypesAsCertain' => TREAT_PHPDOC_TYPES_AS_CERTAIN,
	];
	$result = request('POST', '/analyse', [
		'code' => $code,
		'level' => LEVEL,
		'strictRules' => STRICT_RULES,
		'bleedingEdge' => BLEEDING_EDGE,
		'treatPhpDocTypesAsCertain' => TREAT_PHPDOC_TYPES_AS_CERTAIN,
		'options' => $options,
		'saveResult' => true,
	]);
	$id = $result['id'] ?? null;
	if (!is_string($id) || preg_match('/^[0-9a-f-]{36}$/', $id) !== 1) {
		throw new RuntimeException('API did not return an id');
	}
	return $id;
}

/**
 * @param array<string, mixed>|null $body
 * @return array<mixed>
 */
function request(string $method, string $path, ?array $body = null): array
{
	$headers = ['User-Agent: ' . USER_AGENT, 'Accept: application/json'];
	$options = ['method' => $method, 'timeout' => 120, 'ignore_errors' => true];
	if ($body !== null) {
		$headers[] = 'Content-Type: application/json';
		$options['content'] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
	$options['header'] = implode("\r\n", $headers);
	$context = stream_context_create(['http' => $options]);
	$response = @file_get_contents(API_BASE . $path, false, $context);
	if ($response === false) {
		throw new RuntimeException("request failed: {$method} {$path}");
	}
	$status = 0;
	/** @var list<string> $http_response_header */
	foreach ($http_response_header as $header) {
		if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $s) === 1) {
			$status = (int) $s[1];
		}
	}
	if ($status < 200 || $status >= 300) {
		throw new RuntimeException("HTTP {$status}: {$method} {$path}");
	}
	$json = json_decode($response, true);
	if (!is_array($json)) {
		throw new RuntimeException("invalid JSON from {$method} {$path}");
	}
	return $json;
}

$argv = $_SERVER['argv'] ?? [];
$argv = is_array($argv) ? array_values(array_filter($argv, 'is_string')) : [];
exit(main($argv));
