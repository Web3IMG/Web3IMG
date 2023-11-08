<?php

namespace CHV;

use JeroenDesloovere\XmpMetadataExtractor\XmpMetadataExtractor as Base;

class XmpMetadataExtractor extends Base
{
    public function extractFromContent(string $content): array
    {
        try {
            $string = $this->getXmpXmlString($content);
            if ($string == '') {
                return [];
            }
            $doc = new \DOMDocument();
            $doc->loadXML($string);
            $root = $doc->documentElement;
            $output = $this->convertDomNodeToArray($root);
            $output['@root'] = $root->tagName;

            return $output;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getXmpXmlString(string $content): string
    {
        $xmpDataStart = strpos($content, '<x:xmpmeta');
        if ($xmpDataStart === false) {
            return '';
        }
        $xmpDataEnd = strpos($content, '</x:xmpmeta>');
        $xmpLength = $xmpDataEnd - $xmpDataStart;
        

        return substr($content, $xmpDataStart, $xmpLength + 12);
    }
}
