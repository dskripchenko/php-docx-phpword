# Changelog

All notable changes to `dskripchenko/php-docx-phpword` are documented in
this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] — 2026-07-18

Initial release.

### Added
- `PhpWordBridge::toHtml(PhpWord)` — PhpWord objects to clean HTML with
  inline styles (the export PHPWord itself does not offer).
- `PhpWordBridge::fromHtml(string)` — HTML into PhpWord objects, ready
  for PHPWord-native post-processing and writers.
- `PhpWordBridge::readToPhpWord(string)` — arbitrary DOCX bytes into
  PhpWord models via php-docx's reader (full style cascade).
- `PhpWordBridge::detectVariables(PhpWord|string)` — MERGEFIELD, SDT
  content controls and `{{x}}`/`${x}`/`%x%` patterns.
- `toDocument()` / `fromDocument()` AST escape hatches.
- Mapping covers the shared element set (paragraphs/runs with
  formatting, headings, tables with gridSpan/vMerge, nested lists,
  hyperlinks, images, breaks, default headers/footers); everything
  outside is skipped, never guessed — see the README boundary table.
