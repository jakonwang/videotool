package com.videotool.console;

import android.content.Context;
import android.content.SharedPreferences;
import android.webkit.CookieManager;

import androidx.annotation.NonNull;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Iterator;
import java.util.List;

import okhttp3.Cookie;
import okhttp3.CookieJar;
import okhttp3.HttpUrl;

public class PersistentCookieJar implements CookieJar
{
    private static final String PREF_NAME = "mobile_console_cookies";
    private static final String KEY_COOKIES = "cookies_json";

    private final SharedPreferences prefs;
    private final List<Cookie> cache = new ArrayList<>();

    public PersistentCookieJar(Context context)
    {
        this.prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        loadFromPrefs();
    }

    @Override
    public synchronized void saveFromResponse(@NonNull HttpUrl url, @NonNull List<Cookie> cookies)
    {
        if (cookies.isEmpty()) {
            return;
        }
        for (Cookie cookie : cookies) {
            upsert(cookie);
        }
        removeExpired();
        persist();
    }

    @NonNull
    @Override
    public synchronized List<Cookie> loadForRequest(@NonNull HttpUrl url)
    {
        removeExpired();
        List<Cookie> out = new ArrayList<>();
        for (Cookie cookie : cache) {
            if (cookie.matches(url)) {
                out.add(cookie);
            }
        }
        if (out.isEmpty()) {
            return new ArrayList<>();
        }
        return out;
    }

    public synchronized void clear()
    {
        cache.clear();
        prefs.edit().remove(KEY_COOKIES).apply();
        CookieManager manager = CookieManager.getInstance();
        manager.removeAllCookies(null);
        manager.flush();
    }

    public synchronized void syncToWebView(String originOrUrl)
    {
        if (originOrUrl == null || originOrUrl.trim().isEmpty()) {
            return;
        }
        CookieManager manager = CookieManager.getInstance();
        manager.setAcceptCookie(true);
        removeExpired();
        for (Cookie cookie : cache) {
            StringBuilder sb = new StringBuilder();
            sb.append(cookie.name()).append('=').append(cookie.value());
            sb.append("; path=").append(cookie.path());
            if (cookie.secure()) {
                sb.append("; Secure");
            }
            if (cookie.httpOnly()) {
                sb.append("; HttpOnly");
            }
            manager.setCookie(originOrUrl, sb.toString());
        }
        manager.flush();
    }

    private void upsert(Cookie incoming)
    {
        String key = cookieKey(incoming);
        for (int i = 0; i < cache.size(); i++) {
            Cookie old = cache.get(i);
            if (cookieKey(old).equals(key)) {
                cache.set(i, incoming);
                return;
            }
        }
        cache.add(incoming);
    }

    private void removeExpired()
    {
        long now = System.currentTimeMillis();
        Iterator<Cookie> it = cache.iterator();
        boolean changed = false;
        while (it.hasNext()) {
            Cookie c = it.next();
            if (c.expiresAt() < now) {
                it.remove();
                changed = true;
            }
        }
        if (changed) {
            persist();
        }
    }

    private String cookieKey(Cookie c)
    {
        return c.name() + "|" + c.domain() + "|" + c.path();
    }

    private void persist()
    {
        JSONArray arr = new JSONArray();
        for (Cookie c : cache) {
            try {
                JSONObject o = new JSONObject();
                o.put("name", c.name());
                o.put("value", c.value());
                o.put("domain", c.domain());
                o.put("path", c.path());
                o.put("expires_at", c.expiresAt());
                o.put("secure", c.secure());
                o.put("http_only", c.httpOnly());
                o.put("host_only", c.hostOnly());
                o.put("persistent", c.persistent());
                arr.put(o);
            } catch (Exception ignore) {
            }
        }
        prefs.edit().putString(KEY_COOKIES, arr.toString()).apply();
    }

    private void loadFromPrefs()
    {
        cache.clear();
        String raw = prefs.getString(KEY_COOKIES, "");
        if (raw == null || raw.trim().isEmpty()) {
            return;
        }
        try {
            JSONArray arr = new JSONArray(raw);
            long now = System.currentTimeMillis();
            for (int i = 0; i < arr.length(); i++) {
                JSONObject o = arr.optJSONObject(i);
                if (o == null) {
                    continue;
                }
                Cookie.Builder b = new Cookie.Builder()
                        .name(o.optString("name", ""))
                        .value(o.optString("value", ""))
                        .path(o.optString("path", "/"));
                String domain = o.optString("domain", "");
                if (domain.isEmpty()) {
                    continue;
                }
                if (o.optBoolean("host_only", false)) {
                    b.hostOnlyDomain(domain);
                } else {
                    b.domain(domain);
                }
                if (o.optBoolean("secure", false)) {
                    b.secure();
                }
                if (o.optBoolean("http_only", false)) {
                    b.httpOnly();
                }
                if (o.optBoolean("persistent", false)) {
                    long expiresAt = o.optLong("expires_at", now + 86400000L);
                    b.expiresAt(expiresAt);
                }
                Cookie c = b.build();
                if (c.expiresAt() >= now) {
                    cache.add(c);
                }
            }
        } catch (Exception ignore) {
        }
    }
}

