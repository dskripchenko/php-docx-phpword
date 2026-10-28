# dskripchenko/php-docx-phpword

> **PHPWord 流水线缺的那一层 HTML。** 它在
> [PHPWord](https://github.com/PHPOffice/PHPWord) 的对象模型与
> [php-docx](https://github.com/dskripchenko/php-docx) 的 AST 之间架起带类型的桥：
> 把 `PhpWord` 对象导出为干净的 HTML（这正是 PHPWord 没有提供的导出）、把 HTML 导入
> `PhpWord`、把任意 DOCX 直接读进 PHPWord 模型、识别模板变量——同时继续用你熟悉的 API。

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · [Русский](../ru/README.md) · **中文**

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/php-docx-phpword/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/php-docx-phpword/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/php-docx-phpword?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/php-docx-phpword)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)

## 安装

```bash
composer require dskripchenko/php-docx-phpword
```

它会把 `dskripchenko/php-docx`（MIT、零依赖）装在你现有的 `phpoffice/phpword` 旁边。
本包使用 MIT；PHPWord 仍是 LGPL-3.0——一个普通的 Composer 依赖，你的代码无需任何改动。

## 能得到什么

### PhpWord → HTML（最关键的一项）

```php
use Dskripchenko\PhpDocxPhpWord\PhpWordBridge;

$html = PhpWordBridge::toHtml($phpWord);   // 干净的 HTML，样式内联
```

### HTML → PhpWord

```php
$phpWord = PhpWordBridge::fromHtml('<h1>报告</h1><p>正文中有<b>加粗</b>。</p>');
// …… 之后继续用 PHPWord 原生的调用与 writer
```

### 把任意 DOCX 读成 PhpWord 对象

```php
$phpWord = PhpWordBridge::readToPhpWord(file_get_contents('from-word.docx'));
```

内部使用 php-docx 的读取器（完整的样式级联；Word、Google Docs、LibreOffice、PHPWord
的产物都能读，保真度在
[外部语料集](https://github.com/dskripchenko/php-docx/blob/main/docs/READER-FIDELITY.md)
上持续跟踪），再映射到 PHPWord 模型。PHPWord 自带的读取器不参与其中。

### 识别模板变量

```php
$variables = PhpWordBridge::detectVariables($phpWordOrDocxBytes);
// MERGEFIELD、SDT 内容控件，以及 {{x}} / ${x} / %x% 形式
```

### 直达 AST 的出口

`PhpWordBridge::toDocument($phpWord)` 与 `PhpWordBridge::fromDocument($document)`
暴露底层映射本身，供后续继续在 php-docx 中处理的流水线使用。

## 共同元素集（诚实的边界）

桥只搬运**两个模型都能表达**的东西：

| 会被搬运 | 不会被搬运（跳过，绝不猜测） |
|---|---|
| 段落、文本 run（加粗、倾斜、下划线、删除线、上下标、颜色、字号、字体名） | 具名样式与主题样式（只保留解析后的取值） |
| 1–6 级标题 | 图表、目录、复选框、OLE |
| 表格，含 `gridSpan` / `vMerge` | 修订记录、批注 |
| 项目符号与编号列表（含嵌套） | 脚注/尾注、OMML 公式 |
| 超链接、图片（行内或浮动：位置、环绕、置于文字上方或下方）、换行与分页符 | 表单域 |
| 节（页面尺寸与方向、页边距、起始方式），含默认、首页与偶数页页眉页脚 | 水印 |
| 段落对齐、间距、缩进 | 节内分栏、页码、页面边框 |

如果一份文档主要依赖右栏的那些特性，桥不会假装没事：落在共同元素集之外的内容会按设计被静默丢弃。
需要完全保真的 DOCX 往返，请直接使用
[php-docx](https://github.com/dskripchenko/php-docx) 本身。

## 许可证

MIT。通过 Composer 依赖 `phpoffice/phpword`（LGPL-3.0）——正是 LGPL 所针对的动态链接场景。
