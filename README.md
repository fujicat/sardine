Sardine
====

## Overview

Sardineは主としてFuelPHPで使用することを目的とした各種ライブラリです。  
FuelPHP用に作っていますが、多分他のどのフレームワークでも使用できます。

## Install
パッケージ依存管理ツールComposerを使用します。

1. composer.jsonのrepositoriesセクションに以下を記述。
```
{   "type": "git",
    "url": "https://github.com/fujicat/sardine.git"
}
```

2. composer.jsonのrequireセクションに以下を記述
```
"fujicat/sardine": "1.0.*"
```

3. 以下のコマンドでインストール
```
php composer.phar install
```

## Licence

[MIT](https://github.com/fujicat/sardine/blob/master/LICENCE)

## Author

[fujicat](https://github.com/fujicat)
