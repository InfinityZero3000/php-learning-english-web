"use client";

import { useEffect, useState } from "react";
import { IconBell, IconBellFilled } from "@tabler/icons-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { api } from "@/lib/api";

type Notification = {
  id: number;
  title: string;
  message: string;
  type: "REVIEW_REMINDER" | "DAILY_DUE_REMINDER" | "EVENING_REVIEW_REMINDER" | "OVERDUE_REMINDER" | "STREAK_ALERT" | "ACHIEVEMENT" | "SYSTEM";
  deepLink?: string;
  isRead: boolean;
  createdAt: string;
};

const formatRelative = (dateStr: string) => {
  if (!dateStr) return "";
  const diff = Date.now() - new Date(dateStr).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return "Just now";
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  return `${Math.floor(h / 24)}d ago`;
};

export function NotificationWidget() {
  const [unreadCount, setUnreadCount] = useState(0);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [open, setOpen] = useState(false);

  const fetchNotifs = async () => {
    try {
      const [countData, list] = await Promise.all([
        api.unreadNotifications().catch(() => ({ unread: 0 })),
        api.notifications().catch(() => [])
      ]);
      setUnreadCount((countData as any).unread ?? 0);
      setNotifications((list as any) || []);
    } catch {
      // Ignore
    }
  };

  useEffect(() => {
    fetchNotifs();
    const interval = setInterval(fetchNotifs, 60_000);
    return () => clearInterval(interval);
  }, []);

  const handleOpenChange = (newOpen: boolean) => {
    setOpen(newOpen);
    if (newOpen) {
      fetchNotifs();
    }
  };

  const markAllRead = async () => {
    const unread = notifications.filter(n => !n.isRead);
    await Promise.allSettled(
      unread.map(n => api.markNotificationRead(n.id).catch(() => {}))
    );
    fetchNotifs();
  };

  const handleRead = async (id: number, deepLink?: string) => {
    const n = notifications.find(n => n.id === id);
    if (n && !n.isRead) {
      await api.markNotificationRead(id).catch(() => {});
      setNotifications(prev =>
        prev.map(x => (x.id === id ? { ...x, isRead: true } : x))
      );
      setUnreadCount(c => Math.max(0, c - 1));
    }
    if (deepLink?.trim()) {
      window.location.assign(deepLink);
    }
  };

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <button
          className="group relative flex h-12 w-12 items-center justify-center rounded-full transition hover:bg-muted"
          aria-label="Notifications"
        >
          {unreadCount > 0 ? (
            <IconBellFilled className="h-7 w-7 text-[#404a52] transition-transform group-hover:scale-110" />
          ) : (
            <IconBell className="h-7 w-7 text-[#404a52] transition-transform group-hover:scale-110" stroke={2} />
          )}
          {unreadCount > 0 ? (
            <span className="absolute right-1 top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-xs font-bold text-white shadow-sm ring-2 ring-card">
              {unreadCount > 99 ? "99+" : unreadCount}
            </span>
          ) : null}
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-[360px] p-0 shadow-lg border-2 border-border overflow-hidden rounded-2xl bg-card">
        <div className="flex items-center justify-between border-b border-border bg-card px-5 py-4">
          <span className="font-display text-[15px] font-bold text-foreground">Notifications</span>
          <button
            onClick={markAllRead}
            className="rounded-lg px-2 py-1 font-display text-[13px] font-bold text-primary transition hover:bg-accent focus:outline-none"
          >
            Mark all read
          </button>
        </div>
        <div className="max-h-[380px] overflow-y-auto bg-card">
          {notifications.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-10 text-center">
              <IconBell className="mb-3 h-10 w-10 text-muted-foreground opacity-50" stroke={1.5} />
              <p className="font-sans text-[15px] text-muted-foreground">No notifications</p>
            </div>
          ) : (
            notifications.map((n) => {
              const unread = !n.isRead;
              return (
                <div
                  key={n.id}
                  onClick={() => handleRead(n.id, n.deepLink)}
                  className={`flex cursor-pointer items-start gap-4 border-b border-border px-5 py-3 transition ${
                    unread ? "bg-accent/50 hover:bg-accent/70" : "bg-card hover:bg-muted"
                  }`}
                >
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                   <IconBellFilled className="h-5 w-5 text-primary" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className={`font-display text-[14px] leading-tight ${unread ? "font-bold text-foreground" : "font-semibold text-foreground/90"}`}>
                      {n.title}
                    </p>
                    <p className="font-sans text-[13px] text-muted-foreground mt-0.5 leading-snug">
                      {n.message}
                    </p>
                    <p className="font-sans text-[11px] text-muted-foreground/70 mt-1.5 font-medium">
                      {formatRelative(n.createdAt)}
                    </p>
                  </div>
                  {unread && (
                    <div className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary shadow-[0_0_8px_rgba(0,101,144,0.6)]" />
                  )}
                </div>
              );
            })
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
