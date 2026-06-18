<?php

namespace Symbiote\ContentReplace\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Shortcodes\FileShortcodeProvider;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Parsers\ShortcodeParser;
use Symbiote\ContentReplace\Model\Configuration;
use Symbiote\ContentReplace\Extension\FileLinkAnnotationExtension;

class FileLinkAnnotationExtensionTest extends FunctionalTest
{
    protected static $fixture_file = 'FileLinkAnnotationExtensionTest.yml';

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
        Versioned::set_stage(Versioned::DRAFT);
        // Set backend root to /FileLinkAnnotationExtensionTest
        TestAssetStore::activate('FileLinkAnnotationExtensionTest');

        // Create a test files for each of the fixture references
        $fileIDs = array_merge(
            $this->allFixtureIDs(File::class)
        );
        foreach ($fileIDs as $fileID) {
            /**
             * @var File $file
            */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
            $file->write();
            $file->publishRecursive();
        }

        // allow root controller to be an applicable controller for the test
        Config::modify()->merge(
            Configuration::class,
            'applicable_controllers',
            [
                '\SilverStripe\Control\Controller'
            ]
        );
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();
        parent::tearDown();
    }

    public function testApplicableController(): void
    {
        $this->assertTrue(FileLinkAnnotationExtension::isApplicableController());
    }

    public function testFileLinkMatching(): void
    {
        $testStrings = [
            '[file_link,id=3]',                  // Simple with comma
            '[file_link id=3]',                   // Simple with space
            '[file_link foo=bar id=3]',           // Prior attributes
            '[file_link id=3 class="download"]',  // Trait attributes
            "[file_link id='3']",                 // Single quotes
            '[file_link id="3"]',                 // Double quotes
            '[file_link,  id=3]',                 // Extra whitespace
        ];

        foreach ($testStrings as $shortcodeValue) {
            $matches = FileLinkAnnotationExtension::matchFileLinkShortcodes($shortcodeValue);
            $this->assertArrayHasKey(2, $matches);
            $this->assertEquals(3, $matches[2][0]);
            $this->assertEquals($shortcodeValue, $matches[0][0]);
        }
    }

    public function testBareValidShortcode(): void
    {
        $testFile = $this->objFromFixture(File::class, 'example_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $shortcodeValue = "[file_link,id={$testFile->ID}]";
        $this->assertEquals(
            $testFile->Link(),
            $parser->parse($shortcodeValue),
            'Test that a bare shortcode only returns the link to the file and is not annotated.'
        );
    }

    public function testNotEnabled(): void
    {

        Config::modify()->set(
            Configuration::class,
            'enabled',
            false
        );

        $testFile = $this->objFromFixture(File::class, 'example_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $shortcodeValue = "<a href=\"[file_link,id={$testFile->ID}]\" class=\"file\" data-type=\"pdf\" data-size=\"977 KB\">Example Content</a>";
        $expectedValue = "<a href=\"{$testFile->Link()}\" class=\"file\" data-type=\"pdf\" data-size=\"977 KB\">Example Content</a>";
        $this->assertEquals(
            $expectedValue,
            $parser->parse($shortcodeValue),
            'Test not enabled is not annotated.'
        );

        Config::modify()->set(
            Configuration::class,
            'enabled',
            true
        );
    }

    public function testShortcodeInHtmlAnnotate(): void
    {
        $testFile = $this->objFromFixture(File::class, 'example_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $shortcodeValue  = "<a href=\"[file_link,id={$testFile->ID}]\" class=\"file\" data-type=\"pdf\" data-size=\"977 KB\">Example Content</a>";
        $htmlExpected = '<a href="/assets/FileLinkAnnotationExtensionTest/example.pdf" class="file" data-type="pdf" data-size="977 KB">Example Content</a><span data-annotated="1"> <strong>(PDF, 977 KB)</strong></span>';
        $this->assertEquals(
            $htmlExpected,
            $parser->parse($shortcodeValue),
            'Test that annotation is added after shortcode in HTML.'
        );
    }

    public function testShortcodeInContentAnnotate(): void
    {
        $testExampleFile = $this->objFromFixture(File::class, 'example_file');
        $testAnotherFile = $this->objFromFixture(File::class, 'another_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $template  = <<<HTML
        <h1>Heading 1</h1>
        <p>Quisque ultricies quis elit at luctus. Praesent tincidunt dui nulla, at elementum nunc imperdiet sodales. Donec hendrerit erat bibendum elit facilisis sodales. Nam eu ultricies magna, non vehicula massa. Interdum et malesuada fames ac ante ipsum primis in faucibus. Maecenas sollicitudin tempus libero sed vestibulum. Duis eu placerat dui.</p>
        <p>Quisque ultricies quis elit at luctus <a href="[file_link,id={$testExampleFile->ID}]" class="file" data-type="pdf" data-size="977 KB">EXAMPLE</a> Maecenas sollicitudin tempus libero sed vestibulum. Duis eu placerat dui. <a href="[file_link,id={$testAnotherFile->ID}]" class="file" data-type="pdf" data-size="977 KB">ANOTHER</a>. Duis eu placerat dui.</p>
        <p>Quisque ultricies quis elit at luctus <a href="[file_link,id={$testExampleFile->ID}]" class="file" data-type="pdf" data-size="977 KB">EXAMPLE</a> Maecenas sollicitudin tempus libero sed vestibulum. Duis eu placerat dui. <a href="[file_link,id={$testAnotherFile->ID}]" class="file" data-type="pdf" data-size="977 KB">ANOTHER</a>. Duis eu placerat dui.</p>
        HTML;

        $htmlExpected  = <<<HTML
        <h1>Heading 1</h1>
        <p>Quisque ultricies quis elit at luctus. Praesent tincidunt dui nulla, at elementum nunc imperdiet sodales. Donec hendrerit erat bibendum elit facilisis sodales. Nam eu ultricies magna, non vehicula massa. Interdum et malesuada fames ac ante ipsum primis in faucibus. Maecenas sollicitudin tempus libero sed vestibulum. Duis eu placerat dui.</p>
        <p>Quisque ultricies quis elit at luctus <a href="/assets/FileLinkAnnotationExtensionTest/example.pdf" class="file" data-type="pdf" data-size="977 KB">EXAMPLE</a><span data-annotated="1"> <strong>(PDF, 977 KB)</strong></span> Maecenas sollicitudin tempus libero sed vestibulum. Duis eu placerat dui. <a href="/assets/FileLinkAnnotationExtensionTest/another.pdf" class="file" data-type="pdf" data-size="977 KB">ANOTHER</a><span data-annotated="1"> <strong>(PDF, 977 KB)</strong></span>. Duis eu placerat dui.</p>
        <p>Quisque ultricies quis elit at luctus <a href="/assets/FileLinkAnnotationExtensionTest/example.pdf" class="file" data-type="pdf" data-size="977 KB">EXAMPLE</a><span data-annotated="1"> <strong>(PDF, 977 KB)</strong></span> Maecenas sollicitudin tempus libero sed vestibulum. Duis eu placerat dui. <a href="/assets/FileLinkAnnotationExtensionTest/another.pdf" class="file" data-type="pdf" data-size="977 KB">ANOTHER</a><span data-annotated="1"> <strong>(PDF, 977 KB)</strong></span>. Duis eu placerat dui.</p>
        HTML;

        $this->assertEquals(
            $htmlExpected,
            $parser->parse($template),
            'Test that multiple shortcodes are parsed correctly in a block of HTML.'
        );
    }

    public function testBareShortcodeWithDeletedFile() {
        $testFile = $this->objFromFixture(File::class, 'example_file');
        $id = $testFile->ID;
        $testFile->delete();
        $shortcodeValue = "[file_link,id={$id}]";
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $this->assertEquals(
            '',
            $parser->parse($shortcodeValue),
            'Test that deleted file returns no value.'
        );
    }

    public function testBareInvalidShortcodeValues() {
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $this->assertEquals('', $parser->parse('[file_link]'), 'Test that invalid ID attributes are not parsed.');
        $this->assertEquals('', $parser->parse('[file_link,id="text"]'));
        $this->assertEquals('', $parser->parse('[file_link,id="-1"]'), 'Short code is removed if file record is not present.');
    }

    public function testBareIncompleteShortcode() : void
    {
        $testFile = $this->objFromFixture(File::class, 'example_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $incompletedShortcodeValue = '[file_link]';
        $this->assertEquals(
            '',
            $parser->parse($incompletedShortcodeValue),
            'Test bare incomplete file shortcode.'
        );
    }

    public function testBareInvalidShortcode() : void
    {
        $testFile = $this->objFromFixture(File::class, 'example_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $invalidShortcodeValue = '[file_link,id=5000]';
        $this->assertEquals(
            '',
            $parser->parse($invalidShortcodeValue),
            'Test bare invalid file shortcode.'
        );
    }

    public function testShortcodeInHtmlIncomplete(): void
    {
        $testFile = $this->objFromFixture(File::class, 'example_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $shortcodeValue = "<a href=\"[file_link]\" class=\"file\" data-type=\"pdf\" data-size=\"977 KB\">Example Content</a>";
        $htmlExpected = '<a href="" class="file" data-type="pdf" data-size="977 KB">Example Content</a>';
        $this->assertEquals(
            $htmlExpected,
            $parser->parse($shortcodeValue),
            'Test incomplete shortcode value in HTML.'
        );
    }

    public function testShortcodeInHtmlInvalid(): void
    {
        $testFile = $this->objFromFixture(File::class, 'example_file');
        $parser = ShortcodeParser::create();
        $parser->register('file_link', FileShortcodeProvider::handle_shortcode(...));
        $shortcodeValue = "<a href=\"[file_link id=5000]\" class=\"file\" data-type=\"pdf\" data-size=\"977 KB\">Example Content</a>";
        $htmlExpected = '<a href="" class="file" data-type="pdf" data-size="977 KB">Example Content</a>';
        $this->assertEquals(
            $htmlExpected,
            $parser->parse($shortcodeValue),
            'Test invalid shortcode value in HTML.'
        );
    }

}
