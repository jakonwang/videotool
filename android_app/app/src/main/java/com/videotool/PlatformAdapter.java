package com.videotool;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import java.util.List;

public class PlatformAdapter extends RecyclerView.Adapter<PlatformAdapter.ViewHolder> {
    
    private List<Platform> platformList;
    private OnPlatformClickListener listener;
    
    public interface OnPlatformClickListener {
        void onPlatformClick(Platform platform);
    }
    
    public PlatformAdapter(List<Platform> platformList, OnPlatformClickListener listener) {
        this.platformList = platformList;
        this.listener = listener;
    }
    
    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_platform, parent, false);
        return new ViewHolder(view);
    }
    
    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        Platform platform = platformList.get(position);
        holder.nameTextView.setText(platform.getName());
        holder.itemView.setOnClickListener(v -> {
            if (listener != null) {
                listener.onPlatformClick(platform);
            }
        });
    }
    
    @Override
    public int getItemCount() {
        return platformList.size();
    }
    
    static class ViewHolder extends RecyclerView.ViewHolder {
        TextView nameTextView;
        
        ViewHolder(View itemView) {
            super(itemView);
            nameTextView = itemView.findViewById(R.id.platform_name);
        }
    }
}

