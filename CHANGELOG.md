# Changelog

All notable changes to `dskripchenko/php-docx-phpword` are documented in
this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] — 2026-10-28

Requires `dskripchenko/php-docx` ^1.18.

### Added
- Sections both ways: a php-docx section per PhpWord section, with its page
  size and orientation (a preset paper size, else the size as it is),
  margins, header and footer offsets and start (`breakType`: new page,
  continuous, even or odd page). Before, every section was folded into one.
- Headers and footers of every kind both ways: default, first-page and
  even-page (`Header::FIRST`, `Header::EVEN`; even pages turn PhpWord's
  `evenAndOddHeaders` on). Before, only the default ones were carried, and
  PhpWord's headers of every kind were merged into one.
- Floating pictures both ways: php-docx's `Image::$position` ↔ PhpWord's
  image style (`positioning`, `posHorizontal`/`posVertical`, their
  `…Rel`, `marginLeft`/`marginTop`, `wrappingStyle`). Origins php-docx has
  no case for are read as the nearest one.

### Fixed
- Pictures from a php-docx document reach PhpWord: they were handed over
  as a `data:` URL, which PhpWord 1.x rejects as an invalid image, so any
  document with a picture failed in `fromDocument()`. They go as bytes now.

### Changed
- `fromDocument()` sets each PhpWord section's page from the document's
  page setup; before, PhpWord's default page was used.

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
