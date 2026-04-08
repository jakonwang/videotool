package com.videotool.console;

import android.content.Context;

import okhttp3.OkHttpClient;

public class HttpClientProvider
{
    private static OkHttpClient client;
    private static PersistentCookieJar cookieJar;

    public static synchronized OkHttpClient client(Context context)
    {
        if (client != null) {
            return client;
        }
        cookieJar = new PersistentCookieJar(context.getApplicationContext());
        client = new OkHttpClient.Builder()
                .cookieJar(cookieJar)
                .followRedirects(true)
                .followSslRedirects(true)
                .build();
        return client;
    }

    public static synchronized PersistentCookieJar cookieJar(Context context)
    {
        if (cookieJar == null) {
            client(context);
        }
        return cookieJar;
    }

    public static synchronized void clearCookies(Context context)
    {
        cookieJar(context).clear();
    }
}

