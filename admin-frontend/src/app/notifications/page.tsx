'use client';

import { useEffect, useState, useCallback } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { notifications } from '@/lib/api';

interface Notification {
  id: number;
  title?: string;
  message?: string;
  type?: string;
  read?: boolean;
  createdAt?: string;
  [key: string]: unknown;
}

interface NotifSettings {
  emailEnabled?: boolean;
  pushEnabled?: boolean;
  reviewReminder?: boolean;
  dailyGoalReminder?: boolean;
  streakReminder?: boolean;
  reminderTime?: string;
  [key: string]: unknown;
}

const typeColors: Record<string, { bg: string; color: string; icon: string }> = {
  REVIEW: { bg: '#c8e6ff', color: '#004c6e', icon: 'replay' },
  STREAK: { bg: '#ffdad6', color: '#93000a', icon: 'local_fire_department' },
  ACHIEVEMENT: { bg: '#ffdf92', color: '#594400', icon: 'emoji_events' },
  SYSTEM: { bg: '#efeded', color: '#3e4850', icon: 'info' },
};

export default function NotificationsPage() {
  const [notifList, setNotifList] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [settings, setSettings] = useState<NotifSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [savingSettings, setSavingSettings] = useState(false);
  const [settingsMsg, setSettingsMsg] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    const [n, u, s] = await Promise.allSettled([
      notifications.list(),
      notifications.unreadCount(),
      notifications.settings(),
    ]);
    if (n.status === 'fulfilled') {
      const val = n.value as unknown;
      setNotifList(Array.isArray(val) ? (val as Notification[]) : []);
    }
    if (u.status === 'fulfilled') {
      const val = u.value as unknown;
      if (typeof val === 'number') setUnreadCount(val);
      else if (val && typeof val === 'object' && (val as any).unread != null) setUnreadCount((val as any).unread as number);
      else setUnreadCount(0);
    }
    if (s.status === 'fulfilled') setSettings(s.value as NotifSettings);
    setLoading(false);
  }, []);

  useEffect(() => { load(); }, [load]);

  const markRead = async (id: number) => {
    try {
      await notifications.markRead(id);
      setNotifList(list => list.map(n => n.id === id ? { ...n, read: true } : n));
      setUnreadCount(c => Math.max(0, c - 1));
    } catch { /* ignore */ }
  };

  const saveSettings = async () => {
    if (!settings) return;
    setSavingSettings(true);
    try {
      await notifications.updateSettings(settings);
      setSettingsMsg('Settings saved!');
      setTimeout(() => setSettingsMsg(''), 3000);
    } catch {
      setSettingsMsg('Failed to save');
    } finally {
      setSavingSettings(false);
    }
  };

  const toggle = (key: keyof NotifSettings) => {
    setSettings(s => s ? { ...s, [key]: !s[key] } : s);
  };

  return (
    <AdminLayout title="Notifications">
      <div className="space-y-6">
        {/* Unread Badge */}
        {unreadCount > 0 && (
          <div className="flex items-center gap-3 p-4 rounded-2xl"
            style={{ backgroundColor: '#e8f4ff', border: '2px solid #1cb0f6' }}>
            <span className="material-symbols-outlined" style={{ color: '#006590' }}>notifications_active</span>
            <p className="text-sm font-bold" style={{ color: '#00405d' }}>
              You have {unreadCount} unread notification{unreadCount !== 1 ? 's' : ''}
            </p>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Notifications List */}
          <div className="lg:col-span-2 rounded-3xl"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="p-5 flex justify-between items-center rounded-t-[22px]"
              style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <h3 className="font-extrabold" style={{ color: '#1b1c1c' }}>
                All Notifications
              </h3>
              <span className="px-3 py-1 rounded-full text-xs font-bold"
                style={{ backgroundColor: unreadCount > 0 ? '#ffdad6' : '#efeded', color: unreadCount > 0 ? '#93000a' : '#3e4850' }}>
                {unreadCount} unread
              </span>
            </div>
            {loading ? (
              <div className="p-8 text-center text-sm" style={{ color: '#3e4850' }}>Loading...</div>
            ) : notifList.length === 0 ? (
              <div className="p-10 text-center">
                <span className="material-symbols-outlined text-5xl block mb-2" style={{ color: '#bdc8d2' }}>
                  notifications_none
                </span>
                <p className="text-sm font-bold" style={{ color: '#3e4850' }}>No notifications</p>
              </div>
            ) : (
              <div className="divide-y" style={{ '--tw-divide-opacity': 1 } as React.CSSProperties}>
                {notifList.map((n) => {
                  const tc = typeColors[n.type ?? ''] ?? typeColors.SYSTEM;
                  return (
                    <div key={n.id}
                      className="p-4 flex gap-4 items-start transition-colors"
                      style={{ backgroundColor: n.read ? '' : '#fafcff' }}>
                      <div className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                        style={{ backgroundColor: tc.bg }}>
                        <span className="material-symbols-outlined text-base" style={{ color: tc.color }}>
                          {tc.icon}
                        </span>
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex justify-between items-start gap-2">
                          <p className="font-bold text-sm" style={{ color: '#1b1c1c' }}>
                            {n.title ?? 'Notification'}
                          </p>
                          <span className="text-xs flex-shrink-0" style={{ color: '#bdc8d2' }}>
                            {n.createdAt ? new Date(n.createdAt as string).toLocaleDateString() : ''}
                          </span>
                        </div>
                        <p className="text-xs mt-0.5" style={{ color: '#3e4850' }}>
                          {n.message ?? ''}
                        </p>
                      </div>
                      {!n.read && (
                        <button onClick={() => markRead(n.id)}
                          className="flex-shrink-0 px-2.5 py-1 rounded-lg text-xs font-bold"
                          style={{ backgroundColor: '#c8e6ff', color: '#004c6e' }}>
                          Mark read
                        </button>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Settings Panel */}
          <div className="rounded-3xl"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="p-5 rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <h3 className="font-extrabold" style={{ color: '#1b1c1c' }}>Notification Settings</h3>
            </div>
            {loading || !settings ? (
              <div className="p-8 text-center text-sm" style={{ color: '#3e4850' }}>Loading settings...</div>
            ) : (
              <div className="p-5 space-y-4">
                {[
                  { key: 'emailEnabled' as keyof NotifSettings, label: 'Email Notifications', desc: 'Receive notifications via email' },
                  { key: 'pushEnabled' as keyof NotifSettings, label: 'Push Notifications', desc: 'Browser push alerts' },
                  { key: 'reviewReminder' as keyof NotifSettings, label: 'Review Reminders', desc: 'Daily FSRS review alerts' },
                  { key: 'dailyGoalReminder' as keyof NotifSettings, label: 'Daily Goal', desc: 'Remind to reach daily target' },
                  { key: 'streakReminder' as keyof NotifSettings, label: 'Streak Warning', desc: 'Alert before streak breaks' },
                ].map(({ key, label, desc }) => (
                  <div key={key} className="flex items-center justify-between p-3 rounded-xl"
                    style={{ backgroundColor: '#f5f3f3' }}>
                    <div>
                      <p className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{label}</p>
                      <p className="text-xs" style={{ color: '#3e4850' }}>{desc}</p>
                    </div>
                    <button onClick={() => toggle(key)}
                      className="relative w-11 h-6 rounded-full flex items-center px-0.5 transition-colors"
                      style={{ backgroundColor: settings[key] ? '#006590' : '#bdc8d2' }}>
                      <div className="w-5 h-5 rounded-full bg-white shadow transition-all"
                        style={{ marginLeft: settings[key] ? 'auto' : '0' }} />
                    </button>
                  </div>
                ))}

                {settings.reminderTime != null && (
                  <div>
                    <label className="block text-xs font-bold uppercase tracking-wider mb-1.5"
                      style={{ color: '#3e4850' }}>Reminder Time</label>
                    <input
                      type="time"
                      value={String(settings.reminderTime)}
                      onChange={e => setSettings(s => s ? { ...s, reminderTime: e.target.value } : s)}
                      className="w-full p-3 rounded-xl outline-none text-sm font-medium"
                      style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }}
                    />
                  </div>
                )}

                {settingsMsg && (
                  <div className="p-3 rounded-xl text-sm font-semibold text-center"
                    style={{
                      backgroundColor: settingsMsg.includes('Failed') ? '#ffdad6' : '#c8e6ff',
                      color: settingsMsg.includes('Failed') ? '#93000a' : '#004c6e',
                    }}>
                    {settingsMsg}
                  </div>
                )}

                <button onClick={saveSettings} disabled={savingSettings}
                  className="btn-tactile w-full py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
                  style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
                  {savingSettings ? 'Saving...' : 'Save Settings'}
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
