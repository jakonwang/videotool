package com.videotool.automation;

import android.app.Service;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.graphics.drawable.GradientDrawable;
import android.os.Build;
import android.os.IBinder;
import android.view.Gravity;
import android.view.MotionEvent;
import android.view.View;
import android.view.WindowManager;
import android.widget.TextView;

import androidx.annotation.Nullable;

public class FloatingBubbleService extends Service
{
    private WindowManager windowManager;
    private TextView bubbleView;
    private WindowManager.LayoutParams layoutParams;
    private float touchStartX;
    private float touchStartY;
    private int bubbleStartX;
    private int bubbleStartY;
    private boolean moved;

    @Override
    public void onCreate()
    {
        super.onCreate();
        createBubble();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId)
    {
        if (bubbleView == null) {
            createBubble();
        }
        return START_STICKY;
    }

    private void createBubble()
    {
        windowManager = (WindowManager) getSystemService(WINDOW_SERVICE);
        if (windowManager == null || bubbleView != null) {
            return;
        }

        bubbleView = new TextView(this);
        bubbleView.setText("●");
        bubbleView.setTextSize(18f);
        bubbleView.setGravity(Gravity.CENTER);
        bubbleView.setTextColor(0xCCFFFFFF);
        GradientDrawable bg = new GradientDrawable();
        bg.setShape(GradientDrawable.OVAL);
        bg.setColor(0x883D63E6);
        bubbleView.setBackground(bg);

        int type = Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
                ? WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
                : WindowManager.LayoutParams.TYPE_PHONE;

        layoutParams = new WindowManager.LayoutParams(
                dp(40),
                dp(40),
                type,
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                        | WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
                PixelFormat.TRANSLUCENT
        );
        layoutParams.gravity = Gravity.START | Gravity.CENTER_VERTICAL;
        layoutParams.x = dp(10);
        layoutParams.y = 0;

        bubbleView.setOnTouchListener((v, event) ->
        {
            switch (event.getActionMasked()) {
                case MotionEvent.ACTION_DOWN:
                    moved = false;
                    touchStartX = event.getRawX();
                    touchStartY = event.getRawY();
                    bubbleStartX = layoutParams.x;
                    bubbleStartY = layoutParams.y;
                    return true;
                case MotionEvent.ACTION_MOVE:
                    int dx = (int) (event.getRawX() - touchStartX);
                    int dy = (int) (event.getRawY() - touchStartY);
                    if (Math.abs(dx) > 6 || Math.abs(dy) > 6) {
                        moved = true;
                    }
                    layoutParams.x = bubbleStartX + dx;
                    layoutParams.y = bubbleStartY + dy;
                    windowManager.updateViewLayout(bubbleView, layoutParams);
                    return true;
                case MotionEvent.ACTION_UP:
                    if (!moved) {
                        CommentAutomationBridge.triggerAutomation(this);
                    }
                    return true;
                default:
                    return false;
            }
        });

        windowManager.addView(bubbleView, layoutParams);
    }

    private int dp(int value)
    {
        float density = getResources().getDisplayMetrics().density;
        return Math.round(value * density);
    }

    @Override
    public void onDestroy()
    {
        if (windowManager != null && bubbleView != null) {
            try {
                windowManager.removeView(bubbleView);
            } catch (Exception ignore) {
            }
        }
        bubbleView = null;
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent)
    {
        return null;
    }
}
