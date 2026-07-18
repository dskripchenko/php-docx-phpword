<?php

declare(strict_types=1);

namespace Dskripchenko\PhpDocxPhpWord;

use Dskripchenko\PhpDocx\Document;
use Dskripchenko\PhpDocx\Html\Converter;
use Dskripchenko\PhpDocx\Html\Serializer;
use Dskripchenko\PhpDocx\Reader\DocxPackageReader;
use Dskripchenko\PhpDocx\Reader\DocxReader;
use Dskripchenko\PhpDocx\Reader\VariableDetector;
use Dskripchenko\PhpDocxPhpWord\Internal\FromPhpWordMapper;
use Dskripchenko\PhpDocxPhpWord\Internal\ToPhpWordMapper;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Typed bridge between PHPWord's object model and php-docx's AST — the
 * HTML layer for PHPWord pipelines.
 *
 * Works on the SHARED element set: paragraphs and runs (bold, italic,
 * underline, strikethrough, super/subscript, colour, size, font name),
 * headings 1–6, tables (gridSpan / vMerge), bullet and numbered lists
 * (nested), hyperlinks, images, line and page breaks, default
 * headers/footers, paragraph alignment/spacing/indent. Features outside
 * the set (charts, TOC, tracked changes, comments, footnotes, form
 * fields, named styles) are skipped, never guessed.
 */
final class PhpWordBridge
{
    /**
     * PhpWord object → clean HTML with inline styles. The HTML export
     * PHPWord itself does not offer.
     */
    public static function toHtml(PhpWord $phpWord): string
    {
        return (new Serializer)
            ->serialize(self::toDocument($phpWord))
            ->bodyHtml;
    }

    /**
     * HTML (inline styles) → PhpWord object, ready for PHPWord-native
     * post-processing and writers.
     */
    public static function fromHtml(string $html): PhpWord
    {
        return (new ToPhpWordMapper)->map((new Converter)->fromHtml($html));
    }

    /**
     * Arbitrary DOCX bytes (Word, Google Docs, LibreOffice, PHPWord …)
     * → PhpWord object, via php-docx's reader and its full style
     * cascade. PHPWord's own reader is not involved.
     */
    public static function readToPhpWord(string $docxBytes): PhpWord
    {
        return (new ToPhpWordMapper)->map((new DocxReader)->read($docxBytes));
    }

    /**
     * Template-variable detection (MERGEFIELD, SDT content controls,
     * `{{x}}` / `${x}` / `%x%` text patterns) over a PhpWord object or
     * raw DOCX bytes.
     *
     * @return list<\Dskripchenko\PhpDocx\Reader\DetectedVariable>
     */
    public static function detectVariables(PhpWord|string $source): array
    {
        $bytes = $source instanceof PhpWord ? self::toBytes($source) : $source;

        return (new VariableDetector)->detect((new DocxPackageReader)->read($bytes));
    }

    /**
     * PhpWord object → php-docx AST (the shared-set mapping used by
     * toHtml(); exposed for pipelines that continue in php-docx).
     */
    public static function toDocument(PhpWord $phpWord): Document
    {
        return (new FromPhpWordMapper)->map($phpWord);
    }

    /**
     * php-docx AST → PhpWord object.
     */
    public static function fromDocument(Document $document): PhpWord
    {
        return (new ToPhpWordMapper)->map($document);
    }

    private static function toBytes(PhpWord $phpWord): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpword-bridge-');
        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
