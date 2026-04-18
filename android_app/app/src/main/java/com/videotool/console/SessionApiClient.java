package com.videotool.console;

import android.content.Context;

import org.json.JSONObject;

import java.io.IOException;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.FormBody;
import okhttp3.HttpUrl;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

public class SessionApiClient
{
    private static final MediaType JSON = MediaType.parse("application/json; charset=utf-8");
    private final Context appContext;
    private final OkHttpClient client;

    public interface LoginCallback
    {
        void onSuccess(int userId, String username, String role, int tenantId);

        void onError(String errorMessage);
    }

    public interface JsonCallback
    {
        void onSuccess(JSONObject data);

        void onUnauthorized();

        void onError(String errorMessage);
    }

    public SessionApiClient(Context context)
    {
        this.appContext = context.getApplicationContext();
        this.client = HttpClientProvider.client(this.appContext);
    }

    public void login(String adminBase, String username, String password, final LoginCallback callback)
    {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        if (base.isEmpty()) {
            callback.onError("admin_base_empty");
            return;
        }
        RequestBody body = new FormBody.Builder()
                .add("username", username == null ? "" : username.trim())
                // 密码按用户输入原样提交，避免尾部空格类口令被误改
                .add("password", password == null ? "" : password)
                .add("redirect", "/admin.php")
                .build();
        Request req = new Request.Builder()
                .url(base + "/auth/login")
                .header("Accept", "application/json")
                .post(body)
                .build();

        client.newCall(req).enqueue(new Callback()
        {
            @Override
            public void onFailure(Call call, IOException e)
            {
                callback.onError("network_error: " + (e.getMessage() == null ? "" : e.getMessage()));
            }

            @Override
            public void onResponse(Call call, Response response) throws IOException
            {
                String text = response.body() == null ? "" : response.body().string();
                if (!response.isSuccessful()) {
                    callback.onError("http_" + response.code());
                    return;
                }
                try {
                    JSONObject root = new JSONObject(text);
                    if (root.optInt("code", 1) != 0) {
                        callback.onError(root.optString("msg", "login_failed"));
                        return;
                    }
                    JSONObject data = root.optJSONObject("data");
                    JSONObject user = data == null ? null : data.optJSONObject("user");
                    if (user == null) {
                        callback.onError("invalid_user");
                        return;
                    }
                    callback.onSuccess(
                            user.optInt("id", 0),
                            user.optString("username", ""),
                            user.optString("role", ""),
                            user.optInt("tenant_id", 1)
                    );
                } catch (Exception e) {
                    callback.onError("parse_error");
                }
            }
        });
    }

    public void bootstrap(String adminBase, String lang, final JsonCallback callback)
    {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        HttpUrl url = HttpUrl.parse(base + "/mobile_console/bootstrap");
        if (url == null) {
            callback.onError("invalid_url");
            return;
        }
        url = url.newBuilder().addQueryParameter("lang", AppPrefs.normalizeLanguage(lang)).build();
        Request req = new Request.Builder()
                .url(url)
                .header("Accept", "application/json")
                .get()
                .build();
        requestJson(req, callback);
    }

    public void logout(String adminBase, final JsonCallback callback)
    {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        Request req = new Request.Builder()
                .url(base + "/auth/logout")
                .header("Accept", "application/json")
                .post(new FormBody.Builder().build())
                .build();
        requestJson(req, callback);
    }

    public void createBatch(String adminBase, String taskType, int limit, final JsonCallback callback)
    {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        try {
            JSONObject payload = new JSONObject();
            payload.put("task_type", taskType == null ? "tiktok_dm" : taskType);
            payload.put("limit", Math.max(1, Math.min(200, limit)));
            Request req = new Request.Builder()
                    .url(base + "/mobile_task/create_batch")
                    .header("Accept", "application/json")
                    .post(RequestBody.create(payload.toString(), JSON))
                    .build();
            requestJson(req, callback);
        } catch (Exception e) {
            callback.onError("invalid_payload");
        }
    }

    public void listPendingTasks(String adminBase, final JsonCallback callback)
    {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        HttpUrl url = HttpUrl.parse(base + "/mobile_task/list");
        if (url == null) {
            callback.onError("invalid_url");
            return;
        }
        url = url.newBuilder()
                .addQueryParameter("task_status", "0")
                .addQueryParameter("page_size", "20")
                .build();
        Request req = new Request.Builder()
                .url(url)
                .header("Accept", "application/json")
                .get()
                .build();
        requestJson(req, callback);
    }

    public void listDashboardTasks(String adminBase, int pageSize, final JsonCallback callback)
    {
        listDashboardTasks(adminBase, 1, pageSize, "", "", "", callback);
    }

    public void listDashboardTasks(
            String adminBase,
            int page,
            int pageSize,
            String keyword,
            String taskType,
            String taskStatus,
            final JsonCallback callback
    ) {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        HttpUrl url = HttpUrl.parse(base + "/mobile_task/list");
        if (url == null) {
            callback.onError("invalid_url");
            return;
        }
        int size = Math.max(10, Math.min(80, pageSize));
        int currentPage = Math.max(1, page);
        HttpUrl.Builder builder = url.newBuilder()
                .addQueryParameter("page", String.valueOf(currentPage))
                .addQueryParameter("page_size", String.valueOf(size));
        String q = keyword == null ? "" : keyword.trim();
        if (!q.isEmpty()) {
            builder.addQueryParameter("keyword", q);
        }
        String t = taskType == null ? "" : taskType.trim().toLowerCase();
        if (!t.isEmpty()) {
            builder.addQueryParameter("task_type", t);
        }
        String s = taskStatus == null ? "" : taskStatus.trim();
        if (!s.isEmpty()) {
            builder.addQueryParameter("task_status", s);
        }
        Request req = new Request.Builder()
                .url(builder.build())
                .header("Accept", "application/json")
                .get()
                .build();
        requestJson(req, callback);
    }

    public void listDevices(String adminBase, final JsonCallback callback)
    {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        Request req = new Request.Builder()
                .url(base + "/mobile_device/list")
                .header("Accept", "application/json")
                .get()
                .build();
        requestJson(req, callback);
    }

    public void updateTaskStatus(
            String adminBase,
            int taskId,
            String event,
            String action,
            String renderedText,
            final JsonCallback callback
    ) {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        if (taskId <= 0) {
            callback.onError("invalid_task_id");
            return;
        }
        try {
            JSONObject payload = new JSONObject();
            payload.put("task_id", taskId);
            payload.put("event", event == null ? "" : event);
            payload.put("action", action == null ? "" : action);
            payload.put("rendered_text", renderedText == null ? "" : renderedText);
            Request req = new Request.Builder()
                    .url(base + "/mobile_task/update_status")
                    .header("Accept", "application/json")
                    .post(RequestBody.create(payload.toString(), JSON))
                    .build();
            requestJson(req, callback);
        } catch (Exception e) {
            callback.onError("invalid_payload");
        }
    }

    public void updateInfluencerStatus(String adminBase, int influencerId, int status, final JsonCallback callback)
    {
        String base = AppPrefs.normalizeAdminBase(adminBase);
        if (influencerId <= 0 || status < 0 || status > 6) {
            callback.onError("invalid_influencer_status");
            return;
        }
        RequestBody body = new FormBody.Builder()
                .add("id", String.valueOf(influencerId))
                .add("status", String.valueOf(status))
                .build();
        Request req = new Request.Builder()
                .url(base + "/influencer/updateStatus")
                .header("Accept", "application/json")
                .post(body)
                .build();
        requestJson(req, callback);
    }

    private void requestJson(Request request, final JsonCallback callback)
    {
        client.newCall(request).enqueue(new Callback()
        {
            @Override
            public void onFailure(Call call, IOException e)
            {
                callback.onError("network_error: " + (e.getMessage() == null ? "" : e.getMessage()));
            }

            @Override
            public void onResponse(Call call, Response response) throws IOException
            {
                String text = response.body() == null ? "" : response.body().string();
                if (!response.isSuccessful()) {
                    if (response.code() == 401) {
                        callback.onUnauthorized();
                        return;
                    }
                    callback.onError("http_" + response.code());
                    return;
                }
                try {
                    JSONObject root = new JSONObject(text);
                    int code = root.optInt("code", 1);
                    String msg = root.optString("msg", "");
                    if (code == 401 || "not_logged_in".equalsIgnoreCase(msg)) {
                        callback.onUnauthorized();
                        return;
                    }
                    if (code != 0) {
                        callback.onError(msg.isEmpty() ? "api_error" : msg);
                        return;
                    }
                    JSONObject data = root.optJSONObject("data");
                    if (data == null) {
                        data = new JSONObject();
                    }
                    callback.onSuccess(data);
                } catch (Exception e) {
                    callback.onError("parse_error");
                }
            }
        });
    }
}
