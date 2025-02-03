# PHPStan型付けチュートリアル

## 取り組み方

### Webブラウザで取り組む

チュートリアル本文にあるリンクからWeb上のPlaygroundにアクセスできます。

> [!NOTE]
> この節のコードは以下で確認できます
> * **PHPStan Playground**: `https://phpstan.org/...`
> * **File**: `xxx.php`
> * **CLI**: `./vendor/bin/phpstan analyze beginner/xxx.php`

### ローカルで実行する

このGitリポジトリをローカルに `git clone` してください。

> [!TIP]
> PHP 8.0以降と[Composer]がインストールされている必要があります。  
> `git clone` 後にディレクトリ内に移動し `composer install` を実行してください。

チュートリアルを読み進めながら、同じディレクトリがあるファイルを編集してください。

> [!TIP]
> エディタ画面上から編集中にPHPStanの出力を表示できるようにすると便利です。  
> **PhpStorm**、**VS Code**、**GNU Emacs**、**Vim**などでは拡張を有効化することで実現できます。

[Composer]: https://getcomposer.org/

## Copyright

この文書は[GNU自由文書ライセンス]により自由に利用できます。

    Copyright (c)  2025 USAMI Kenta
    Permission is granted to copy, distribute and/or modify this document
    under the terms of the GNU Free Documentation License, Version 1.3
    or any later version published by the Free Software Foundation;
    with no Invariant Sections, no Front-Cover Texts, and no Back-Cover Texts.
    A copy of the license is included in the section entitled "GNU
    Free Documentation License".

コード部分については[BSD Zero Clause License]とします。

    BSD Zero Clause License
    
    Copyright (c)  2025 USAMI Kenta
    
    Permission to use, copy, modify, and/or distribute this software for any
    purpose with or without fee is hereby granted.
    
    THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH
    REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT,
    INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM
    LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR
    OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR
    PERFORMANCE OF THIS SOFTWARE.

[GNU自由文書ライセンス]: https://www.gnu.org/licenses/fdl-1.3.html
[gfdl-ja]: https://doclicenses.opensource.jp/GFDL-1.2/GFDL-1.2.html
[BSD Zero Clause License]: https://choosealicense.com/licenses/0bsd/
