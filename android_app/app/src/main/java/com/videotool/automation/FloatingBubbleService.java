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
import android.widget.FrameLayout;
import android.widget.ImageView;

import androidx.annotation.Nullable;

import com.videotool.R;

public class FloatingBubbleService extends Service
{
    private WindowManager windowManager;
    private View bubbleView;
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

        FrameLayout bubble = new FrameLayout(this);
        GradientDrawable bg = new GradientDrawable();
        bg.setShape(GradientDrawable.OVAL);
        bg.setColor(0x993D63E6);
        bubble.setBackground(bg);

        ImageView icon = new ImageView(this);
        icon.setImageResource(R.mipmap.ic_launcher_round);
        icon.setScaleType(ImageView.ScaleType.CENTER_INSIDE);
        FrameLayout.LayoutParams iconLp = new FrameLayout.LayoutParams(dp(24), dp(24));
        iconLp.gravity = Gravity.CENTER;
        bubble.addView(icon, iconLp);
        bubbleView = bubble;

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
