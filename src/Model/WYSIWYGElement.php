<?php

declare(strict_types=1);

namespace Symbiote\ContentReplace\Model;

use SilverStripe\Model\ModelData;
use SilverStripe\Assets\File;

class WYSIWYGElement extends ModelData
{
    /**
     * the HTML content inside link
     */
    protected string $linkHTML = "";

    protected ?File $file = null;

    public function getFile(): ?File
    {
        return $this->file;
    }

    /**
     * Set an HTML attributes on element
     */
    public function setLinkHTML(string $linkHTML): static
    {
        $this->linkHTML = $linkHTML;
        return $this;
    }

    public function getLinkHTML(): string
    {
        return $this->linkHTML;
    }

    public function setFile(File $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function getFileId()
    {
        $file = $this->getFile();
        return $file ? $file->ID : null;
    }
}
