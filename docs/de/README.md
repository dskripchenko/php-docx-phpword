# dskripchenko/php-docx-phpword

> **Die HTML-Ebene für PHPWord-Pipelines.** Eine typisierte Brücke zwischen dem
> Objektmodell von [PHPWord](https://github.com/PHPOffice/PHPWord) und dem AST
> von [php-docx](https://github.com/dskripchenko/php-docx): `PhpWord`-Objekte
> nach sauberem HTML exportieren (den Export, den PHPWord nicht bietet), HTML in
> `PhpWord` importieren, beliebige DOCX direkt in PHPWord-Modelle einlesen,
> Vorlagenvariablen erkennen. Und dabei in der gewohnten API bleiben.

> 🌐 [English](../../README.md) · **Deutsch** · [Русский](../ru/README.md) · [中文](../zh/README.md)

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/php-docx-phpword/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/php-docx-phpword/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/php-docx-phpword?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/php-docx-phpword)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)

## Installation

```bash
composer require dskripchenko/php-docx-phpword
```

Bringt `dskripchenko/php-docx` (MIT, ohne Abhängigkeiten) neben Ihr vorhandenes
`phpoffice/phpword`. Dieses Paket steht unter MIT; PHPWord bleibt LGPL-3.0 —
eine ganz normale Composer-Abhängigkeit, an Ihrem Code ändert sich nichts.

## Was Sie bekommen

### PhpWord → HTML (das Kernstück)

```php
use Dskripchenko\PhpDocxPhpWord\PhpWordBridge;

$html = PhpWordBridge::toHtml($phpWord);   // sauberes HTML, Styles inline
```

### HTML → PhpWord

```php
$phpWord = PhpWordBridge::fromHtml('<h1>Bericht</h1><p>Text mit <b>Fettdruck</b>.</p>');
// … weiter mit PHPWord-eigenen Aufrufen und Writern
```

### Beliebige DOCX in PhpWord-Objekte einlesen

```php
$phpWord = PhpWordBridge::readToPhpWord(file_get_contents('from-word.docx'));
```

Verwendet den Reader von php-docx (vollständige Stilkaskade; Ausgaben von Word,
Google Docs, LibreOffice und PHPWord — die Treue wird an einem
[externen Korpus](https://github.com/dskripchenko/php-docx/blob/main/docs/READER-FIDELITY.md)
gemessen) und bildet das Ergebnis auf PHPWord-Modelle ab. PHPWords eigener
Reader ist nicht beteiligt.

### Vorlagenvariablen erkennen

```php
$variables = PhpWordBridge::detectVariables($phpWordOrDocxBytes);
// MERGEFIELD, SDT-Inhaltssteuerelemente, Muster {{x}} / ${x} / %x%
```

### Zugang zur AST-Ebene

`PhpWordBridge::toDocument($phpWord)` und
`PhpWordBridge::fromDocument($document)` legen die Abbildung selbst offen — für
Pipelines, die anschließend in php-docx weiterlaufen.

## Die gemeinsame Elementmenge (die ehrliche Grenze)

Die Brücke überträgt, was **beide** Modelle ausdrücken können:

| Wird übernommen | Wird nicht übernommen (übersprungen, nie geraten) |
|---|---|
| Absätze, Text-Runs (fett, kursiv, unterstrichen, durchgestrichen, hoch-/tiefgestellt, Farbe, Größe, Schriftname) | benannte Stile und Themenstile (nur aufgelöste Werte) |
| Überschriften 1–6 | Diagramme, Inhaltsverzeichnis, Kontrollkästchen, OLE |
| Tabellen inkl. `gridSpan` / `vMerge` | Änderungsverfolgung, Kommentare |
| Aufzählungen und nummerierte Listen (auch verschachtelt) | Fuß- und Endnoten, OMML-Formeln |
| Hyperlinks, Bilder (im Text oder frei positioniert: Position, Umbruch, vor oder hinter dem Text), Zeilen- und Seitenumbrüche | Formularfelder |
| Abschnitte (Seitengröße und -ausrichtung, Ränder, Abschnittsbeginn) mit Standard-Kopf-/Fußzeilen sowie Varianten für erste und gerade Seiten | Wasserzeichen |
| Absatzausrichtung, Abstände, Einzüge | Spalten im Abschnitt, Seitennummerierung, Seitenrahmen |

Stützt sich ein Dokument auf die rechte Spalte, tut die Brücke nicht so, als
wäre alles in Ordnung: Inhalte außerhalb der gemeinsamen Menge werden bewusst
stillschweigend verworfen. Für verlustfreie DOCX-Rundläufe bleiben Sie in
[php-docx](https://github.com/dskripchenko/php-docx) selbst.

## Lizenz

MIT. Hängt über Composer von `phpoffice/phpword` (LGPL-3.0) ab — genau der Fall
dynamischen Bindens, für den die LGPL geschrieben wurde.
