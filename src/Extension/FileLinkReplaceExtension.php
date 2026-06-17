<?php

namespace Symbiote\ContentReplace\Extension;

use SilverStripe\Core\Extension;
use Symbiote\ContentReplace\Model\WYSIWYGElement;
use SilverStripe\Control\Controller;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Assets\File;

class FileLinkReplaceExtension extends Extension
{

    protected array $fileIdsTmp = [];

    public function onBeforeParse(string &$content)
    {
        $isAdminPage = Controller::curr() instanceof LeftAndMain;
        if (!$isAdminPage) {
            $this->setfileIdsTmpIfFileLinkExists($content);
        }
    }

    public function onAfterParse(string &$content)
    {
        $isAdminPage = Controller::curr() instanceof LeftAndMain;
        if (!$isAdminPage) {
            $content = $this->replaceFileLinkWithTemplate($content);
        }
    }

    private function setfileIdsTmpIfFileLinkExists(string $value)
    {
        if ($this->fileIdsTmp) {
            $this->fileIdsTmp = [];
        }

        preg_replace_callback(
            // Match file_link shorcode
            '#\[file_link.id=+([1-9]\d*)+]#i',
            function (array $matches): string {
                if(isset($matches[1])) {
                    // $val[0] - the shorcode, eg: [file_link,id=12]
                    // $val[1] - the file_link id, eg: 12
                    $this->fileIdsTmp[] = $matches[1];
                    return $matches[1];
                } else {
                    return "";
                }
            },
            $value
        );
    }

    /**
     * Replace file links with WYSIWYG_FileLink.ss template.
     * eg: <a href="[file_link,id=12]">
     *
     * @return string
     */
    private function replaceFileLinkWithTemplate(string $value)
    {
        $fileIds = $this->fileIdsTmp;
        if ($fileIds == []) {
            return $value;
        }

        $files = File::get()->filter('ID', $fileIds);

        $fileMap = [];
        // create a map of URL to file object for later use
        foreach ($files as $file) {
            $fileMap[$file->getURL()] = $file;
        }

        $res = preg_replace_callback(
            // Match all a tags, even with nested child html tags
            '#<a.*?href=\"(.*?)\".*?>(?:.(?!\<\/a\>))*.<\/a>#i',
            function (array $matches) use ($fileMap): string {
                // $val[0] - the link HTML tag, eg: <a href="link">text</a>
                $linkHtml = $matches[0];
                $href = $matches[1];

                $element = WYSIWYGElement::create();

                if (!isset($fileMap[$href])) {
                    return $linkHtml;
                }

                $element->setFile($fileMap[$href]);

                // set default link HTML
                $element->setLinkHTML($linkHtml);
                return $element
                    ->renderWith(
                        [
                            ["type" => "Symbiote/ContentReplace", 'WYSIWYGFileLink'],
                            ["type" => "Includes", 'WYSIWYGFileLink']
                        ]
                    )
                    ->RAW();
            },
            $value
        );

        return $res;
    }
}
