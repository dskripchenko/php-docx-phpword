# dskripchenko/php-docx-phpword

> **The HTML layer for PHPWord pipelines.** A typed bridge between
> [PHPWord](https://github.com/PHPOffice/PHPWord)'s object model and
> [php-docx](https://github.com/dskripchenko/php-docx)'s AST — export
> `PhpWord` objects to clean HTML (the export PHPWord doesn't offer),
> import HTML into `PhpWord`, read arbitrary DOCX straight into PHPWord
> models, detect template variables. Keep working in the API you know.

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/php-docx-phpword/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/php-docx-phpword/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/php-docx-phpword?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/php-docx-phpword)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)

## Installation

```bash
composer require dskripchenko/php-docx-phpword
```

Brings `dskripchenko/php-docx` (MIT, zero-dep) next to your existing
`phpoffice/phpword`. This package is MIT; PHPWord stays LGPL-3.0 — a
regular Composer dependency, nothing about your code changes.

## What you get

### PhpWord → HTML (the killer feature)

```php
use Dskripchenko\PhpDocxPhpWord\PhpWordBridge;

$html = PhpWordBridge::toHtml($phpWord);   // clean HTML, inline styles
```

### HTML → PhpWord

```php
$phpWord = PhpWordBridge::fromHtml('<h1>Report</h1><p>Body with <b>bold</b>.</p>');
// … continue with PHPWord-native calls and writers
```

### Read any DOCX into PhpWord objects

```php
$phpWord = PhpWordBridge::readToPhpWord(file_get_contents('from-word.docx'));
```

Uses php-docx's reader (full style cascade, Word / Google Docs /
LibreOffice / PHPWord output — fidelity is tracked on an
[external corpus](https://github.com/dskripchenko/php-docx/blob/main/docs/READER-FIDELITY.md)),
then maps into PHPWord models. PHPWord's own reader is not involved.

### Detect template variables

```php
$variables = PhpWordBridge::detectVariables($phpWordOrDocxBytes);
// MERGEFIELD, SDT content controls, {{x}} / ${x} / %x% patterns
```

### AST escape hatches

`PhpWordBridge::toDocument($phpWord)` and
`PhpWordBridge::fromDocument($document)` expose the underlying mapping
for pipelines that continue in php-docx.

## The shared element set (the honest boundary)

The bridge maps what **both** models can express:

| Carried across | Not carried (skipped, never guessed) |
|---|---|
| paragraphs, text runs (bold, italic, underline, strikethrough, super/subscript, colour, size, font name) | named/theme styles (resolved values only) |
| headings 1–6 | charts, TOC, checkboxes, OLE |
| tables incl. `gridSpan` / `vMerge` | tracked changes, comments |
| bullet / numbered lists (nested) | footnotes/endnotes, OMML math |
| hyperlinks, images, line/page breaks | form fields |
| default headers/footers | first/even header variants, watermarks |
| paragraph alignment, spacing, indents | section/page geometry details |

If a document leans on the right-hand column, the bridge will not
pretend otherwise — content outside the set is dropped silently by
design. For full-fidelity DOCX round-trips stay in
[php-docx](https://github.com/dskripchenko/php-docx) itself.

## License

MIT. Depends on `phpoffice/phpword` (LGPL-3.0) via Composer — the
standard dynamic-linking case the LGPL is written for.
