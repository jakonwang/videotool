package com.videotool.console;

import android.content.Context;
import android.content.res.Configuration;
import android.os.Build;

import java.util.Locale;

public class LocaleHelper
{
    public static Context wrap(Context context)
    {
        if (context == null) {
            return null;
        }
        AppPrefs prefs = new AppPrefs(context);
        String lang = prefs.getLanguage();
        if (lang.isEmpty()) {
            lang = detectSystemLanguage();
            prefs.setLanguage(lang);
        }
        return updateContext(context, lang);
    }

    public static void applySavedLocale(Context context)
    {
        if (context == null) {
            return;
        }
        AppPrefs prefs = new AppPrefs(context);
        String lang = prefs.getLanguage();
        if (lang.isEmpty()) {
            lang = detectSystemLanguage();
            prefs.setLanguage(lang);
        }
        updateContext(context, lang);
    }

    public static void switchLanguage(Context context, String lang)
    {
        if (context == null) {
            return;
        }
        String target = AppPrefs.normalizeLanguage(lang);
        if (target.isEmpty()) {
            target = "zh";
        }
        AppPrefs prefs = new AppPrefs(context);
        prefs.setLanguage(target);
        updateContext(context, target);
    }

    public static String detectSystemLanguage()
    {
        Locale locale = Locale.getDefault();
        String lang = locale == null ? "" : locale.getLanguage();
        lang = AppPrefs.normalizeLanguage(lang);
        return lang.isEmpty() ? "zh" : lang;
    }

    private static Context updateContext(Context context, String lang)
    {
        Locale locale = new Locale(lang);
        Locale.setDefault(locale);
        Configuration config = new Configuration(context.getResources().getConfiguration());
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            config.setLocale(locale);
            return context.createConfigurationContext(config);
        }
        config.locale = locale;
        context.getResources().updateConfiguration(config, context.getResources().getDisplayMetrics());
        return context;
    }
}

