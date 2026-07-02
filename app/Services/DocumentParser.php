<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;

class DocumentParser
{
    /**
     * Parse text content from a given file path based on its extension/mime-type.
     */
    public function parse(string $filePath): string
    {
        if (! file_exists($filePath)) {
            throw new \Exception("File not found at path: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'docx') {
            return $this->parseDocx($filePath);
        }

        // Default: parse as plain text (txt, md, json, csv, html, shtml, etc.)
        return $this->parsePlainText($filePath);
    }

    /**
     * Parse plain text files.
     */
    protected function parsePlainText(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Failed to read plain text file: {$filePath}");
        }

        return $content;
    }

    /**
     * Parse Word (.docx) files.
     */
    protected function parseDocx(string $filePath): string
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText()."\n";
                    } elseif (method_exists($element, 'getElements')) {
                        foreach ($element->getElements() as $subElement) {
                            if (method_exists($subElement, 'getText')) {
                                $text .= $subElement->getText();
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            if (! empty(trim($text))) {
                return $text;
            }
        } catch (\Throwable $e) {
            \Log::info('PhpWord failed to parse docx, attempting direct ZIP extraction fallback: '.$e->getMessage());
        }

        return $this->parseDocxDirectly($filePath);
    }

    /**
     * Directly extract text from docx zip contents to avoid DOMDocument namespace errors.
     */
    protected function parseDocxDirectly(string $filePath): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \Exception('Unable to open docx file as ZIP archive.');
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xmlContent === false) {
            throw new \Exception('word/document.xml not found inside docx archive.');
        }

        // Strip namespace prefixes from XML tags (e.g. <w:p> to <w_p>) to prevent DOMDocument loading errors
        $cleanXml = preg_replace('/<(\/?[a-zA-Z0-9_]+):([a-zA-Z0-9_]+)/', '<$1_$2', $xmlContent);
        $cleanXml = preg_replace('/([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)=/', '$1_$2=', $cleanXml);

        $dom = new \DOMDocument;
        if (! @$dom->loadXML($cleanXml)) {
            throw new \Exception('Failed to parse clean Word XML structure.');
        }

        $paragraphs = $dom->getElementsByTagName('w_p');
        $text = '';

        foreach ($paragraphs as $paragraph) {
            $pText = '';
            $tElements = $paragraph->getElementsByTagName('w_t');
            foreach ($tElements as $tElement) {
                $pText .= $tElement->nodeValue;
            }
            if ($pText !== '') {
                $text .= $pText."\n";
            }
        }

        if (empty(trim($text))) {
            // Fallback: search for any text node inside document if no paragraphs matched
            $tElements = $dom->getElementsByTagName('w_t');
            foreach ($tElements as $tElement) {
                $text .= $tElement->nodeValue.' ';
            }
        }

        return $text;
    }
}
