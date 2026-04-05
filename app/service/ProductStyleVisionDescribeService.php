<?php
declare(strict_types=1);

namespace app\service;

/**
 * 寻款参考图「AI 视觉描述」生成（饰品全品类）：火山方舟豆包优先，失败或未配置时回退 OpenAI。
 */
class ProductStyleVisionDescribeService
{
    /**
     * 生成单行中文特征描述（耳环、手链、项链等；与导入 / OpenAI 字段格式一致）。
     */
    public static function describeEarring(string $absolutePath): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        if (VolcArkVisionConfig::get()['enabled']) {
            $d = VolcArkVisionService::describeEarringImage($absolutePath);
            if ($d !== null && trim($d) !== '') {
                return trim($d);
            }
        }
        if (VisionOpenAIConfig::get()['enabled']) {
            return VisionSearchService::describeEarringImage($absolutePath);
        }

        return null;
    }

    /**
     * 异步 CSV 导入：豆包用短「指纹」Prompt；未命中时回退 OpenAI 全量描述（若已启用）。
     */
    public static function describeForImport(string $absolutePath): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        if (VolcArkVisionConfig::get()['enabled']) {
            $d = VolcArkVisionService::describeImportFingerprintImage($absolutePath);
            if ($d !== null && trim($d) !== '') {
                return trim($d);
            }
        }
        if (VisionOpenAIConfig::get()['enabled']) {
            return VisionSearchService::describeEarringImage($absolutePath);
        }

        return null;
    }
}
