# dskripchenko/php-docx-phpword

> **HTML-слой для конвейеров на PHPWord.** Типизированный мост между объектной
> моделью [PHPWord](https://github.com/PHPOffice/PHPWord) и деревом
> [php-docx](https://github.com/dskripchenko/php-docx): выгрузка объектов
> `PhpWord` в чистый HTML (того самого экспорта, которого в PHPWord нет), импорт
> HTML в `PhpWord`, чтение произвольного DOCX прямо в модели PHPWord, поиск
> переменных шаблона. Работать при этом можно в привычном API.

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · **Русский** · [中文](../zh/README.md)

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/php-docx-phpword/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/php-docx-phpword/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/php-docx-phpword?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/php-docx-phpword)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)

## Установка

```bash
composer require dskripchenko/php-docx-phpword
```

Пакет ставит `dskripchenko/php-docx` (MIT, без зависимостей) рядом с уже
имеющимся `phpoffice/phpword`. Сам пакет под MIT; PHPWord остаётся под LGPL-3.0 —
обычная зависимость Composer, в вашем коде ничего не меняется.

## Что даёт

### PhpWord → HTML (главное)

```php
use Dskripchenko\PhpDocxPhpWord\PhpWordBridge;

$html = PhpWordBridge::toHtml($phpWord);   // чистый HTML, стили инлайном
```

### HTML → PhpWord

```php
$phpWord = PhpWordBridge::fromHtml('<h1>Отчёт</h1><p>Текст с <b>выделением</b>.</p>');
// … дальше — родные вызовы и писатели PHPWord
```

### Чтение любого DOCX в объекты PhpWord

```php
$phpWord = PhpWordBridge::readToPhpWord(file_get_contents('from-word.docx'));
```

Используется ридер php-docx (полный каскад стилей; документы из Word, Google
Docs, LibreOffice и PHPWord — точность измеряется на
[внешнем корпусе](https://github.com/dskripchenko/php-docx/blob/main/docs/READER-FIDELITY.md)),
и результат раскладывается в модели PHPWord. Собственный ридер PHPWord при этом
не участвует.

### Поиск переменных шаблона

```php
$variables = PhpWordBridge::detectVariables($phpWordOrDocxBytes);
// MERGEFIELD, элементы управления SDT, шаблоны {{x}} / ${x} / %x%
```

### Выход на уровень дерева

`PhpWordBridge::toDocument($phpWord)` и `PhpWordBridge::fromDocument($document)`
открывают само отображение — для конвейеров, которые дальше продолжаются уже в
php-docx.

## Общее множество элементов (честная граница)

Мост переносит то, что выразимо в **обеих** моделях:

| Переносится | Не переносится (пропускается, не додумывается) |
|---|---|
| абзацы, текстовые прогоны (жирный, курсив, подчёркивание, зачёркивание, над- и подстрочный, цвет, кегль, имя шрифта) | именованные стили и стили темы (только вычисленные значения) |
| заголовки 1–6 | диаграммы, оглавление, чекбоксы, OLE |
| таблицы, включая `gridSpan` и `vMerge` | правки, комментарии |
| маркированные и нумерованные списки, в том числе вложенные | сноски и концевые сноски, формулы OMML |
| ссылки, изображения, разрывы строки и страницы | поля форм |
| колонтитулы по умолчанию | отдельные колонтитулы первой и чётных страниц, подложки |
| выравнивание, интервалы, отступы абзаца | тонкости геометрии разделов и страниц |

Если документ держится на правом столбце, мост не станет делать вид, что всё в
порядке: содержимое вне общего множества сознательно отбрасывается молча. Для
полного круга DOCX → DOCX оставайтесь в самом
[php-docx](https://github.com/dskripchenko/php-docx).

## Лицензия

MIT. Зависит от `phpoffice/phpword` (LGPL-3.0) через Composer — это ровно тот
случай динамического связывания, ради которого LGPL и написана.
