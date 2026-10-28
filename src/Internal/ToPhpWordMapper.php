<?php

declare(strict_types=1);

namespace Dskripchenko\PhpDocxPhpWord\Internal;

use Dskripchenko\PhpDocx\Document;
use Dskripchenko\PhpDocx\Element\Bookmark;
use Dskripchenko\PhpDocx\Element\Hyperlink;
use Dskripchenko\PhpDocx\Element\Image;
use Dskripchenko\PhpDocx\Element\LineBreak;
use Dskripchenko\PhpDocx\Element\ListNode;
use Dskripchenko\PhpDocx\Element\PageBreak;
use Dskripchenko\PhpDocx\Element\Paragraph;
use Dskripchenko\PhpDocx\Element\Run;
use Dskripchenko\PhpDocx\Element\Table as AstTable;
use Dskripchenko\PhpDocx\Style\Alignment;
use Dskripchenko\PhpDocx\Style\CellStyle;
use Dskripchenko\PhpDocx\Style\ParagraphStyle;
use Dskripchenko\PhpDocx\Section;
use Dskripchenko\PhpDocx\Style\HorizontalAnchor;
use Dskripchenko\PhpDocx\Style\ImageWrap;
use Dskripchenko\PhpDocx\Style\RunStyle;
use Dskripchenko\PhpDocx\Style\SectionStart;
use Dskripchenko\PhpDocx\Style\VerticalAnchor;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Image as ImageStyle;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;

/**
 * php-docx AST → PhpWord object model, on the shared element set: a PhpWord
 * section per php-docx section, with its page setup, start and headers and
 * footers of every kind. AST features outside PHPWord's model (text
 * watermarks) are skipped, never guessed.
 */
final class ToPhpWordMapper
{
    public function map(Document $document): PhpWord
    {
        $phpWord = new PhpWord;
        // Register default heading styles so addTitle() works.
        for ($level = 1; $level <= 6; $level++) {
            $phpWord->addTitleStyle($level, ['bold' => true, 'size' => 20 - $level * 2]);
        }
        foreach ($document->sections() as $i => $astSection) {
            $section = $phpWord->addSection($this->sectionStyle($astSection, isFirst: $i === 0));
            // The kinds share their names: default, first, even. A section
            // without one shows the previous section's, in both models.
            foreach ($astSection->allHeaders() as $type => $blocks) {
                $this->mapBlocks($section->addHeader($type), $blocks);
            }
            foreach ($astSection->allFooters() as $type => $blocks) {
                $this->mapBlocks($section->addFooter($type), $blocks);
            }
            if ($astSection->hasEvenPageHeaderOrFooter()) {
                $phpWord->getSettings()->setEvenAndOddHeaders(true);
            }
            $this->mapBlocks($section, $astSection->body);
        }

        return $phpWord;
    }

    /**
     * A section's page and start as PhpWord's section style (twips).
     *
     * @return array<string, mixed>
     */
    private function sectionStyle(Section $section, bool $isFirst): array
    {
        $page = $section->pageSetup;
        $style = [
            'pageSizeW' => $page->pageWidthTwips(),
            'pageSizeH' => $page->pageHeightTwips(),
            'orientation' => $page->effectiveOrientation()->value,
            'marginTop' => $page->marginTopTwips,
            'marginRight' => $page->marginRightTwips,
            'marginBottom' => $page->marginBottomTwips,
            'marginLeft' => $page->marginLeftTwips,
            'headerHeight' => $page->headerOffsetTwips,
            'footerHeight' => $page->footerOffsetTwips,
        ];
        // A new page is what a section does without a break type.
        if (! $isFirst && $section->start !== SectionStart::NextPage) {
            $style['breakType'] = $section->start->value;
        }

        return $style;
    }

    /**
     * @param  list<object>  $blocks
     */
    private function mapBlocks(AbstractContainer $container, array $blocks): void
    {
        foreach ($blocks as $block) {
            if ($block instanceof Paragraph) {
                $this->mapParagraph($container, $block);
            } elseif ($block instanceof AstTable) {
                $this->mapTable($container, $block);
            } elseif ($block instanceof ListNode) {
                $this->mapList($container, $block, 0);
            } elseif ($block instanceof PageBreak) {
                $container->addPageBreak();
            }
        }
    }

    private function mapParagraph(AbstractContainer $container, Paragraph $p): void
    {
        if ($p->headingLevel !== null && $container instanceof \PhpOffice\PhpWord\Element\Section) {
            $container->addTitle($this->plainText($p->children), max(1, min(6, $p->headingLevel)));

            return;
        }

        $textRun = $container->addTextRun($this->paragraphStyle($p->style));
        foreach ($p->children as $child) {
            if ($child instanceof Bookmark) {
                foreach ($child->children as $inner) {
                    if ($inner instanceof Run) {
                        $textRun->addText($inner->text, $this->fontStyle($inner->style));
                    }
                }
            } elseif ($child instanceof Run) {
                $textRun->addText($child->text, $this->fontStyle($child->style));
            } elseif ($child instanceof Hyperlink) {
                $textRun->addLink(
                    (string) ($child->href ?? '#'.$child->anchor),
                    $this->plainText($child->children),
                    array_merge($this->fontStyle($this->firstRunStyle($child->children)), ['underline' => 'single', 'color' => '0563C1']),
                );
            } elseif ($child instanceof LineBreak) {
                $textRun->addTextBreak();
            } elseif ($child instanceof Image) {
                // The bytes themselves: PhpWord takes a string that is not a
                // path for image data, and a data: URL for neither.
                $textRun->addImage($child->binary, [
                    'width' => (int) round($child->widthEmu / 12700),
                    'height' => (int) round($child->heightEmu / 12700),
                ] + $this->floating($child));
            }
        }
    }

    private function mapTable(AbstractContainer $container, AstTable $table): void
    {
        $wordTable = $container->addTable(['borderSize' => 4, 'borderColor' => '999999']);
        foreach ($table->rows as $row) {
            $wordTable->addRow();
            foreach ($row->cells as $cell) {
                $style = [];
                $cellStyle = $cell->style;
                if ($cellStyle->gridSpan > 1) {
                    $style['gridSpan'] = $cellStyle->gridSpan;
                }
                if ($cellStyle->vMergeContinue) {
                    $style['vMerge'] = 'continue';
                } elseif ($cellStyle->rowSpan > 1) {
                    $style['vMerge'] = 'restart';
                }
                $wordCell = $wordTable->addCell(null, $style);
                $this->mapBlocks($wordCell, $cell->children);
            }
        }
    }

    private function mapList(AbstractContainer $container, ListNode $list, int $depth): void
    {
        $type = $list->ordered ? ListItemStyle::TYPE_NUMBER : ListItemStyle::TYPE_BULLET_FILLED;
        foreach ($list->items as $item) {
            $text = $this->plainText($item->children);
            if ($text !== '') {
                $container->addListItem($text, $depth, null, $type);
            }
            if ($item->nestedList !== null) {
                $this->mapList($container, $item->nestedList, $depth + 1);
            }
        }
    }

    /**
     * @param  list<object>  $children
     */
    private function plainText(array $children): string
    {
        $out = '';
        foreach ($children as $child) {
            if ($child instanceof Run) {
                $out .= $child->text;
            } elseif ($child instanceof Hyperlink || $child instanceof Bookmark) {
                $out .= $this->plainText($child->children);
            }
        }

        return $out;
    }

    /**
     * @param  list<object>  $children
     */
    private function firstRunStyle(array $children): RunStyle
    {
        foreach ($children as $child) {
            if ($child instanceof Run) {
                return $child->style;
            }
        }

        return new RunStyle;
    }

    /**
     * @return array<string, mixed>
     */
    private function fontStyle(RunStyle $style): array
    {
        $font = [];
        if ($style->bold) {
            $font['bold'] = true;
        }
        if ($style->italic) {
            $font['italic'] = true;
        }
        if ($style->underline) {
            $font['underline'] = 'single';
        }
        if ($style->strikethrough) {
            $font['strikethrough'] = true;
        }
        if ($style->superscript) {
            $font['superScript'] = true;
        }
        if ($style->subscript) {
            $font['subScript'] = true;
        }
        if ($style->color !== null) {
            $font['color'] = strtoupper(ltrim($style->color, '#'));
        }
        if ($style->sizeHalfPoints !== null) {
            $font['size'] = $style->sizeHalfPoints / 2;
        }
        if ($style->fontFamily !== null) {
            $font['name'] = $style->fontFamily;
        }

        return $font;
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraphStyle(ParagraphStyle $style): array
    {
        $out = [];
        $alignment = match ($style->alignment) {
            Alignment::Center => Jc::CENTER,
            Alignment::End => Jc::END,
            Alignment::Justify => Jc::BOTH,
            Alignment::Distribute => 'distribute',
            default => null,
        };
        if ($alignment !== null) {
            $out['alignment'] = $alignment;
        }
        if ($style->spaceBeforeTwips !== 0) {
            $out['spaceBefore'] = $style->spaceBeforeTwips;
        }
        if ($style->spaceAfterTwips !== 0) {
            $out['spaceAfter'] = $style->spaceAfterTwips;
        }
        if ($style->indentLeftTwips !== 0 || $style->indentRightTwips !== 0) {
            $out['indentation'] = array_filter([
                'left' => $style->indentLeftTwips ?: null,
                'right' => $style->indentRightTwips ?: null,
            ]);
        }

        return $out;
    }

    /**
     * A floating picture's position as PhpWord's image style (points, as its
     * sizes); nothing for a picture in the line of text.
     *
     * @return array<string, mixed>
     */
    private function floating(Image $image): array
    {
        $position = $image->effectivePosition();
        if ($position === null) {
            return [];
        }

        return [
            'positioning' => ImageStyle::POSITION_RELATIVE,
            'posHorizontalRel' => match ($position->horizontalFrom) {
                HorizontalAnchor::Page => ImageStyle::POSITION_RELATIVE_TO_PAGE,
                HorizontalAnchor::Margin => ImageStyle::POSITION_RELATIVE_TO_MARGIN,
                HorizontalAnchor::Column => ImageStyle::POSITION_RELATIVE_TO_COLUMN,
                HorizontalAnchor::Character => ImageStyle::POSITION_RELATIVE_TO_CHAR,
            },
            'posVerticalRel' => match ($position->verticalFrom) {
                VerticalAnchor::Page => ImageStyle::POSITION_RELATIVE_TO_PAGE,
                VerticalAnchor::Margin => ImageStyle::POSITION_RELATIVE_TO_MARGIN,
                VerticalAnchor::Paragraph => ImageStyle::POSITION_RELATIVE_TO_TEXT,
                VerticalAnchor::Line => ImageStyle::POSITION_RELATIVE_TO_LINE,
            },
            // The alignments share their names; without one, the offset.
            'posHorizontal' => $position->horizontalAlign->value ?? ImageStyle::POSITION_ABSOLUTE,
            'posVertical' => $position->verticalAlign->value ?? ImageStyle::POSITION_ABSOLUTE,
            'marginLeft' => round($position->xEmu / 12700, 2),
            'marginTop' => round($position->yEmu / 12700, 2),
            'wrappingStyle' => match ($position->wrap) {
                ImageWrap::None => $position->behindText ? ImageStyle::WRAPPING_STYLE_BEHIND : ImageStyle::WRAPPING_STYLE_INFRONT,
                ImageWrap::Square => ImageStyle::WRAPPING_STYLE_SQUARE,
                ImageWrap::TopAndBottom => 'topAndBottom',
            },
        ];
    }

}
