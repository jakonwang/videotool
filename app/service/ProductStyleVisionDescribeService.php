<?php
declare(strict_types=1);

namespace app\service;

/**
 * 寻款参考图「AI 视觉描述」生成（饰品全品类）：仅火山方舟豆包。
 */
class ProductStyleVisionDescribeService
{
    /**
     * 生成单行中文特征描述（耳环、手链、项链等；写入 ai_description 字段格式一致）。
     */
    public static function describeEarring(string $absolutePath): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        if (!VolcArkVisionConfig::get()['enabled']) {
            return null;
        }
        $d = VolcArkVisionService::describeEarringImage($absolutePath);

        return $d !== null && trim($d) !== '' ? trim($d) : null;
    }

    /**
     * 异步导入：豆包 **单次** 请求生成描述（合并原指纹+全量两步，显著缩短每行等待时间）。
     */
    public static function describeForImport(string $absolutePath): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        if (!VolcArkVisionConfig::get()['enabled']) {
            return null;
        }
        $d = VolcArkVisionService::describeImportSinglePassImage($absolutePath);

        return $d !== null && trim($d) !== '' ? trim($d) : null;
    }

    /**
     * 是否应在导入时调用视觉描述：仅看后台「导入时生成视觉描述」勾选。
     * 若未启用豆包或未配置完整，describeForImport 会返回 null；列表页用 vision_any_provider_ready 提示用户。
     */
    public static function shouldDescribeOnImport(): bool
    {
        return VisionOpenAIConfig::get()['describe_on_import'];
    }
}
