<?php

namespace Symbiote\ContentReplace\Extension;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Shortcodes\FileShortcodeProvider;
use SilverStripe\Core\Extension;
use SilverStripe\Control\Controller;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use Symbiote\ContentReplace\Model\Configuration;

/**
 * @extends \SilverStripe\Core\Extension<(\SilverStripe\View\Parsers\ShortcodeParser & static)>
 */
class FileLinkAnnotationExtension extends Extension
{

    /**
     * Determine if the update can happen in the current controller context
     */
    public static function isApplicableController(): bool
    {
        $controller = Controller::curr();
        $applicableControllers = Configuration::config()->get('applicable_controllers');
        if(is_array($applicableControllers)) {
            foreach($applicableControllers as $applicableController) {
                if($controller instanceof $applicableController) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Handle before parse event on ShortcodeParser
     */
    public function onBeforeParse(?string &$content)
    {

        if(Configuration::config()->get('enabled') && is_string($content) && static::isApplicableController()) {
            static::annotateShortcodeValue($content);
        }
    }

    /**
     * Pull file using same method as FileShortcodeProvider
     */
    protected static function getFile(int $fileId): ?File
    {
        if($fileId > 0) {
            $file = FileShortcodeProvider::find_shortcode_record(['id' => $fileId]);
            return $file instanceof File ? $file : null;
        } else {
            return null;
        }
    }

    /**
     * Test of thje string is only a short .. e.g starts [  and ends ]
     */
    public static function isShortcodeOnly(string $shortcodeValue): bool
    {
        $pattern = '/^\[[^\]]+\]$/';
        return (bool) preg_match($pattern, trim($shortcodeValue));
    }

    /**
     * Globally match all shortcode values in the provided value
     */
    public static function matchFileLinkShortcodes(string $shortcodeValue): array
    {
        // $pattern = '#\[file_link.id=+([1-9]\d*)+]#i';
        $pattern = '/\[file_link[\s,]+(?:[^\]]*?\s)?id=(["\']?)([1-9]\d*)\1[^\]]*\]/i';
        $result = preg_match_all($pattern, $shortcodeValue, $matches);
        return $matches;
    }

    /**
     * Annotates the shortcode value if the shortcode [file_link id=N] is part
     * of an HTML string
     */
    protected static function annotateShortcodeValue(?string &$shortcodeValue)
    {
        if(is_null($shortcodeValue) || $shortcodeValue === '' || static::isShortcodeOnly($shortcodeValue)) {
            // Do not annotate bare shortcodes
            return false;
        }

        $matches = static::matchFileLinkShortcodes($shortcodeValue);
        $shortcodes = isset($matches[0]) && is_array($matches[0]) ? $matches[0] : [];
        $fileIds = isset($matches[2]) && is_array($matches[2]) ? $matches[2] : [];
        if($shortcodes !== [] && count($shortcodes) == count($fileIds)) {
            foreach($shortcodes as $i => $shortcode) {
                $annotatedValue = "";
                $file = null;
                if(isset($fileIds[$i])) {
                    $file = static::getFile($fileIds[$i]);
                }
                if($file instanceof File) {
                    $annotation = ArrayData::create([
                        'File' => $file
                    ])->renderWith('Symbiote/ContentReplace/AnnotatedFileLink');
                    $annotation->setProcessShortcodes(false);
                    $annotatedValue = trim($annotation->RAW());
                }

                if($annotatedValue !== "") {
                    // find out where to annotate
                    // ignore previously annotated tags to avoid duplicated annotations
                    // adds configured class to the annotation string
                    $pattern = '/<a\s+[^>]*?href=["\']' . preg_quote($shortcode) . '["\'][^>]*>.*?<\/a>(?!<span data-annotated="1">)/i';
                    // annotate the value onto the <a> tag containing the shortcode
                    $replacement = '$0' . ('<span data-annotated="1"> ' . $annotatedValue . '</span>');
                    $shortcodeValue = preg_replace($pattern, $replacement, $shortcodeValue);
                }
            }
        }
    }

}
