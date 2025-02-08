# PHPStan型付けチュートリアル 基礎編


## 1. 関数の戻り値に型をつけよう

既存の関数には適切な戻り値をつけることが大切ですが、型を付けるのに必要以上に手間をかけるのは徒労です。

このコースでは極力手間をかけずに型を書くことを身につけましょう。

> [!NOTE]
> この節のコードは以下で確認できます
> * **PHPStan Playground**: <https://phpstan.org/r/99e38017-017c-4ca0-9a41-48750b676c8a>
> * **File**: [`1.php`](./1.php)
> * **CLI**: `./vendor/bin/phpstan analyze basic/1.php`

``` php
<?php declare(strict_types = 1);

class UsersBuilder
{
	public function buildUser(int $id, string $name, string $birthday): array
	{
		$result = [
			'ID' => $id,
			'Name' => $name,
			'BirthDay' => new DateTimeImmutable($birthday),
		];

		return $result;
	}

	public function fetchUsers(): array
	{
		// 仮実装なので仮データを返す
		$users = [];
		$users[] = $this->buildUser(1, 'Miku', '2007-08-31');
		$users[] = $this->buildUser(2, 'Rin', '2007-12-27');
		$users[] = $this->buildUser(3, 'Len', '2007-12-27');
		$users[] = $this->buildUser(4, 'Luka', '2009-01-30');

		return $users;
	}
}
```

初期状態ではPHPStanは以下のようなエラーを発生させます。

> [!WARNING]
>
> ```
> Method UsersBuilder::buildUser() return type has no value type specified in iterable type array.
> Method UsersBuilder::fetchUsers() return type has no value type specified in iterable type array.
> ```
> **See**: [Solving PHPStan error "No value type specified in iterable type"](https://phpstan.org/blog/solving-phpstan-no-value-type-specified-in-iterable-type)

PHPStanは基本的に `array` だけの型宣言には厳しい対応をします。なぜなら、`array`を一皮剥いたら何が返ってくるかわからない、つまりは**型なし**だからです。そのため、配列の内部構造も公開仕様として語ってあげなければいけません。

PHPDocで配列に詳細な型を記述する方法は大きく分けて二つあります：

 * `array<string>`, `array<int, string>` (ジェネリック配列記法)
   * 同じ構造が繰り返す場合に使う
 * `array{}`, `array{key: value}`, `array{int, string, string}` (array-shapes記法)
   * 配列の中のキーごとに別の意味の値が格納される場合に使う

使い慣れてくればどちらがどちらか混乱することはないのですが大変初心者殺しであることは間違いありません。

そこで使えるのが `\PHPStan\dumpPhpDocType()` です。これは `\PHPStan\dumpType()` と似ていますが、PHPDocでそのまま使える記法で出力してくれる機能です。

手始めに `buildUser()` メソッドに型をつけてみましょう。

```php
	public function buildUser(int $id, string $name, string $birthday): array
	{
		$result = [
			'ID' => $id,
			'Name' => $name,
			'BirthDay' => new DateTimeImmutable($birthday),
		];
		\PHPStan\dumpPhpDocType($result);
        # Dumped type: array{ID: int, Name: string, BirthDay: DateTimeImmutable}

		return $result;
	}
```

型が出力されました。これをコピペしてPHPDocに貼り付けます。

```php
	/**
     * @return array{ID: int, Name: string, BirthDay: DateTimeImmutable}
     */
```

今度は同じように `fetchUsers()` に型をつけてみましょう。

うまく行っていれば、こういう型が出力されるはずです。

```
Dumped type: array{array{ID: int, Name: string, BirthDay: DateTimeImmutable}, array{ID: int, Name: string, BirthDay: DateTimeImmutable}, array{ID: int, Name: string, BirthDay: DateTimeImmutable}, array{ID: int, Name: string, BirthDay: DateTimeImmutable}}
```

なんだか長い型が出てしまいましたね… これはPHPStanが賢すぎて、`$users`がきちんと長さ4の配列だと認識してくれてしまっているからこその問題です。

整形すると以下のようになります。

```php
	/**
	 * @return array{
	 *     0: array{ID: int, Name: string, BirthDay: DateTimeImmutable},
	 *     1: array{ID: int, Name: string, BirthDay: DateTimeImmutable},
	 *     2: array{ID: int, Name: string, BirthDay: DateTimeImmutable},
	 *     3: array{ID: int, Name: string, BirthDay: DateTimeImmutable},
	 * }
	 */
```

これをコピペしてまたPHPDocに貼り付ける… でもいいのですが、コードにはこういうことが書いてあります

``` php
		// 仮実装なので仮データを返す
```

ということは、本実装では何個になるかというのは、あまり具体的な意味はなさそうです。

さきほどのPHPDoc記法の使い分けを再掲しましょう。

>  * `array<string>`, `array<int, string>` (ジェネリック配列記法)
>    * **同じ構造が繰り返す場合に使う**
>  * `array{}`, `array{key: value}`, `array{int, string, string}` (array-shapes記法)
>    * 配列の中のキーごとに別の意味の値が格納される場合に使う

今回の場合はどの要素も引数は違いますが、同じ大きく見れば同じ構造のものの繰り返しと考えられます。

つまり、このように書けます。

```php
	/**
	 * @return array<array{ID: int, Name: string, BirthDay: DateTimeImmutable}>
	 */
```
この型は読みやすいように改行しても構いません。

```php
	/**
	 * @return array<array{
     *   ID: int,
     *   Name: string,
     *   BirthDay: DateTimeImmutable,
     * }>
	 */
```
