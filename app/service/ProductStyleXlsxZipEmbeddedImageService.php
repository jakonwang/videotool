<?php
declare(strict_types=1);

namespace app\service;

use ZipArchive;

/**
 * 不依赖 PhpSpreadsheet 浮动图集合：直接从 xlsx/xlsm（OOXML Zip）解析 drawing 锚点与 media，提取「嵌入在表格/单元格区域」的图片。
 * 用于「放置在单元格中」、WPS、或库未正确挂接 DrawingCollection 等情况。
 */
class ProductStyleXlsxZipEmbeddedImageService
{
    private const NS_REL_PACKAGE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_ODR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * 在指定工作表上，按 1-based 列、行查找锚点覆盖该格的第一张图片，写入临时文件并返回绝对路径。
     */
    public static function extractImageAtCell(string $xlsxPath, string $sheetTitle, int $col1Based, int $row1Based): ?string
    {
        if ($col1Based < 1 || $row1Based < 1 || !\is_file($xlsxPath) || !\is_readable($xlsxPath)) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return null;
        }
        try {
            $wsPath = self::resolveWorksheetPathInZip($zip, $sheetTitle);
            if ($wsPath === null) {
                return null;
            }
            $drawingPaths = self::findDrawingXmlPathsFromWorksheet($zip, $wsPath);
            foreach ($drawingPaths as $dp) {
                $tmp = self::extractFromDrawingXml($zip, $dp, $col1Based, $row1Based);
                if ($tmp !== null) {
                    return $tmp;
                }
            }
            if ($drawingPaths === [] && self::workbookSheetCount($zip) === 1) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    if (\preg_match('#^xl/drawings/drawing\\d+\\.xml$#', $name) === 1) {
                        $tmp = self::extractFromDrawingXml($zip, $name, $col1Based, $row1Based);
                        if ($tmp !== null) {
                            return $tmp;
                        }
                    }
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    private static function workbookSheetCount(ZipArchive $zip): int
    {
        $xml = self::zipGetString($zip, 'xl/workbook.xml');
        if ($xml === null) {
            return 0;
        }
        $dom = self::loadDom($xml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('m', self::NS_MAIN);

        return $xp->query('//m:sheets/m:sheet')->length;
    }

    private static function zipGetString(ZipArchive $zip, string $path): ?string
    {
        $path = \str_replace('\\', '/', $path);
        $raw = $zip->getFromName($path);
        if ($raw === false) {
            $raw = $zip->getFromName(\ltrim($path, '/'));
        }
        if ($raw === false || $raw === '') {
            return null;
        }

        return (string) $raw;
    }

    private static function loadDom(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $prev = \libxml_use_internal_errors(true);
        try {
            $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            \libxml_clear_errors();
            \libxml_use_internal_errors($prev);
        }

        return $dom;
    }

    /**
     * @return array<string, string> Id => Target
     */
    private static function parseRelationships(ZipArchive $zip, string $relsPathInZip): array
    {
        $xml = self::zipGetString($zip, $relsPathInZip);
        if ($xml === null) {
            return [];
        }
        $dom = self::loadDom($xml);
        $map = [];
        foreach ($dom->getElementsByTagNameNS(self::NS_REL_PACKAGE, 'Relationship') as $rel) {
            if (!($rel instanceof \DOMElement)) {
                continue;
            }
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            if ($id !== '' && $target !== '') {
                $map[$id] = $target;
            }
        }
        if ($map !== []) {
            return $map;
        }
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            if (!($rel instanceof \DOMElement)) {
                continue;
            }
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            if ($id !== '' && $target !== '') {
                $map[$id] = $target;
            }
        }

        return $map;
    }

    private static function resolveWorksheetPathInZip(ZipArchive $zip, string $sheetTitle): ?string
    {
        $xml = self::zipGetString($zip, 'xl/workbook.xml');
        if ($xml === null) {
            return null;
        }
        $dom = self::loadDom($xml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('m', self::NS_MAIN);
        $rels = self::parseRelationships($zip, 'xl/_rels/workbook.xml.rels');
        $nodes = $xp->query('//m:sheets/m:sheet');
        $pickedRid = null;
        foreach ($nodes as $sn) {
            if (!($sn instanceof \DOMElement)) {
                continue;
            }
            $name = (string) $sn->getAttribute('name');
            if ($name === $sheetTitle) {
                $pickedRid = self::relIdFromSheetElement($sn);
                break;
            }
        }
        if ($pickedRid === null && $nodes->length > 0) {
            $first = $nodes->item(0);
            if ($first instanceof \DOMElement) {
                $pickedRid = self::relIdFromSheetElement($first);
            }
        }
        if ($pickedRid === null || !isset($rels[$pickedRid])) {
            return null;
        }

        return self::joinXlPath('xl', $rels[$pickedRid]);
    }

    private static function relIdFromSheetElement(\DOMElement $sn): ?string
    {
        $rid = $sn->getAttributeNS(self::NS_ODR, 'id');
        if ($rid === '') {
            $rid = $sn->getAttribute('r:id');
        }

        return $rid !== '' ? $rid : null;
    }

    private static function joinXlPath(string $xlPrefix, string $target): string
    {
        $target = \str_replace('\\', '/', $target);
        if (\str_starts_with($target, '/xl/')) {
            return \ltrim($target, '/');
        }
        if (\str_starts_with($target, 'xl/')) {
            return $target;
        }

        return $xlPrefix . '/' . \ltrim($target, '/');
    }

    /**
     * @return list<string>
     */
    private static function findDrawingXmlPathsFromWorksheet(ZipArchive $zip, string $worksheetPath): array
    {
        $worksheetPath = \str_replace('\\', '/', $worksheetPath);
        $dir = \dirname($worksheetPath);
        $base = \basename($worksheetPath);
        $relsPath = $dir . '/_rels/' . $base . '.rels';
        $relsXml = self::zipGetString($zip, $relsPath);
        $sheetXml = self::zipGetString($zip, $worksheetPath);
        if ($sheetXml === null) {
            return [];
        }
        $dom = self::loadDom($sheetXml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('m', self::NS_MAIN);
        $drawingRids = [];
        foreach ($xp->query('//m:drawing') as $n) {
            if ($n instanceof \DOMElement) {
                $rid = $n->getAttributeNS(self::NS_ODR, 'id');
                if ($rid === '') {
                    $rid = $n->getAttribute('r:id');
                }
                if ($rid !== '') {
                    $drawingRids[] = $rid;
                }
            }
        }
        if ($relsXml === null) {
            return [];
        }
        $rDom = self::loadDom($relsXml);
        $ridToDrawingXml = [];
        foreach ($rDom->getElementsByTagNameNS(self::NS_REL_PACKAGE, 'Relationship') as $rel) {
            if (!($rel instanceof \DOMElement)) {
                continue;
            }
            $id = $rel->getAttribute('Id');
            $type = $rel->getAttribute('Type');
            $target = $rel->getAttribute('Target');
            if ($id === '' || $target === '') {
                continue;
            }
            if (!\str_contains($type, '/drawing') || \str_contains($type, 'chart')) {
                continue;
            }
            $full = self::resolveZipRelative($dir, $target);
            if (\str_ends_with(\strtolower($full), '.xml') && \str_contains($full, 'drawings')) {
                $ridToDrawingXml[$id] = $full;
            }
        }
        $out = [];
        foreach ($drawingRids as $rid) {
            if (isset($ridToDrawingXml[$rid])) {
                $out[] = $ridToDrawingXml[$rid];
            }
        }

        return \array_values(\array_unique($out));
    }

    private static function resolveZipRelative(string $baseDir, string $target): string
    {
        $baseDir = \str_replace('\\', '/', $baseDir);
        $target = \str_replace('\\', '/', $target);
        $parts = $baseDir !== '' && $baseDir !== '.' ? \explode('/', $baseDir) : [];
        foreach (\explode('/', $target) as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                \array_pop($parts);
            } else {
                $parts[] = $p;
            }
        }

        return \implode('/', $parts);
    }

    private static function extractFromDrawingXml(ZipArchive $zip, string $drawingPathInZip, int $col1Based, int $row1Based): ?string
    {
        $xml = self::zipGetString($zip, $drawingPathInZip);
        if ($xml === null) {
            return null;
        }
        $dom = self::loadDom($xml);
        $xp = new \DOMXPath($dom);
        $anchors = $xp->query('//*[local-name()="twoCellAnchor" or local-name()="oneCellAnchor"]');
        $c0 = $col1Based - 1;
        $r0 = $row1Based - 1;
        $dDir = \dirname($drawingPathInZip);
        $relsPath = $dDir . '/_rels/' . \basename($drawingPathInZip) . '.rels';
        $rels = self::parseRelationships($zip, $relsPath);
        foreach ($anchors as $anchor) {
            if (!self::anchorContainsCell($xp, $anchor, $c0, $r0)) {
                continue;
            }
            $embedRid = self::findBlipEmbedRid($anchor);
            if ($embedRid === null || !isset($rels[$embedRid])) {
                continue;
            }
            $mediaTarget = $rels[$embedRid];
            if (!\str_contains(\strtolower($mediaTarget), 'media') && !\preg_match('#\\.(png|jpe?g|gif|webp|bmp)$#i', $mediaTarget)) {
                continue;
            }
            $mediaPath = self::resolveZipRelative($dDir, $mediaTarget);
            $bin = self::zipGetString($zip, $mediaPath);
            if ($bin === null || $bin === '') {
                continue;
            }
            $ext = \strtolower(\pathinfo($mediaPath, PATHINFO_EXTENSION) ?: 'png');
            if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
                $ext = 'png';
            }
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $tmp = ProductStyleImportService::createTempImagePath($ext);
            if (@\file_put_contents($tmp, $bin) !== false) {
                return $tmp;
            }
        }

        return null;
    }

    private static function anchorContainsCell(\DOMXPath $xp, \DOMNode $anchor, int $c0, int $r0): bool
    {
        $local = $anchor->localName;
        if ($local !== 'oneCellAnchor' && $local !== 'twoCellAnchor') {
            return false;
        }
        $fromList = $xp->query('.//*[local-name()="from"]', $anchor);
        if ($fromList->length === 0) {
            return false;
        }
        $from = $fromList->item(0);
        if (!$from instanceof \DOMElement) {
            return false;
        }
        $fc = self::firstChildIntByLocalName($from, 'col');
        $fr = self::firstChildIntByLocalName($from, 'row');
        if ($local === 'oneCellAnchor') {
            return $c0 === $fc && $r0 === $fr;
        }
        $tc = $fc;
        $tr = $fr;
        $toList = $xp->query('.//*[local-name()="to"]', $anchor);
        if ($toList->length > 0) {
            $to = $toList->item(0);
            if ($to instanceof \DOMElement) {
                $tc = self::firstChildIntByLocalName($to, 'col');
                $tr = self::firstChildIntByLocalName($to, 'row');
            }
        }
        $minC = \min($fc, $tc);
        $maxC = \max($fc, $tc);
        $minR = \min($fr, $tr);
        $maxR = \max($fr, $tr);

        return $c0 >= $minC && $c0 <= $maxC && $r0 >= $minR && $r0 <= $maxR;
    }

    private static function firstChildIntByLocalName(\DOMElement $parent, string $local): int
    {
        foreach ($parent->childNodes as $ch) {
            if ($ch instanceof \DOMElement && $ch->localName === $local) {
                return (int) \trim($ch->textContent);
            }
        }

        return 0;
    }

    private static function findBlipEmbedRid(\DOMNode $anchor): ?string
    {
        $walk = function (\DOMNode $node) use (&$walk): ?string {
            if ($node instanceof \DOMElement) {
                if ($node->localName === 'blip') {
                    $e = $node->getAttributeNS(self::NS_ODR, 'embed');
                    if ($e !== '') {
                        return $e;
                    }
                    foreach ($node->attributes ?? [] as $attr) {
                        if ($attr->localName === 'embed' && $attr->value !== '') {
                            return (string) $attr->value;
                        }
                    }
                }
                foreach ($node->childNodes as $c) {
                    $f = $walk($c);
                    if ($f !== null) {
                        return $f;
                    }
                }
            }

            return null;
        };

        return $walk($anchor);
    }

    /**
     * 从 Zip 解析出图片后写入 public/uploads/products（md5），并删除临时文件。
     */
    public static function extractRowImageToProductsDir(
        string $xlsxPath,
        string $sheetTitle,
        int $imgCol1Based,
        int $row1Based,
        string $publicRoot
    ): ?string {
        $tmp = self::extractImageAtCell($xlsxPath, $sheetTitle, $imgCol1Based, $row1Based);
        if ($tmp === null || !\is_file($tmp)) {
            return null;
        }
        $web = ProductStyleImportService::saveImportImageToProductsDir($tmp, $publicRoot);
        if ($web !== null && \is_file($tmp)) {
            @\unlink($tmp);
        }

        return $web;
    }

    /**
     * 解析类似 =DISPIMG("ID_xxx",1) 的公式，直接从 xl/cellimages.xml 取图并写入临时文件。
     */
    public static function extractImageFromDispImgFormula(string $xlsxPath, string $formula): ?string
    {
        $id = self::parseDispImgIdFromFormula($formula);
        if ($id === null) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return null;
        }
        try {
            $rels = self::parseRelationships($zip, 'xl/_rels/cellimages.xml.rels');
            if ($rels === []) {
                return null;
            }
            $cellImagesXml = self::zipGetString($zip, 'xl/cellimages.xml');
            if ($cellImagesXml === null) {
                return null;
            }
            $dom = self::loadDom($cellImagesXml);
            $xp = new \DOMXPath($dom);
            $nodes = $xp->query('//*[local-name()="cellImage"]');
            $rid = null;
            foreach ($nodes as $node) {
                $nameNode = $xp->query('.//*[local-name()="cNvPr"]', $node)->item(0);
                if (!($nameNode instanceof \DOMElement)) {
                    continue;
                }
                $name = (string) $nameNode->getAttribute('name');
                if ($name === '') {
                    continue;
                }
                if (\strcasecmp($name, $id) !== 0) {
                    continue;
                }
                $blipNode = $xp->query('.//*[local-name()="blip"]', $node)->item(0);
                if (!($blipNode instanceof \DOMElement)) {
                    continue;
                }
                $rid = $blipNode->getAttributeNS(self::NS_ODR, 'embed');
                if ($rid === '') {
                    foreach ($blipNode->attributes ?? [] as $attr) {
                        if ($attr->localName === 'embed' && $attr->value !== '') {
                            $rid = (string) $attr->value;
                            break;
                        }
                    }
                }
                if ($rid !== '') {
                    break;
                }
            }
            if ($rid === null || $rid === '' || !isset($rels[$rid])) {
                return null;
            }
            $target = (string) $rels[$rid];
            $mediaPath = self::joinXlPath('xl', $target);
            $bin = self::zipGetString($zip, $mediaPath);
            if ($bin === null || $bin === '') {
                return null;
            }
            $ext = \strtolower(\pathinfo($mediaPath, PATHINFO_EXTENSION) ?: 'png');
            if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
                $ext = 'png';
            }
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $tmp = ProductStyleImportService::createTempImagePath($ext);
            if (@\file_put_contents($tmp, $bin) === false) {
                return null;
            }

            return $tmp;
        } finally {
            $zip->close();
        }
    }

    /**
     * 解析 DISPIMG 公式并直接落盘至 public/uploads/products（md5），返回站内路径。
     */
    public static function extractImageFromDispImgFormulaToProductsDir(string $xlsxPath, string $formula, string $publicRoot): ?string
    {
        $tmp = self::extractImageFromDispImgFormula($xlsxPath, $formula);
        if ($tmp === null || !\is_file($tmp)) {
            return null;
        }
        $web = ProductStyleImportService::saveImportImageToProductsDir($tmp, $publicRoot);
        if (\is_file($tmp)) {
            @\unlink($tmp);
        }

        return $web;
    }

    private static function parseDispImgIdFromFormula(string $formula): ?string
    {
        $formula = \trim($formula);
        if ($formula === '') {
            return null;
        }
        // 兼容：DISPIMG / _xlfn.DISPIMG，单双引号，或其它包装函数里携带 "ID_xxx"
        if (\preg_match('/^\s*=?\s*(?:_xlfn\.)?DISPIMG\s*\(\s*(["\'])([^"\']+)\1/i', $formula, $m) === 1) {
            $id = \trim((string) ($m[2] ?? ''));

            return $id !== '' ? $id : null;
        }
        if (\preg_match('/(["\'])(ID_[A-Za-z0-9]+)\1/i', $formula, $m2) === 1) {
            $id = \trim((string) ($m2[2] ?? ''));

            return $id !== '' ? $id : null;
        }

        return null;
    }
}
