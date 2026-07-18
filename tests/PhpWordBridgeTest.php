<?php

declare(strict_types=1);

namespace Dskripchenko\PhpDocxPhpWord\Tests;

use Dskripchenko\PhpDocxPhpWord\PhpWordBridge;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhpWordBridgeTest extends TestCase
{
    private function samplePhpWord(): PhpWord
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addTitle('Bridge sample', 1);
        $run = $section->addTextRun(['alignment' => 'both']);
        $run->addText('Plain, ');
        $run->addText('bold', ['bold' => true]);
        $run->addText(' and ');
        $run->addText('colored', ['color' => 'C0392B']);
        $run->addText(' кириллица.');
        $section->addLink('https://example.com', 'example link');
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('A1');
        $cell = $table->addCell(2000);
        $cell->getStyle()->setGridSpan(2);
        $cell->addText('spans two');
        $section->addListItem('first', 0);
        $section->addListItem('nested', 1);
        $section->addListItem('numbered', 0, null, ListItemStyle::TYPE_NUMBER);

        return $word;
    }

    #[Test]
    public function to_html_exports_the_shared_set(): void
    {
        $html = PhpWordBridge::toHtml($this->samplePhpWord());

        self::assertStringContainsString('Bridge sample', $html);
        self::assertStringContainsString('кириллица', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertMatchesRegularExpression('@color:#c0392b@i', $html);
        self::assertStringContainsString('https://example.com', $html);
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('spans two', $html);
        self::assertMatchesRegularExpression('@<(ul|ol)@', $html);
        self::assertStringContainsString('nested', $html);
    }

    #[Test]
    public function from_html_builds_phpword_objects(): void
    {
        $word = PhpWordBridge::fromHtml(
            '<h1>From HTML</h1>'
            .'<p style="text-align: justify">Body with <b>bold</b> and <i>italic</i>.</p>'
            .'<table><tr><td>X</td><td>Y</td></tr></table>'
            .'<ul><li>alpha</li><li>beta</li></ul>',
        );

        // Round-trip through the bridge back to HTML proves the objects
        // carry the content (and exercises both mappers).
        $html = PhpWordBridge::toHtml($word);
        self::assertStringContainsString('From HTML', $html);
        self::assertStringContainsString('Body with', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('alpha', $html);
    }

    #[Test]
    public function read_to_phpword_consumes_arbitrary_docx(): void
    {
        // Produce DOCX with PHPWord itself (file boundary), read it back
        // into fresh PhpWord objects through php-docx's reader.
        $tmp = tempnam(sys_get_temp_dir(), 'bridge-');
        \PhpOffice\PhpWord\IOFactory::createWriter($this->samplePhpWord(), 'Word2007')->save($tmp);
        $word = PhpWordBridge::readToPhpWord((string) file_get_contents($tmp));
        unlink($tmp);

        $html = PhpWordBridge::toHtml($word);
        self::assertStringContainsString('Bridge sample', $html);
        self::assertStringContainsString('spans two', $html);
        self::assertStringContainsString('кириллица', $html);
    }

    #[Test]
    public function detect_variables_over_a_phpword_object(): void
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('Customer: {{customer_name}}');
        $section->addText('Total: ${total}');

        $variables = PhpWordBridge::detectVariables($word);
        $names = array_map(fn ($v) => $v->name, $variables);

        self::assertContains('customer_name', $names);
        self::assertContains('total', $names);
    }

    #[Test]
    public function object_round_trip_preserves_structure_counts(): void
    {
        $document = PhpWordBridge::toDocument($this->samplePhpWord());
        $back = PhpWordBridge::fromDocument($document);
        $again = PhpWordBridge::toDocument($back);

        $counts = fn ($doc) => [
            'blocks' => count($doc->section->body),
            'tables' => count(array_filter($doc->section->body, fn ($b) => $b instanceof \Dskripchenko\PhpDocx\Element\Table)),
            'lists' => count(array_filter($doc->section->body, fn ($b) => $b instanceof \Dskripchenko\PhpDocx\Element\ListNode)),
        ];

        self::assertSame($counts($document), $counts($again));
    }
}
