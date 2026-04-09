package com.videotool.agent;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Intent;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.text.TextUtils;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

import com.videotool.AgentControlActivity;
import com.videotool.R;

public class MobileAgentService extends Service {
    public static final String ACTION_START = "com.videotool.agent.action.START";
    public static final String ACTION_STOP = "com.videotool.agent.action.STOP";
    public static final String ACTION_PULL_ONCE = "com.videotool.agent.action.PULL_ONCE";
    public static final String ACTION_MARK_SENT = "com.videotool.agent.action.MARK_SENT";
    public static final String ACTION_MARK_SKIP = "com.videotool.agent.action.MARK_SKIP";
    public static final String ACTION_MARK_FAIL = "com.videotool.agent.action.MARK_FAIL";
    public static final String ACTION_OPEN_TARGET = "com.videotool.agent.action.OPEN_TARGET";
    public static final String ACTION_SYNC_STATE = "com.videotool.agent.action.SYNC_STATE";

    public static final String EXTRA_ERROR_MESSAGE = "error_message";

    public static final String BROADCAST_STATE = "com.videotool.agent.broadcast.STATE";
    public static final String EXTRA_RUNNING = "running";
    public static final String EXTRA_POLLING = "polling";
    public static final String EXTRA_AWAITING = "awaiting_manual";
    public static final String EXTRA_STATUS = "status_text";
    public static final String EXTRA_LOG_LINE = "log_line";
    public static final String EXTRA_TASK_JSON = "task_json";

    private static final String CHANNEL_ID = "mobile_agent_channel";
    private static final int NOTIFICATION_ID = 31012;

    private Handler mainHandler;
    private AgentPrefs prefs;
    private AgentApiClient apiClient;
    private MobileTaskExecutor executor;
    private AgentConfig config;

    private boolean running = false;
    private boolean polling = false;
    private boolean autoMode = false;
    private AgentTask currentTask;

    private final Runnable pollRunnable = new Runnable() {
        @Override
        public void run() {
            pollOnce(false);
        }
    };

    @Override
    public void onCreate() {
        super.onCreate();
        mainHandler = new Handler(Looper.getMainLooper());
        prefs = new AgentPrefs(this);
        apiClient = new AgentApiClient();
        executor = new MobileTaskExecutor(this);
        config = prefs.loadConfig();
        currentTask = prefs.loadCurrentTask();
        running = false;
        polling = false;
        autoMode = config != null && config.isAutoMode();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        String action = intent == null ? ACTION_START : intent.getAction();
        if (action == null || action.trim().isEmpty()) {
            action = ACTION_START;
        }

        switch (action) {
            case ACTION_START:
                handleStart();
                break;
            case ACTION_STOP:
                handleStop("agent_stopped");
                break;
            case ACTION_PULL_ONCE:
                if (!running) {
                    handleStart();
                }
                pollOnce(true);
                break;
            case ACTION_MARK_SENT:
                markCurrentTask(currentTask == null ? "done" : currentTask.getDoneEvent(), "", "");
                break;
            case ACTION_MARK_SKIP:
                markCurrentTask("skip", "", "manual_skip");
                break;
            case ACTION_MARK_FAIL:
                String reason = intent == null ? "" : intent.getStringExtra(EXTRA_ERROR_MESSAGE);
                if (TextUtils.isEmpty(reason)) {
                    reason = "manual_fail";
                }
                markCurrentTask("failed", "manual_fail", reason);
                break;
            case ACTION_OPEN_TARGET:
                reopenCurrentTask();
                break;
            case ACTION_SYNC_STATE:
                emitState("state_sync");
                break;
            default:
                handleStart();
                break;
        }
        return START_STICKY;
    }

    private void handleStart() {
        config = prefs.loadConfig();
        if (config == null || !config.isValid()) {
            running = false;
            prefs.setRunning(false);
            emitState("config_invalid");
            stopSelf();
            return;
        }
        autoMode = config.isAutoMode();
        running = true;
        prefs.setRunning(true);
        startForegroundCompat("Mobile Agent running");
        if (currentTask != null) {
            emitState("resumed_waiting_task_" + currentTask.getId());
            refreshNotification();
            return;
        }
        emitState("agent_started");
        scheduleNextPoll(300);
    }

    private void handleStop(String logLine) {
        running = false;
        polling = false;
        prefs.setRunning(false);
        mainHandler.removeCallbacksAndMessages(null);
        refreshNotification();
        emitState(logLine);
        stopForeground(true);
        stopSelf();
    }

    private void pollOnce(boolean manual) {
        if (!running || config == null || !config.isValid()) {
            emitState("agent_not_running");
            return;
        }
        if (currentTask != null) {
            emitState("awaiting_manual_task_" + currentTask.getId());
            return;
        }
        if (polling) {
            if (manual) {
                emitState("already_polling");
            }
            return;
        }

        polling = true;
        refreshNotification();
        emitState(manual ? "manual_pull" : "polling_queue");
        AgentApiClient.ApiCallback<AgentApiClient.PullResult> pullCallback = new AgentApiClient.ApiCallback<AgentApiClient.PullResult>() {
            @Override
            public void onSuccess(final AgentApiClient.PullResult result) {
                mainHandler.post(new Runnable() {
                    @Override
                    public void run() {
                        polling = false;
                        if (result == null || result.task == null) {
                            String reason = result == null ? "" : result.reason;
                            if (!TextUtils.isEmpty(reason)) {
                                emitState("queue_idle_" + reason);
                            }
                            refreshNotification();
                            if (running) {
                                scheduleNextPoll(config.getPollIntervalSec() * 1000L);
                            }
                            return;
                        }

                        currentTask = result.task;
                        prefs.saveCurrentTask(currentTask);
                        emitState("task_pulled_" + currentTask.getId());
                        refreshNotification();
                        executeCurrentTask();
                    }
                });
            }

            @Override
            public void onError(final String errorMessage) {
                mainHandler.post(new Runnable() {
                    @Override
                    public void run() {
                        polling = false;
                        emitState(errorMessage);
                        refreshNotification();
                        if (running) {
                            scheduleNextPoll(Math.max(5000L, config.getPollIntervalSec() * 1000L));
                        }
                    }
                });
            }
        };
        if (autoMode) {
            apiClient.pullTaskAuto(config, pullCallback);
        } else {
            apiClient.pullTask(config, pullCallback);
        }
    }

    private void executeCurrentTask() {
        if (currentTask == null) {
            return;
        }
        MobileTaskExecutor.ExecutionResult result = executor.prepareTask(currentTask);
        if (!result.ok) {
            final AgentTask failedTask = currentTask;
            reportTask(
                    failedTask,
                    "failed",
                    failedTask.getBestText(),
                    "prepare_failed",
                    result.error,
                    "",
                    new AgentApiClient.ApiCallback<AgentApiClient.ReportResult>() {
                        @Override
                        public void onSuccess(AgentApiClient.ReportResult reportResult) {
                            mainHandler.post(new Runnable() {
                                @Override
                                public void run() {
                                    emitState("task_failed_" + failedTask.getId() + "_prepare_failed");
                                    clearCurrentTaskAndContinue();
                                }
                            });
                        }

                        @Override
                        public void onError(final String errorMessage) {
                            mainHandler.post(new Runnable() {
                                @Override
                                public void run() {
                                    emitState("task_failed_report_error_" + errorMessage);
                                    clearCurrentTaskAndContinue();
                                }
                            });
                        }
                    }
            );
            return;
        }

        final AgentTask preparedTask = currentTask;
        if (autoMode && preparedTask.isAutoDmTask()) {
            reportTask(
                    preparedTask,
                    preparedTask.getAutoSendingEvent(),
                    preparedTask.getBestText(),
                    "",
                    "",
                    "",
                    new AgentApiClient.ApiCallback<AgentApiClient.ReportResult>() {
                        @Override
                        public void onSuccess(AgentApiClient.ReportResult reportResult) {
                            mainHandler.post(new Runnable() {
                                @Override
                                public void run() {
                                    reportTask(
                                            preparedTask,
                                            preparedTask.getAutoDoneEvent(),
                                            preparedTask.getBestText(),
                                            "",
                                            "",
                                            "",
                                            new AgentApiClient.ApiCallback<AgentApiClient.ReportResult>() {
                                                @Override
                                                public void onSuccess(AgentApiClient.ReportResult reportResult2) {
                                                    mainHandler.post(new Runnable() {
                                                        @Override
                                                        public void run() {
                                                            emitState("task_auto_sent_" + preparedTask.getId());
                                                            clearCurrentTaskAndContinue();
                                                        }
                                                    });
                                                }

                                                @Override
                                                public void onError(final String errorMessage) {
                                                    mainHandler.post(new Runnable() {
                                                        @Override
                                                        public void run() {
                                                            emitState("task_auto_sent_report_error_" + errorMessage);
                                                            clearCurrentTaskAndContinue();
                                                        }
                                                    });
                                                }
                                            }
                                    );
                                }
                            });
                        }

                        @Override
                        public void onError(final String errorMessage) {
                            mainHandler.post(new Runnable() {
                                @Override
                                public void run() {
                                    emitState("task_auto_sending_report_error_" + errorMessage);
                                    clearCurrentTaskAndContinue();
                                }
                            });
                        }
                    }
            );
            return;
        }

        reportTask(
                preparedTask,
                preparedTask.getPreparedEvent(),
                preparedTask.getBestText(),
                "",
                "",
                "",
                new AgentApiClient.ApiCallback<AgentApiClient.ReportResult>() {
                    @Override
                    public void onSuccess(AgentApiClient.ReportResult reportResult) {
                        mainHandler.post(new Runnable() {
                            @Override
                            public void run() {
                                emitState("task_prepared_" + preparedTask.getId() + "_manual_send_required");
                                refreshNotification();
                            }
                        });
                    }

                    @Override
                    public void onError(final String errorMessage) {
                        mainHandler.post(new Runnable() {
                            @Override
                            public void run() {
                                emitState("prepared_report_error_" + errorMessage);
                                refreshNotification();
                            }
                        });
                    }
                }
        );
    }

    private void markCurrentTask(final String event, final String errorCode, final String errorMessage) {
        if (currentTask == null) {
            emitState("no_active_task");
            return;
        }
        final AgentTask task = currentTask;
        reportTask(
                task,
                event,
                task.getBestText(),
                errorCode,
                errorMessage,
                "",
                new AgentApiClient.ApiCallback<AgentApiClient.ReportResult>() {
                    @Override
                    public void onSuccess(AgentApiClient.ReportResult reportResult) {
                        mainHandler.post(new Runnable() {
                            @Override
                            public void run() {
                                emitState("task_" + task.getId() + "_reported_" + event);
                                clearCurrentTaskAndContinue();
                            }
                        });
                    }

                    @Override
                    public void onError(final String errorMessageValue) {
                        mainHandler.post(new Runnable() {
                            @Override
                            public void run() {
                                emitState("report_error_" + errorMessageValue);
                                refreshNotification();
                            }
                        });
                    }
                }
        );
    }

    private void clearCurrentTaskAndContinue() {
        currentTask = null;
        prefs.clearCurrentTask();
        refreshNotification();
        if (running) {
            scheduleNextPoll(500);
        }
    }

    private void reopenCurrentTask() {
        if (currentTask == null) {
            emitState("no_active_task");
            return;
        }
        boolean ok = executor.reopenTask(currentTask);
        if (ok) {
            emitState("task_reopened_" + currentTask.getId());
        } else {
            emitState("reopen_failed");
        }
    }

    private void scheduleNextPoll(long delayMs) {
        mainHandler.removeCallbacks(pollRunnable);
        if (!running || currentTask != null) {
            return;
        }
        mainHandler.postDelayed(pollRunnable, Math.max(200L, delayMs));
    }

    private void emitState(String logLine) {
        String status = buildStatusText();
        prefs.setLastStatus(status);
        if (logLine != null && !logLine.trim().isEmpty()) {
            prefs.setLastLog(logLine);
        }

        Intent intent = new Intent(BROADCAST_STATE);
        intent.setPackage(getPackageName());
        intent.putExtra(EXTRA_RUNNING, running);
        intent.putExtra(EXTRA_POLLING, polling);
        intent.putExtra(EXTRA_AWAITING, currentTask != null);
        intent.putExtra(EXTRA_STATUS, status);
        intent.putExtra(EXTRA_LOG_LINE, logLine == null ? "" : logLine);
        intent.putExtra(EXTRA_TASK_JSON, currentTask == null ? "" : currentTask.toJson().toString());
        sendBroadcast(intent);
        refreshNotification();
    }

    private String buildStatusText() {
        if (!running) {
            return "Stopped";
        }
        if (polling) {
            return autoMode ? "Polling auto queue" : "Polling queue";
        }
        if (currentTask != null) {
            if (autoMode && currentTask.isAutoDmTask()) {
                return "Auto sending: #" + currentTask.getId();
            }
            return "Awaiting manual send: #" + currentTask.getId();
        }
        return autoMode ? "Running (Auto)" : "Running";
    }

    private void reportTask(
            final AgentTask task,
            final String event,
            final String renderedText,
            final String errorCode,
            final String errorMessage,
            final String screenshotPath,
            final AgentApiClient.ApiCallback<AgentApiClient.ReportResult> callback
    ) {
        if (task == null) {
            if (callback != null) {
                callback.onError("task_null");
            }
            return;
        }
        if (config == null || !config.isValid()) {
            if (callback != null) {
                callback.onError("config_invalid");
            }
            return;
        }
        if (autoMode && task.isAutoDmTask()) {
            apiClient.reportAuto(
                    config,
                    task.getId(),
                    event,
                    renderedText,
                    errorCode,
                    errorMessage,
                    screenshotPath,
                    callback
            );
            return;
        }
        apiClient.report(
                config,
                task.getId(),
                event,
                renderedText,
                errorCode,
                errorMessage,
                screenshotPath,
                callback
        );
    }

    private void startForegroundCompat(String text) {
        createChannelIfNeeded();
        Notification notification = buildNotification(text);
        startForeground(NOTIFICATION_ID, notification);
    }

    private void refreshNotification() {
        if (!running) {
            return;
        }
        NotificationManager manager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        if (manager == null) {
            return;
        }
        manager.notify(NOTIFICATION_ID, buildNotification(buildStatusText()));
    }

    private Notification buildNotification(String text) {
        Intent openIntent = new Intent(this, AgentControlActivity.class);
        openIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        PendingIntent pendingIntent = PendingIntent.getActivity(
                this,
                100,
                openIntent,
                pendingFlags()
        );

        Intent stopIntent = new Intent(this, MobileAgentService.class);
        stopIntent.setAction(ACTION_STOP);
        PendingIntent stopPending = PendingIntent.getService(
                this,
                101,
                stopIntent,
                pendingFlags()
        );

        String contentText = text;
        if (currentTask != null) {
            contentText = contentText + " | " + currentTask.getDisplayName();
        }

        return new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle("TikStar Mobile Agent")
                .setContentText(contentText)
                .setContentIntent(pendingIntent)
                .setOngoing(true)
                .setOnlyAlertOnce(true)
                .addAction(R.mipmap.ic_launcher, "Stop", stopPending)
                .build();
    }

    private void createChannelIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }
        NotificationManager manager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        if (manager == null) {
            return;
        }
        NotificationChannel channel = manager.getNotificationChannel(CHANNEL_ID);
        if (channel != null) {
            return;
        }
        NotificationChannel created = new NotificationChannel(
                CHANNEL_ID,
                "Mobile Agent",
                NotificationManager.IMPORTANCE_LOW
        );
        created.setDescription("TikStar OPS Mobile Agent foreground service");
        manager.createNotificationChannel(created);
    }

    private int pendingFlags() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            return PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE;
        }
        return PendingIntent.FLAG_UPDATE_CURRENT;
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        mainHandler.removeCallbacksAndMessages(null);
        polling = false;
    }
}
