# チュートリアルの書き方

このリポジトリでは、本文 (`*/README.md`) と演習用PHPファイル、そしてPHPStanの実際の出力が食い違わないように、`composer check` で機械的に検査しています。

```bash
composer install
composer check
```

`composer check` は次を実行します。

 * `composer check-tools` — `tools/` 以下を PHPStan で解析します
 * `composer check-docs` — `tools/check-docs.php` で本文とPHPファイルの整合性を検査します

GitHub Actions でも同じ検査が走ります (`.github/workflows/check.yml`)。

## ディレクトリ構成

```
beginner/     入門編 (README.md と演習ファイル N.php)
basic/        基礎編
answers/      演習の解答例 (answers/beginner/N.php のように配置)
tools/        検査ツール
```

演習ファイルと解答例は同名の関数・クラスを定義するため、解答例は `phpstan.dist.neon` の `paths` に**含めない** `answers/` に置きます。同じディレクトリに置くと `phpstan analyse` の一括実行で定義が衝突し、誤ったエラーが出ます。

## コードブロックの検査

README 中の ```` ```php ```` ブロックは、フェンスに属性を付けると検査対象になります。属性のないブロックは検査されません (件数だけ表示されます)。

### `file=` — 演習ファイルとの一致

````markdown
```php file=3.php
function search(string $word, string $order, int $page): array
{
    // ...
}
```
````

ブロックの内容が、README と同じディレクトリにある指定ファイルの**連続した行**と一致することを検査します。

 * タブとスペースの違い、行末の空白は無視されます (PHPファイルはタブ、README は4スペースで構いません)
 * `// ...` または `// …` だけの行は「ここに任意の行が入る」という省略記号として扱われます
 * ファイルの一部だけを抜粋できます

### `phpstan` — スニペット単体の解析

````markdown
```php phpstan
$word = filter_var($_GET['word'] ?? '');
\PHPStan\dumpType($word); // DumpedType: string|false
```
````

ブロックの内容を単体のPHPファイルとして PHPStan で解析します。先頭に `<?php` がなければ `<?php declare(strict_types = 1);` が補われます。

 * 注釈 (後述) を付けた行は、注釈と実際の出力が**完全に一致**することを検査します
 * 注釈のない行にエラーが出た場合は失敗します (`dumpType()` の出力は除く)。本文で触れないエラーが紛れ込むのを防ぐためです

### 注釈

コードブロック内のコメントで、その行に PHPStan が出力する内容を宣言します。ファイルとの一致検査では注釈は取り除いてから比較されるので、演習ファイル側に注釈を書く必要はありません。

| 書き方 | 意味 |
|---|---|
| `// DumpedType: int` | その行の `\PHPStan\dumpType()` の出力 (`// Dumped type: int` や `# Dumped type: int` も可) |
| `// Error: Function f() has no return type specified.` | その行で発生するエラーメッセージ |

注釈は**行末**に書くか、**直前の行**に1行ずつ書きます。直前の行に書いた注釈は、次のコード行 (注釈でない行) に適用されます。

```php
// Error: Function label() has no return type specified.
// Error: Function label() has parameter $title with no type specified.
function label($title)
{
    return "label:{$title}";
}

\PHPStan\dumpType($x); // DumpedType: int
```

注釈を付けた行は、注釈で宣言した dump 出力とエラーの集合が、実際の出力と**過不足なく**一致しなければなりません (順序は問いません)。

## 解答例

`answers/*/*.php` はそれぞれ単体で PHPStan を実行し、`dumpType()` の出力以外のエラーが出ないことを検査します。

## Playground のリンク

演習ファイルを変更したら、対応する節の **PHPStan Playground** のリンクを再生成してください。Playground のリンクは自動では検査できないので、更新が必要な箇所には `<!-- TODO: ... -->` を残しておきます。
