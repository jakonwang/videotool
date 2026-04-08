package com.videotool.console;

import android.content.Context;

import com.videotool.R;

import java.util.HashMap;
import java.util.Map;

public class MenuTextResolver
{
    private static final Map<String, Integer> MAP = new HashMap<>();

    static {
        MAP.put("admin.menu.overview", R.string.menu_overview);
        MAP.put("admin.menu.dashboard", R.string.menu_dashboard);
        MAP.put("admin.menu.groupSearch", R.string.menu_group_search);
        MAP.put("admin.menu.styleSearch", R.string.menu_style_search);
        MAP.put("admin.menu.offlineOrders", R.string.menu_offline_orders);
        MAP.put("admin.menu.growthHub", R.string.menu_growth_hub);
        MAP.put("admin.menu.growthHubMenu", R.string.menu_growth_hub);
        MAP.put("admin.menu.industryTrend", R.string.menu_industry_trend);
        MAP.put("admin.menu.competitorAnalysis", R.string.menu_competitor_analysis);
        MAP.put("admin.menu.adInsight", R.string.menu_ad_insight);
        MAP.put("admin.menu.dataImport", R.string.menu_data_import);
        MAP.put("admin.menu.groupCreator", R.string.menu_group_creator);
        MAP.put("admin.menu.groupCreatorMenu", R.string.menu_group_creator);
        MAP.put("admin.menu.influencerList", R.string.menu_influencer_list);
        MAP.put("admin.menu.outreachWorkspace", R.string.menu_outreach_workspace);
        MAP.put("admin.menu.sampleManagement", R.string.menu_sample_management);
        MAP.put("admin.menu.category", R.string.menu_category);
        MAP.put("admin.menu.messageTemplates", R.string.menu_message_templates);
        MAP.put("admin.menu.distribute", R.string.menu_distribute);
        MAP.put("admin.menu.material", R.string.menu_material);
        MAP.put("admin.menu.materialMenu", R.string.menu_material);
        MAP.put("admin.menu.video", R.string.menu_video);
        MAP.put("admin.menu.upload", R.string.menu_upload);
        MAP.put("admin.menu.product", R.string.menu_product);
        MAP.put("admin.menu.terminalSection", R.string.menu_terminal_section);
        MAP.put("admin.menu.terminal", R.string.menu_terminal);
        MAP.put("admin.menu.platform", R.string.menu_platform);
        MAP.put("admin.menu.device", R.string.menu_device);
        MAP.put("admin.menu.system", R.string.menu_system);
        MAP.put("admin.menu.settings", R.string.menu_settings);
        MAP.put("admin.menu.opsCenter", R.string.menu_ops_center);
        MAP.put("admin.menu.user", R.string.menu_user);
        MAP.put("admin.menu.extensionManager", R.string.menu_extension_manager);
    }

    public static String resolve(Context context, String key, String fallback)
    {
        if (context == null) {
            return fallback == null ? "" : fallback;
        }
        Integer resId = MAP.get(key);
        if (resId == null) {
            return (fallback == null || fallback.trim().isEmpty()) ? key : fallback;
        }
        return context.getString(resId);
    }
}

