<?php

declare(strict_types=1);

namespace Dskripchenko\PhpDocxPhpWord\Internal;

use Dskripchenko\PhpDocx\Document;
use Dskripchenko\PhpDocx\Element\BlockElement;
use Dskripchenko\PhpDocx\Element\Hyperlink;
use Dskripchenko\PhpDocx\Element\Image;
use Dskripchenko\PhpDocx\Element\InlineElement;
use Dskripchenko\PhpDocx\Element\ImageFormat;
use Dskripchenko\PhpDocx\Element\LineBreak;
use Dskripchenko\PhpDocx\Element\ListItem as AstListItem;
use Dskripchenko\PhpDocx\Element\ListNode;
use Dskripchenko\PhpDocx\Element\PageBreak;
use Dskripchenko\PhpDocx\Element\Paragraph;
use Dskripchenko\PhpDocx\Element\Run;
use Dskripchenko\PhpDocx\Element\Table as AstTable;
use Dskripchenko\PhpDocx\Element\TableCell;
use Dskripchenko\PhpDocx\Element\TableRow;
use Dskripchenko\PhpDocx\Section;
use Dskripchenko\PhpDocx\Style\Alignment;
use Dskripchenko\PhpDocx\Style\CellStyle;
use Dskripchenko\PhpDocx\Style\ParagraphStyle;
use Dskripchenko\PhpDocx\Style\RunStyle;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\ListItem as WordListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\PageBreak as WordPageBreak;
use PhpOffice\PhpWord\Element\PreserveText;
use PhpOffice\PhpWord\Element\Table as WordTable;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\Image as WordImage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use PhpOffice\PhpWord\Style\Paragraph as WordParagraphStyle;

/**
 * PhpWord object model → php-docx AST, on the shared element set (see
 * the package README for the exact boundary). PHPWord-specific features
 * outside the set are skipped, never guessed.
 */
final class FromPhpWordMapper
{
    public function map(PhpWord $phpWord): Document
    {
        $body = [];
        $header = [];
        $footer = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getHeaders() as $h) {
                $header = array_merge($header, $this->mapElements($h->getElements()));
            }
            foreach ($section->getFooters() as $f) {
                $footer = array_merge($footer, $this->mapElements($f->getElements()));
            }
            $body = array_merge($body, $this->mapElements($section->getElements()));
        }

        return new Document(new Section(body: $body, header: $header, footer: $footer));
    }

    /**
     * @param  AbstractElement[]  $elements
     * @return list<BlockElement>
     */
    private function mapElements(array $elements): array
    {
        $blocks = [];
        /** @var list<array{depth: int, item: AstListItem, ordered: bool}> $pendingList */
        $pendingList = [];

        $flushList = function () use (&$blocks, &$pendingList): void {
            if ($pendingList !== []) {
                $blocks[] = $this->buildListTree($pendingList);
                $pendingList = [];
            }
        };

        foreach ($elements as $el) {
            if ($el instanceof WordListItem) {
                $pendingList[] = [
                    'depth' => $el->getDepth(),
                    'item' => new AstListItem([
                        new Run($el->getTextObject()->getText() ?? '', $this->mapFont($el->getTextObject()->getFontStyle())),
                    ]),
                    'ordered' => $this->isOrderedListStyle($el->getStyle()),
                ];

                continue;
            }
            if ($el instanceof ListItemRun) {
                $pendingList[] = [
                    'depth' => $el->getDepth(),
                    'item' => new AstListItem($this->mapInline($el->getElements())),
                    'ordered' => $this->isOrderedListStyle($el->getStyle()),
                ];

                continue;
            }

            $flushList();

            if ($el instanceof Title) {
                $text = $el->getText();
                $children = $text instanceof TextRun
                    ? $this->mapInline($text->getElements())
                    : [new Run((string) $text)];
                $blocks[] = new Paragraph($children, headingLevel: max(1, min(6, (int) $el->getDepth())));
            } elseif ($el instanceof TextRun) {
                $blocks[] = new Paragraph(
                    $this->mapInline($el->getElements()),
                    $this->mapParagraphStyle($el->getParagraphStyle()),
                );
            } elseif ($el instanceof Text) {
                $blocks[] = new Paragraph(
                    [new Run($el->getText() ?? '', $this->mapFont($el->getFontStyle()))],
                    $this->mapParagraphStyle($el->getParagraphStyle()),
                );
            } elseif ($el instanceof PreserveText) {
                $blocks[] = new Paragraph([new Run(implode('', (array) $el->getText()))]);
            } elseif ($el instanceof Link) {
                $blocks[] = new Paragraph([$this->mapLink($el)]);
            } elseif ($el instanceof WordImage) {
                $image = $this->mapImage($el);
                if ($image !== null) {
                    $blocks[] = new Paragraph([$image]);
                }
            } elseif ($el instanceof WordTable) {
                $blocks[] = $this->mapTable($el);
            } elseif ($el instanceof WordPageBreak) {
                $blocks[] = new PageBreak;
            } elseif ($el instanceof TextBreak) {
                $blocks[] = new Paragraph([]);
            }
            // Everything else (charts, TOC, checkboxes, OLE …) is outside
            // the shared set and intentionally skipped.
        }
        $flushList();

        return $blocks;
    }

    /**
     * @param  AbstractElement[]  $elements
     * @return list<InlineElement>
     */
    private function mapInline(array $elements): array
    {
        $out = [];
        foreach ($elements as $el) {
            if ($el instanceof Text) {
                $out[] = new Run($el->getText() ?? '', $this->mapFont($el->getFontStyle()));
            } elseif ($el instanceof Link) {
                $out[] = $this->mapLink($el);
            } elseif ($el instanceof WordImage) {
                $img = $this->mapImage($el);
                if ($img !== null) {
                    $out[] = $img;
                }
            } elseif ($el instanceof TextBreak) {
                $out[] = new LineBreak;
            }
        }

        return $out;
    }

    private function mapLink(Link $el): Hyperlink
    {
        return new Hyperlink(
            $el->getSource(),
            [new Run($el->getText(), $this->mapFont($el->getFontStyle()))],
        );
    }

    private function mapImage(WordImage $el): ?Image
    {
        $data = $el->getImageStringData(true);
        if (! is_string($data) || $data === '') {
            return null;
        }
        $binary = base64_decode($data, true);
        if ($binary === false) {
            return null;
        }
        $format = match ($el->getImageType()) {
            'image/png' => ImageFormat::Png,
            'image/jpeg', 'image/jpg' => ImageFormat::Jpeg,
            'image/gif' => ImageFormat::Gif,
            'image/bmp' => ImageFormat::Bmp,
            default => null,
        };
        if ($format === null) {
            return null;
        }
        $style = $el->getStyle();
        $wPt = (float) ($style?->getWidth() ?? 96.0);
        $hPt = (float) ($style?->getHeight() ?? 96.0);

        return new Image($binary, $format, (int) round($wPt * 12700), (int) round($hPt * 12700));
    }

    private function mapTable(WordTable $el): AstTable
    {
        $rows = [];
        foreach ($el->getRows() as $row) {
            $cells = [];
            foreach ($row->getCells() as $cell) {
                $cells[] = $this->mapCell($cell);
            }
            $rows[] = new TableRow($cells);
        }

        return new AstTable($rows);
    }

    private function mapCell(Cell $cell): TableCell
    {
        $style = $cell->getStyle();
        $gridSpan = (int) ($style?->getGridSpan() ?? 1);
        $vMerge = $style?->getVMerge();

        return new TableCell(
            $this->mapElements($cell->getElements()),
            new CellStyle(
                gridSpan: max(1, $gridSpan),
                rowSpan: $vMerge === 'restart' ? 2 : 1,
                vMergeContinue: $vMerge === 'continue',
            ),
        );
    }

    /**
     * @param  non-empty-list<array{depth: int, item: AstListItem, ordered: bool}>  $flat
     */
    private function buildListTree(array $flat): ListNode
    {
        $ordered = $flat[0]['ordered'];
        $minDepth = min(array_column($flat, 'depth'));
        [$items] = $this->buildLevel($flat, 0, $minDepth);

        return new ListNode($items, ordered: $ordered);
    }

    /**
     * @param  list<array{depth: int, item: AstListItem, ordered: bool}>  $flat
     * @return array{0: list<AstListItem>, 1: int}
     */
    private function buildLevel(array $flat, int $index, int $depth): array
    {
        $items = [];
        while ($index < count($flat)) {
            $entry = $flat[$index];
            if ($entry['depth'] < $depth) {
                break;
            }
            if ($entry['depth'] > $depth) {
                [$nested, $index] = $this->buildLevel($flat, $index, $entry['depth']);
                $host = $items === [] ? null : count($items) - 1;
                $node = new ListNode($nested, ordered: $entry['ordered']);
                if ($host === null) {
                    $items[] = new AstListItem([], $node);
                } else {
                    $items[$host] = new AstListItem($items[$host]->children, $node);
                }

                continue;
            }
            $items[] = $entry['item'];
            $index++;
        }

        return [array_values($items), $index];
    }

    private function isOrderedListStyle(?ListItemStyle $style): bool
    {
        if ($style === null) {
            return false;
        }

        return in_array($style->getListType(), [
            ListItemStyle::TYPE_NUMBER,
            ListItemStyle::TYPE_NUMBER_NESTED,
            ListItemStyle::TYPE_ALPHANUM,
        ], true);
    }

    private function mapFont(Font|string|null $font): RunStyle
    {
        if (! $font instanceof Font) {
            // Named styles are outside the shared set (no style sheet here).
            return new RunStyle;
        }

        $size = $font->getSize();

        return new RunStyle(
            sizeHalfPoints: $size > 0 ? (int) round((float) $size * 2) : null,
            color: $this->normalizeColor($font->getColor()),
            fontFamily: $font->getName(),
            bold: (bool) $font->isBold(),
            italic: (bool) $font->isItalic(),
            underline: $font->getUnderline() !== Font::UNDERLINE_NONE,
            strikethrough: (bool) $font->isStrikethrough(),
            superscript: (bool) $font->isSuperScript(),
            subscript: (bool) $font->isSubScript(),
        );
    }

    private function mapParagraphStyle(WordParagraphStyle|string|null $style): ParagraphStyle
    {
        if (! $style instanceof WordParagraphStyle) {
            return new ParagraphStyle;
        }

        return new ParagraphStyle(
            alignment: match ($style->getAlignment()) {
                Jc::CENTER => Alignment::Center,
                Jc::END, 'right' => Alignment::End,
                Jc::BOTH => Alignment::Justify,
                'distribute' => Alignment::Distribute,
                default => Alignment::Start,
            },
            spaceBeforeTwips: (int) ($style->getSpaceBefore() ?? 0),
            spaceAfterTwips: (int) ($style->getSpaceAfter() ?? 0),
            indentLeftTwips: (int) ($style->getIndentation()?->getLeft() ?? 0),
            indentRightTwips: (int) ($style->getIndentation()?->getRight() ?? 0),
        );
    }

    private function normalizeColor(?string $color): ?string
    {
        if ($color === null || $color === '' || strtolower($color) === 'auto') {
            return null;
        }

        return strtolower(ltrim($color, '#'));
    }
}
