package com.videotool;

import android.content.Intent;
import android.os.Bundle;

import androidx.appcompat.app.AppCompatActivity;

import com.videotool.console.AppPrefs;
import com.videotool.console.LocaleHelper;
import com.videotool.console.LoginActivity;
import com.videotool.console.ModuleConsoleActivity;

/**
 * Launcher router:
 * - logged in: module console
 * - not logged in: login page
 */
public class MainActivity extends AppCompatActivity
{
    @Override
    protected void attachBaseContext(android.content.Context newBase)
    {
        super.attachBaseContext(LocaleHelper.wrap(newBase));
    }

    @Override
    protected void onCreate(Bundle savedInstanceState)
    {
        super.onCreate(savedInstanceState);
        LocaleHelper.applySavedLocale(this);

        AppPrefs prefs = new AppPrefs(this);
        Intent next;
        if (prefs.isLoggedIn() && !prefs.getAdminBase().isEmpty()) {
            next = new Intent(this, ModuleConsoleActivity.class);
        } else {
            next = new Intent(this, LoginActivity.class);
        }
        startActivity(next);
        finish();
    }
}

