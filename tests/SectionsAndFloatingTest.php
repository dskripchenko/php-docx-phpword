<?php

declare(strict_types=1);

namespace Dskripchenko\PhpDocxPhpWord\Tests;

use Dskripchenko\PhpDocx\Build\DocumentBuilder;
use Dskripchenko\PhpDocx\Document;
use Dskripchenko\PhpDocx\Element\Image;
use Dskripchenko\PhpDocx\Element\ImageFormat;
use Dskripchenko\PhpDocx\Element\Paragraph;
use Dskripchenko\PhpDocx\Reader\DocxReader;
use Dskripchenko\PhpDocx\Section;
use Dskripchenko\PhpDocx\Style\HorizontalAnchor;
use Dskripchenko\PhpDocx\Style\ImagePosition;
use Dskripchenko\PhpDocx\Style\ImageWrap;
use Dskripchenko\PhpDocx\Style\Orientation;
use Dskripchenko\PhpDocx\Style\PageSetup;
use Dskripchenko\PhpDocx\Style\PaperSize;
use Dskripchenko\PhpDocx\Style\PositionAlign;
use Dskripchenko\PhpDocx\Style\SectionStart;
use Dskripchenko\PhpDocx\Style\VerticalAnchor;
use Dskripchenko\PhpDocxPhpWord\PhpWordBridge;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Image as WordImage;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SectionsAndFloatingTest extends TestCase
{
    private static function png(): string
    {
        $gd = imagecreatetruecolor(4, 2);
        ob_start();
        imagepng($gd);

        return (string) ob_get_clean();
    }

    private function threeSections(): Document
    {
        return DocumentBuilder::new()
            ->header(fn ($h) => $h->paragraph('Report'))
            ->firstHeader(fn ($h) => $h->paragraph('Cover'))
            ->paragraph('Portrait pages')
            ->newSection(new PageSetup(orientation: Orientation::Landscape))
            ->header(fn ($h) => $h->paragraph('Appendix'))
            ->paragraph('Landscape pages')
            ->newSection(new PageSetup(widthTwips: 8391, heightTwips: 11906), SectionStart::Continuous)
            ->paragraph('A custom size, on the same page')
            ->build();
    }

    #[Test]
    public function every_section_becomes_a_phpword_section(): void
    {
        $word = PhpWordBridge::fromDocument($this->threeSections());
        $sections = $word->getSections();

        self::assertCount(3, $sections);
        self::assertSame('portrait', $sections[0]->getStyle()->getOrientation());
        self::assertSame('landscape', $sections[1]->getStyle()->getOrientation());
        self::assertSame(PaperSize::A4->heightTwips(), (int) $sections[1]->getStyle()->getPageSizeW());
        self::assertSame(8391, (int) $sections[2]->getStyle()->getPageSizeW());
        self::assertSame(11906, (int) $sections[2]->getStyle()->getPageSizeH());
        self::assertNull($sections[1]->getStyle()->getBreakType());
        self::assertSame('continuous', $sections[2]->getStyle()->getBreakType());

        $types = array_map(fn (Header $h) => $h->getType(), $sections[0]->getHeaders());
        self::assertEqualsCanonicalizing([Header::AUTO, Header::FIRST], array_values($types));
        // The third section sets no header: it shows the second's, in both models.
        self::assertSame([], $sections[2]->getHeaders());
    }

    #[Test]
    public function sections_survive_phpword_s_own_writer(): void
    {
        $word = PhpWordBridge::fromDocument($this->threeSections());
        $path = tempnam(sys_get_temp_dir(), 'bridge').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($path);
        $read = (new DocxReader)->read((string) file_get_contents($path));
        unlink($path);

        $sections = $read->sections();
        self::assertCount(3, $sections);
        self::assertSame(Orientation::Landscape, $sections[1]->pageSetup->effectiveOrientation());
        self::assertSame(SectionStart::Continuous, $sections[2]->start);
        self::assertSame('Landscape pages', $this->text($sections[1]));
        self::assertSame('Cover', $this->text($sections[0], 'firstHeader'));
    }

    #[Test]
    public function phpword_sections_become_document_sections(): void
    {
        $word = new PhpWord;
        $first = $word->addSection();
        $first->addHeader()->addText('Default');
        $first->addHeader(Header::FIRST)->addText('First');
        $first->addText('One');
        $second = $word->addSection(['orientation' => 'landscape', 'breakType' => 'oddPage', 'marginTop' => 720]);
        $second->addText('Two');

        $document = PhpWordBridge::toDocument($word);
        $sections = $document->sections();

        self::assertCount(2, $sections);
        self::assertSame('One', $this->text($sections[0]));
        self::assertSame('Default', $this->text($sections[0], 'header'));
        self::assertSame('First', $this->text($sections[0], 'firstHeader'));
        self::assertSame('Two', $this->text($sections[1]));
        self::assertSame(Orientation::Landscape, $sections[1]->pageSetup->orientation);
        self::assertSame(PaperSize::A4, $sections[1]->pageSetup->paperSize);
        self::assertSame(720, $sections[1]->pageSetup->marginTopTwips);
        self::assertSame(SectionStart::OddPage, $sections[1]->start);
    }

    #[Test]
    public function a_floating_picture_keeps_its_place_both_ways(): void
    {
        $position = new ImagePosition(
            horizontalFrom: HorizontalAnchor::Page,
            verticalFrom: VerticalAnchor::Page,
            yEmu: 254000,
            horizontalAlign: PositionAlign::Right,
            behindText: true,
        );
        $image = new Image(self::png(), ImageFormat::Png, 1270000, 635000, position: $position);
        $word = PhpWordBridge::fromDocument(new Document(new Section(body: [new Paragraph([$image])])));

        $run = $word->getSections()[0]->getElements()[0];
        self::assertInstanceOf(TextRun::class, $run);
        $wordImage = $run->getElements()[0];
        self::assertInstanceOf(WordImage::class, $wordImage);
        $style = $wordImage->getStyle();
        self::assertSame('page', $style->getPosHorizontalRel());
        self::assertSame('right', $style->getPosHorizontal());
        self::assertSame('absolute', $style->getPosVertical());
        self::assertEquals(20, $style->getMarginTop());
        self::assertSame('behind', $style->getWrappingStyle());

        $back = PhpWordBridge::toDocument($word)->section->body[0];
        self::assertInstanceOf(Paragraph::class, $back);
        $backImage = $back->children[0];
        self::assertInstanceOf(Image::class, $backImage);
        self::assertEquals($position, $backImage->position);
    }

    #[Test]
    public function a_picture_in_the_line_of_text_stays_there(): void
    {
        $image = new Image(self::png(), ImageFormat::Png, 1270000, 635000);
        $word = PhpWordBridge::fromDocument(new Document(new Section(body: [new Paragraph([$image])])));
        $back = PhpWordBridge::toDocument($word)->section->body[0];

        self::assertInstanceOf(Paragraph::class, $back);
        self::assertInstanceOf(Image::class, $back->children[0]);
        self::assertNull($back->children[0]->position);
    }

    private function text(Section $section, string $part = 'body'): string
    {
        $out = '';
        foreach ($section->{$part} as $block) {
            if ($block instanceof Paragraph) {
                foreach ($block->children as $child) {
                    $out .= $child instanceof \Dskripchenko\PhpDocx\Element\Run ? $child->text : '';
                }
            }
        }

        return $out;
    }
}
