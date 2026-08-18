# PHPStan型付けチュートリアル 入門編

## 入門編のねらい

この入門編は、いままでPHPStanを使ったことがない方でも、「PHPStanがどのようにコードを分析しているか」の感覚を捉えるようになることが目的です。むしろ、「PHPStanにある程度馴染みはじめた」という方こそ「初めて聞いた」という内容があるかもしれません。

全ての機能を直ちに使いこなせるようになることはこの記事の目的ではありません。あなたが書いたコードに期待通りの型が付いているのか、付いていないのかを判別できるようになることが重要です。

## 1. 値の型を確認してみよう

PHPStanは値についた型を `\PHPStan\dumpType()` 関数で出力できます。

> [!NOTE]
> この節のコードは以下で確認できます
> * **PHPStan Playground**: <https://phpstan.org/r/a11064d0-10e1-4bb6-890f-3870ad2be84c>
> * **File**: [`1.php`](./1.php)
> * **CLI**: `./vendor/bin/phpstan analyze beginner/1.php`

<!-- TODO: 1.php に use function を追加したので Playground のリンクを再生成する -->

```php phpstan
$a = 'foo';
$b = 'bar';
$c = $a . $b;
// . は文字列を結合する演算子

\PHPStan\dumpType($a);
\PHPStan\dumpType($b);
\PHPStan\dumpType($c);
```

> [!TIP]
> ファイルの先頭に `use function PHPStan\dumpType;` と書いておけば、単に `dumpType($a);` とも書けます。このチュートリアルの各ファイルにはこの`use function`を入れてあります。
>
> ただしデバッグ用途のために毎回関数を`use function`でインポートするのは面倒に感じられるかもしれません。本文中のコードでは「どこの関数か」が一目でわかるように、 `\PHPStan\dumpType($a);` のように名前空間から書く形で統一します。

複数の値をまとめてチェックしたいときは[`compact()`]で配列にまとめることでわかりやすくなることもあります。

```php phpstan
$n = 5;
$m = 2;
$l = $n / $m;
\PHPStan\dumpType(compact('n', 'm', 'l'));
```

「これってPHPを実行した結果を画面に表示してるだけじゃないの？」と思われるかもしれません。禅問答のようですが、「***そうであって、そうではない***」のです。

何を言っているかわからないと思うので、次のようなコードを考えてみましょう。

```php phpstan
$n = 5;
if (rand() === 1) {
    $m = 2;
} else {
    $m = 5;
}

$l = $n / $m;
\PHPStan\dumpType(compact('n', 'm', 'l')); // DumpedType: array{n: 5, m: 2|5, l: 1|2.5}
```

`rand() === 1` という条件が成り立つ確率は大雑把に「**21億分の1**」です。PHPStanは`rand() === 1`という確率的な処理は**行なっていません**。どちらでも僅かにでも可能性があるならば、PHPStanは**どちらの可能性もある**と判断して`2|5`という型をつけます。さらに`$l = $n / $m`という式はどうでしょうか。`$n`には`5`という型がついていますが、`$m`は`2`と`5`の可能性があるので、`$l = 5 / 2` (= `2.5`) と `$l = 5 / 5` (= `1`) という2パターンが考えられます。ここでPHPStanは`$l`に`1|2.5`という型をつけます。これはPHPStanが行なう型付けの特殊な例などではなく、***PHPStanが常に行なっていること***です。

> [!CAUTION]
> `\PHPStan\dumpType()` は静的解析時に用いられる擬似的な関数ですが、実行時に定義されません。
>
> 実アプリケーションでは実行する前、あるいはユニットテスト実行前に取り除いてください。

> [!TIP]
> * PHPのコードは文末に`;`が必要です
> * `.`演算子は文字列として結合します (`+`は常に数値の加算および配列マージを意味します)
> * * `/` 演算子は数値の除算(割り算)を行います

> [!IMPORTANT]
> 🔜 **コードを好きに書き換えてみて、納得できたら次に進んでください**

## 1.5. 型は追跡され、追跡できなくなると広がる

> [!NOTE]
> この節のコードは以下で確認できます
> * **PHPStan Playground**: TODO
> * **File**: [`1.5.php`](./1.5.php)
> * **CLI**: `./vendor/bin/phpstan analyze beginner/1.5.php`

<!-- TODO: 1.5.php の Playground リンクを発行する -->

PHPStanはコードを実行しているわけではありませんが、**追跡できる限り**は値を追いかけます。

```php phpstan
$n = 5;
$n = $n + 1;
\PHPStan\dumpType($n); // DumpedType: 6

$count = 0;
foreach (['a', 'b', 'c'] as $s) {
    $count++;
}
\PHPStan\dumpType($count); // DumpedType: 3
```

`5`や`6`、`'foo'`のように「その値ひとつだけ」を表す型を**定数型**(constant type)と呼びます。3要素の配列を`foreach`で回して`$count++`すれば`3`になる、というところまでPHPStanは追跡します。

では、追跡できなくなるとどうなるでしょうか。

```php phpstan
$total = 0;
foreach ($_GET as $value) {
    $total++;
}
\PHPStan\dumpType($total); // DumpedType: int<0, max>

$r = rand();
\PHPStan\dumpType($r); // DumpedType: int<0, max>
\PHPStan\dumpType($r + 1); // DumpedType: int<1, max>
```

`$_GET`に何件の値が入っているかは実行するまでわかりません。ループが0回かもしれないし、100回かもしれない。そこでPHPStanは「**0以上の整数**」という意味の`int<0, max>`という型をつけます。これは**整数範囲型**(integer range type)といい、`int<最小値, 最大値>`の形で書きます。`max`は「上限なし」、`min`は「下限なし」を意味します。

`rand()`は0以上の乱数を返すので`int<0, max>`、それに`1`を足せば`int<1, max>`と、範囲も追跡されます。

`int<0, max>`と`int<1, max>`はよく使うので、それぞれ`non-negative-int`、`positive-int`という別名でも書けます。次の節以降のエラーメッセージに`int<1, max>`が出てきたら「1以上の整数のことだな」と読み替えてください。

> [!IMPORTANT]
> 🔜 **配列の要素数やループの回数を書き換えて、型がどう変わるか確かめられたら次に進んでください**

## 2. 型宣言で関数に型をつける

> [!NOTE]
> この節のコードは以下で確認できます
> * **PHPStan Playground**: <https://phpstan.org/r/f95fa83b-1216-46a1-9631-98a4736c5544>
> * **File**: [`2.php`](./2.php)
> * **CLI**: `./vendor/bin/phpstan analyze beginner/2.php`

<!-- TODO: 2.php に use function を追加したので Playground のリンクを再生成する -->

PHPの関数に型を付けてみましょう。

```php file=2.php
// Error: Function label() has no return type specified.
// Error: Function label() has parameter $title with no type specified.
function label($title)
{
    // Error: Part $title (mixed) of encapsed string cannot be cast to string.
    return "label:{$title}";
}

// Error: Expected type string, actual: mixed
\PHPStan\Testing\assertType('string', label('foo'));
```

> [!TIP]
> `\PHPStan\Testing\assertType(expected, actual)` は値が期待する型とPHPStanが認識している型の **文字列表現の一致** をチェックする関数です。`expected`と`actual`が同じ文字列なら何も出力されなくなります。
>
> ここでは使っていませんが、部分型関係を用いてチェックする `\PHPStan\Testing\assertSuperType(expected, actual)`もあります。
>
> `dumpType()`と同じく、`use function PHPStan\Testing\assertType;` を書いておけば `assertType('string', label('foo'));` とも書けます。

### エラーメッセージを読む

初期状態では4つのエラーが出ます。上のコードには、その行で発生するエラーを`// Error:`として書き添えてあります。PHPStanのエラーはどれも「**どこで**」「**何が**」「**どうなっているか**」を1行で説明しているので、慌てずに読み下してみましょう。

 * `Function label() has no return type specified.`
   * 関数`label()`に戻り値の型宣言がない
 * `Function label() has parameter $title with no type specified.`
   * 関数`label()`のパラメータ`$title`に型宣言がない
 * `Part $title (mixed) of encapsed string cannot be cast to string.`
   * 文字列の中に埋め込まれた`$title`は`mixed`型なので、文字列に変換できるかわからない
 * `Expected type string, actual: mixed`
   * `assertType()`が「`string`を期待したが、実際は`mixed`だった」と言っている

ここに出てくる`mixed`は「**どんな値でもありうる**」という型です。型宣言のないパラメータには`mixed`が付きます。`mixed`はあらゆる型を含むので、`mixed`の値を文字列として扱ったり、メソッドを呼び出したりしようとするとPHPStanは「それが本当にできるかわからない」と警告します。裏を返せば、**型を付けるとは`mixed`を減らしていくこと**だといえます。

> [!TIP]
> * PHPStanには **レベル**(0〜10)があり、レベルが高いほど厳しく検査します。このチュートリアルは最も厳しい`level: max`(Playgroundでは **Level 10**)で動いています。「型宣言がない」というエラーが出るのもレベルが高いためです
> * CLIで実行すると各エラーに`🪪 missingType.return`のような **エラー識別子** が表示されます。<https://phpstan.org/error-identifiers/missingType.return> のように識別子をURLに付けると、そのエラーの解説ページを読めます

> [!TIP]
> 型宣言を含まない関数 `function f($arg1, $arg2) { ... }` は、どんな型の引数も受け入れ、どんな型の値を返すこともできます。
>
> * `function f(int $arg1, float $arg2) { ... }`
>   * `()`内に`type $arg`と書くことで、パラメータの型を宣言します
> * `function f($arg1, $arg2): int|float { ... }`
>   * `()` の次に `: type` と書くことで、戻り値の型を宣言します
>
> パラメータと戻り値の型宣言は組み合わせることができます。

PHPではパラメータ(仮引数リスト)や戻り値に型宣言を追加できます。関数やメソッドで型宣言された型は、実行時に**必ず保証**されます。

保証されるということは次のことを意味します：

 * パラメータで宣言された型は、実装内で必ず制約を満たします
   * ⇒ **制約を満たさない値が渡されることを心配する必要はありません**
 * 呼び出した結果、必ず宣言された型の制約を満たす戻り値が返されます
   * ⇒ **制約を満たさない値が返されることを心配する必要はありません**

それぞれの箇所では、型宣言されたものが静的型付きであることが必ず保証されます。

> [!TIP]
> PHPの型宣言についてどのように振る舞うかチェックできます
> * [php-playで確認する](https://php-play.dev/?c=DwfgDgFmAEAmCmBjANgQwE7wBQGcAu6AlongPp4CeY8OAvAIwCUA3AFCsBmArgHYmEB7HtDQAjeMlwFCPAObQAJHkJ5k8RgC5o%2BInNYBvVgEhMeLumEAiMRI36lKtQF9LbJ%2BwBuGUrC4BbMCwbSQByDgEBEMYWVi90H39A4KwmGKA&v=8.4&f=console)

> [!IMPORTANT]
> 🔜 **型を追加してエラーが出なくなったら次に進んでください**
> * 詰まったら解答例 [`answers/beginner/2.php`](../answers/beginner/2.php) を見ても構いません

## 3. 型を絞り込む

> [!NOTE]
> この節のコードは以下で確認できます
> * **PHPStan Playground**: <https://phpstan.org/r/aaa28500-8f05-4fff-b53c-97e1d74f708a>
> * **File**: [`3.php`](./3.php)
> * **CLI**: `./vendor/bin/phpstan analyze beginner/3.php`

<!-- TODO: 3.php に use function を追加したので Playground のリンクを再生成する -->

ユーザーがフォームから検索して、結果の書籍一覧を表示する画面を考えてみましょう。

`search()`関数の実装は次のようになっています。

```php file=3.php
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
        // Error: Parameter #1 $title of class Book constructor expects non-empty-string, '' given.
        // Error: Parameter #2 $authors of class Book constructor expects non-empty-array<Author>, array{} given.
        1 => [new Book('', [])],
        default => [],
    };
}
```

`/** … */`は**Doc comment**といい、関数やクラスの説明を記述できます。 `@param`や`@return`のような記述は**PHPDocタグ**と呼びます。`@param`はパラメータの詳細な型を、`@return`は関数・メソッドの戻り値の型を記述します。

 * `@param non-empty-string $word`
   * 空文字列での検索は不正なので、関数呼び出し側の責任でチェックする
   * `non-empty-string`とは、`''`(空文字列)以外の文字列のことです
 * `@param 'asc'|'desc' $order`
   * 検索結果を昇順と降順のどちらに並び変えるか
 * `@param positive-int $page`
   * 検索のページ数：最小値は`1`
 * `@return list<Book>`
   * `Book`クラスのリスト
   * [`list`型について](https://scrapbox.io/php/list%E5%9E%8B)

`Book`クラスと`Author`クラスは同じファイルの先頭で次のように定義されています。

```php file=3.php
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
```

> [!TIP]
> * `public function __construct(public string $name)` のようにコンストラクタのパラメータに`public`などを付けると、同名のプロパティの宣言と代入を兼ねます (**コンストラクタプロモーション**)
> * `readonly class` は全プロパティが読み取り専用のクラスです。一度作った`Book`の中身は書き換えられません
> * `non-empty-array<Author>` は「`Author`を1つ以上含む配列」です。`search()`の初期実装が`new Book('', [])`でエラーになるのは、この制約に反しているためです

> [!WARNING]
> Doc commentは、必ず `/** ... */` (`*`が二つ！)から始まります。  
> 範囲コメントの `/* ... */` とは区別されるので十分に気をつけてください。
>
> エディタによってはPHPDocタグが色付けされるかによって区別できます。
> ![Emacs PHP ModeでPHPDocタグが色付けされている画像](../pictures/highlighting-comments.png)

続いて、外部からの入力を値として取得します。

```php file=3.php
$word = filter_var($_GET['word'] ?? '');
$order = filter_var($_GET['order'] ?? 'asc');
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

\PHPStan\dumpType(compact('word', 'order', 'page')); // DumpedType: array{word: string|false, order: string|false, page: int|false}

// Error: Parameter #1 $word of function search expects non-empty-string, string|false given.
// Error: Parameter #2 $order of function search expects 'asc'|'desc', string|false given.
// Error: Parameter #3 $page of function search expects int<1, max>, int|false given.
$books = search($word, $order, $page);
```

初期状態では`// Error:`に書いたエラーが発生します。前の節で読み方を練習したとおり、「`search()`の1番目のパラメータ`$word`は`non-empty-string`を期待しているが、`string|false`が渡されている」と読めます。

> [!TIP]
> * [`filter_var()`](https://www.php.net/filter_var)
>   * 値をフィルタリングする関数です (名前に反して変数以外もフィルタできます)
>   * PHPStanは[検証フィルタ]とオプションによって型が変わります

PHPStanは比較により**型を絞り込む**(type narrowing)ことができます。
コードに以下のようなコードを追加して型を確認してみてください。

```php phpstan
$word = filter_var($_GET['word'] ?? '');
\PHPStan\dumpType($word); // DumpedType: string|false

if ($word === '' || $word === false) {
    \PHPStan\dumpType($word); // DumpedType: ''|false
} else {
    \PHPStan\dumpType($word); // DumpedType: non-empty-string
}

\PHPStan\dumpType($word); // DumpedType: string|false
```

PHPStanは**制御フロー解析**を実装しており、`if`や`foreach`といった制御構造に従った変数スコープを保持しています。上記のコードでは初期状態で`string|false`だった変数が`if`と`else`でそれぞれ`''|false`と`non-empty-string`に分岐し、合流後は`string|false`に**戻っている**ことが確認できます。

型が絞り込まれた状態で制御フローを中断することで、その型を絞り込めます。中断とは、`return` `throw` `continue` `break` `exit` あるいは `never` 型の関数を読み込むなどです。

```php phpstan
$word = filter_var($_GET['word'] ?? '');
\PHPStan\dumpType($word); // DumpedType: string|false

if ($word === '' || $word === false) {
    throw new RangeException('$word を入力してください');
} else {
    \PHPStan\dumpType($word); // DumpedType: non-empty-string
}

\PHPStan\dumpType($word); // DumpedType: non-empty-string
```

これで型が絞り込まれた`else`の状態で固定できました。`else`のコードはまるごと削除しても構いません。さらに、型の絞り込みは**式の内部**でも起こります。

```php phpstan
$word = filter_var($_GET['word'] ?? '');

// strlen() に false を渡してしまう可能性があるのでエラー
// Error: Parameter #1 $string of function strlen expects string, string|false given.
if (strlen($word) === 0 || $word === false) {
    throw new RangeException('$word を入力してください');
}
```

これは `||` の右辺と左辺を入れ替えることで解決します。

```php phpstan
$word = filter_var($_GET['word'] ?? '');

// false のときに左辺で処理が打ち切られるので strlen() の呼び出しを防げる
if ($word === false || strlen($word) === 0) {
    throw new RangeException('$word を入力してください');
}

\PHPStan\dumpType($word); // DumpedType: non-empty-string
```

もっとも、このパターンは[`in_array()`](https://www.php.net/in_array)関数を用いて簡潔に絞り込めます。

```php phpstan
$word = filter_var($_GET['word'] ?? '');

if (in_array($word, [false, ''], true)) {
    throw new RangeException('$word を入力してください');
}

\PHPStan\dumpType($word); // DumpedType: non-empty-string
```

このように`in_array($var, ['foo', 'bar', 'buz'], true)`と書くことで、`$var === 'foo' || $var === 'bar' || $var === 'buz'`と等価になり、PHPStanも型の絞り込みを適切に認識します。

> [!TIP]
> `if (!$word)` や `if (empty($word))` と書きたくなるかもしれません。PHPStanはこれも理解しますが、`'0'`という文字列も偽と判定されて弾かれるため、絞り込まれた型は`non-falsy-string`になります。「空文字列だけを弾きたい」という意図とは違う型になっていないか、`dumpType()`で確かめる習慣をつけましょう。

### ほかの絞り込み方

`===`と`in_array()`以外にも、PHPが値を判定するときに使う書き方のほとんどで型を絞り込めます。`<`や`>`のような**比較演算子**は整数範囲型に絞り込みます。

```php phpstan
$limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);
\PHPStan\dumpType($limit); // DumpedType: int|false

if ($limit === false || $limit < 1 || $limit > 100) {
    throw new RangeException('$limit は1以上100以下の整数を入力してください');
}

\PHPStan\dumpType($limit); // DumpedType: int<1, 100>
```

`is_string()`や`is_int()`のような**型判定関数**は`mixed`から型を取り出す基本の道具です。

```php phpstan
$value = $_GET['value'] ?? null;
\PHPStan\dumpType($value); // DumpedType: mixed

if (!is_string($value)) {
    throw new RangeException('$value は文字列で入力してください');
}

\PHPStan\dumpType($value); // DumpedType: string
```

ほかにも次のような書き方でも型が絞り込まれます。どれも仕組みは同じ**制御フロー解析**です。

 * `$obj instanceof Book` — オブジェクトのクラス
 * `$value !== null` / `$value ?? $default` — `null`の除外
 * `is_array()`, `is_int()`, `is_numeric()`, `is_callable()`, ... — 型判定関数
 * `assert(is_string($value))` — アサーション
 * `match (true) { is_string($value) => ..., default => throw ... }` — `match`式

同じように、ほかの変数`$order`と`$page`の型も絞り込んでみてください。

> [!IMPORTANT]
> 🔜 **実装を修正してエラーが出なくなったら次に進んでください**
> * `search()`を呼び出す際に渡す値の型を適切に絞り込みます
> * `search()`の実装内部でエラーが出ないように適当な値を埋めてください
> * 詰まったら解答例 [`answers/beginner/3.php`](../answers/beginner/3.php) を見ても構いません

## 4. 型宣言で安全に型をつける

一方で、**ファイル単位**で `declare(strict_types=1);` の有無によって、スカラー型について「関数(メソッド)を呼び出す際の引数(argument, 実引数)」および「関数(メソッド)が返す戻り値の型」の振る舞いが変わります。

 * `strict_types=1`**あり**
   * 値と型が一致しないと`TypeError`を送出します
 * `strict_types=1`**なし** (または`0`)
   * [**関数のコンテクスト**][関数のコンテクスト]における型の相互変換を行い、文脈に沿わない値に`TypeError`を発生します

以下のようなコードを考えてみましょう。

> [!NOTE]
> この節のコードは以下で確認できます
> * **PHPStan Playground**: <https://phpstan.org/r/af94aa2f-0cb7-4ed3-99de-34fe92eeeba5>
> * **File**: [`4.php`](./4.php)
> * **CLI**: `./vendor/bin/phpstan analyze beginner/4.php`

<!-- TODO: 4.php に use function を追加したので Playground のリンクを再生成する -->

```php file=4.php
<?php declare(strict_types = 0);

use function PHPStan\dumpType;
use function PHPStan\Testing\assertType;

/**
 * $s が数値文字列だったら int に変換して返す
 */
function to_int(string $s): ?int
{
    try {
        return $s;
    } catch (TypeError) {
        return null;
    }
}

\PHPStan\Testing\assertType('int', to_int('1'));
\PHPStan\Testing\assertType('int', to_int('1.1'));
\PHPStan\Testing\assertType('null', to_int('php'));
\PHPStan\Testing\assertType('int|null', to_int(random_bytes(1)));
```

このコードは「**現実には動作するのにPHPStanが警告する**」代表的な例だと言えます。ただ、このコードはPHPStanで警告するだけでなく、`declare(strict_types=0)`に依存しているため安定しているとは言いがたいでしょう。

単に以下のようにすれば問題は解決するでしょうか。

```php phpstan
// Error: Function to_int() never returns null so it can be removed from the return type.
function to_int(string $s): ?int
{
    return (int)$s;
}
```

`(int)`キャストは入力値が数値文字列でなかったときにエラーも出さないため、基本的には適切ではありません。PHPStanも「この関数は`null`を返すことがない」と指摘しています。一方で`filter_var($var, FILTER_VALIDATE_INT)`や`filter_var($var, FILTER_VALIDATE_FLOAT)`は数値文字列として適切ではない文字列が返されたときに`false`を返します。

これらの検証をうまく組み合わせることで、適切な値を返す関数が実装できます。一方で、`assertType()`で表明したような型は実現できていません。

```php
\PHPStan\Testing\assertType('int', to_int('1'));
\PHPStan\Testing\assertType('int', to_int('1.1'));
\PHPStan\Testing\assertType('null', to_int('php'));
\PHPStan\Testing\assertType('int|null', to_int(random_bytes(1)));
```

PHPStanは**条件付き戻り値型**をサポートしているのでPHPDocタグに以下のように記述できます。

```php
 * @return ($s is numeric-string ? int : null)
```

条件付き戻り値型はPHPStan 2.1現在、`($param is T ? X : Y)`または`($param is not T ? Y : X)`のように記述できます。外側の`()`は省略できません。また、条件付き戻り値型の`X`と`Y`の部分は***どんな型も***ネストして書けます。もちろん`()`で括る必要はありますが、条件付き戻り値型をネストすることもできます。

引数で渡した値が「確実に`T`である」というときは`X`、「確実に`T`ではない」という場合は`Y`が戻り値になります。`$param: ?T`や`$param: T|U`のように型が絞り込まれていないければ、自動で`X|Y`になります。

`'1'`や`'1.1'`は定数で確実に数値文字列ですので、`int`という型を返してよいということになります。一方、`'php'`という文字列は間違っても数値ではないので、確実に`null`が返るということができます。「`$s :string`だがnumericかどうかはわからない」という時は自動で`?int`になります。

> [!TIP]
> 今回はとても大雑把に型をつけていますが、「数値文字列」ではなく「整数を表す文字列」だけをサポートしたい場合などはPHPStan 2.1時点ではPHPDocだけでは判定できず、PHPStan拡張を実装する必要があります。
> この章ではこのような使い方ができるということだけを認識できれば目的達成です。

> [!IMPORTANT]
> 🔜 **実装と型宣言を修正してエラーが出なくなれば、この章は修了です🎉**
> * 詰まったら解答例 [`answers/beginner/4.php`](../answers/beginner/4.php) を見ても構いません

## 入門編の修了

🎉 修了おめでとうございます！

ここまで学んだことを整理しましょう。

 * `\PHPStan\dumpType()`でPHPStanが認識している型を確認できる
   * `\PHPStan\Testing\assertType()`で期待する型との比較もできる
 * PHPStanは値を追跡できる限り定数型(`5`, `'foo'`)で追いかけ、追跡できなくなると`int<0, max>`のように型を広げる
 * PHPStanのエラーメッセージを「どこで・何が・どうなっているか」として読み下せる
 * `mixed`は「どんな値でもありうる」型で、型を付けるとは`mixed`を減らしていくこと
 * PHPの基本機能で関数・メソッドに型を付けることができる
 * PHPStanは制御フロー解析により型を絞り込める
   * `===`, `in_array()`, 比較演算子, `is_string()`などの型判定関数, `instanceof` などが使える
 * PHPでは実行できるがPHPStanが受け付けないコードも存在することを認識できる
 * PHPDocタグでより詳細な型を付けることができる
 * `declare(strict_types=1)`の有無での振る舞いの差異がわかる
 * `filter_var()`を使った型の検査方法がわかる
 * 条件付き戻り値型の存在を認識している

ここまでできれば、細かい理窟は抜きにして「PHPStanを使える」と言って差し支えないと思います。

もちろん全ての機能を直ちに使いこなせるようになっている必要はありません。とはいえ、コードを書いて期待通りの型がついていない原因をチェックできるようになったといえるでしょう。

より詳細なPHPStanの使い方を身に付けるために次のステップに進みましょう！

[関数のコンテクスト]: https://www.php.net/manual/ja/language.types.type-juggling.php#language.types.type-juggling.function
[`compact()`]: https://www.php.net/compact
[検証フィルタ]: https://www.php.net/manual/ja/filter.filters.validate.php
