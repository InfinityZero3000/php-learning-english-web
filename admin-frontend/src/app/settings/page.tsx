'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { notifications, fsrs } from '@/lib/api';

interface NotifSettings {
  emailEnabled?: boolean;
  pushEnabled?: boolean;
  reviewReminder?: boolean;
  dailyGoalReminder?: boolean;
  streakReminder?: boolean;
  reminderTime?: string;
  eveningRemainingThreshold?: number;
  [key: string]: unknown;
}

interface FsrsStats {
  averageStability?: number;
  averageDifficulty?: number;
  retentionRate?: number;
  dueCount?: number;
  [key: string]: unknown;
}

function Toggle({ value, onChange }: { value: boolean; onChange: (v: boolean) => void }) {
  return (
    <button onClick={() => onChange(!value)}
      className="relative w-12 h-7 rounded-full flex items-center px-0.5 transition-colors"
      style={{ backgroundColor: value ? '#006590' : '#bdc8d2' }}>
      <div className="w-5 h-5 rounded-full bg-white shadow transition-all"
        style={{ marginLeft: value ? 'auto' : '0' }} />
    </button>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-3xl"
      style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
      <div className="p-5 rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
        <h3 className="font-extrabold" style={{ color: '#1b1c1c' }}>{title}</h3>
      </div>
      <div className="p-6 space-y-4">{children}</div>
    </div>
  );
}

function SettingRow({ icon, label, desc, children }: {
  icon: string; label: string; desc: string; children: React.ReactNode;
}) {
  return (
    <div className="flex items-center justify-between p-4 rounded-2xl"
      style={{ backgroundColor: '#f5f3f3' }}>
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 rounded-xl flex items-center justify-center"
          style={{ backgroundColor: '#c8e6ff' }}>
          <span className="material-symbols-outlined text-lg" style={{ color: '#006590' }}>{icon}</span>
        </div>
        <div>
          <p className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{label}</p>
          <p className="text-xs" style={{ color: '#3e4850' }}>{desc}</p>
        </div>
      </div>
      {children}
    </div>
  );
}

export default function SettingsPage() {
  const [notifSettings, setNotifSettings] = useState<NotifSettings | null>(null);
  const [fsrsStats, setFsrsStats] = useState<FsrsStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState('');

  useEffect(() => {
    const load = async () => {
      const [n, f] = await Promise.allSettled([
        notifications.settings(),
        fsrs.stats(),
      ]);
      if (n.status === 'fulfilled') setNotifSettings(n.value as NotifSettings);
      if (f.status === 'fulfilled') setFsrsStats(f.value as FsrsStats);
      setLoading(false);
    };
    load();
  }, []);

  const saveNotifSettings = async () => {
    if (!notifSettings) return;
    setSaving(true);
    try {
      await notifications.updateSettings(notifSettings);
      setMsg('Settings saved successfully!');
    } catch {
      setMsg('Failed to save settings');
    } finally {
      setSaving(false);
      setTimeout(() => setMsg(''), 4000);
    }
  };

  const setNotif = (key: keyof NotifSettings, val: unknown) => {
    setNotifSettings(s => s ? { ...s, [key]: val } : s);
  };

  return (
    <AdminLayout title="Settings">
      <div className="space-y-6 max-w-3xl">
        {/* FSRS Status */}
        <Section title="FSRS Algorithm Status">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            {[
              { label: 'Due Count', value: fsrsStats?.dueCount ?? '—', color: '#ba1a1a' },
              { label: 'Avg Stability', value: fsrsStats?.averageStability != null ? `${(fsrsStats.averageStability as number).toFixed(1)}d` : '—', color: '#006590' },
              { label: 'Avg Difficulty', value: fsrsStats?.averageDifficulty != null ? (fsrsStats.averageDifficulty as number).toFixed(2) : '—', color: '#843ab4' },
              { label: 'Retention', value: fsrsStats?.retentionRate != null ? `${Math.round(fsrsStats.retentionRate as number)}%` : '—', color: '#755b00' },
            ].map(({ label, value, color }) => (
              <div key={label} className="p-4 rounded-2xl text-center"
                style={{ backgroundColor: '#f5f3f3' }}>
                <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: '#3e4850' }}>{label}</p>
                <p className="text-2xl font-extrabold" style={{ color }}>
                  {loading ? '...' : String(value)}
                </p>
              </div>
            ))}
          </div>
        </Section>

        {/* Notification Settings */}
        <Section title="Notification Preferences">
          {loading || !notifSettings ? (
            <div className="text-center py-4 text-sm" style={{ color: '#3e4850' }}>Loading...</div>
          ) : (
            <>
              <SettingRow icon="email" label="Email Notifications" desc="Send learning reminders via email">
                <Toggle value={!!notifSettings.emailEnabled} onChange={v => setNotif('emailEnabled', v)} />
              </SettingRow>
              <SettingRow icon="notifications" label="Review Reminders" desc="Alert when FSRS cards are due">
                <Toggle value={!!notifSettings.reviewReminder} onChange={v => setNotif('reviewReminder', v)} />
              </SettingRow>
              <SettingRow icon="flag" label="Daily Goal Reminder" desc="Motivate to reach daily word target">
                <Toggle value={!!notifSettings.dailyGoalReminder} onChange={v => setNotif('dailyGoalReminder', v)} />
              </SettingRow>
              <SettingRow icon="local_fire_department" label="Streak Warning" desc="Alert before losing a streak">
                <Toggle value={!!notifSettings.streakReminder} onChange={v => setNotif('streakReminder', v)} />
              </SettingRow>

              {notifSettings.eveningRemainingThreshold != null && (
                <div className="p-4 rounded-2xl" style={{ backgroundColor: '#f5f3f3' }}>
                  <label className="block text-xs font-bold uppercase tracking-wider mb-2"
                    style={{ color: '#3e4850' }}>
                    Evening Reminder Threshold (cards remaining)
                  </label>
                  <input
                    type="number" min={0} max={100}
                    value={notifSettings.eveningRemainingThreshold as number}
                    onChange={e => setNotif('eveningRemainingThreshold', parseInt(e.target.value))}
                    className="w-full p-3 rounded-xl outline-none text-sm font-medium"
                    style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c' }}
                    onFocus={e => (e.target.style.borderColor = '#006590')}
                    onBlur={e => (e.target.style.borderColor = '#bdc8d2')}
                  />
                </div>
              )}

              {msg && (
                <div className="p-3 rounded-xl text-center text-sm font-semibold"
                  style={{
                    backgroundColor: msg.includes('Failed') ? '#ffdad6' : '#c8e6ff',
                    color: msg.includes('Failed') ? '#93000a' : '#004c6e',
                  }}>
                  {msg}
                </div>
              )}

              <button onClick={saveNotifSettings} disabled={saving}
                className="btn-tactile w-full py-3.5 rounded-2xl font-bold text-sm uppercase tracking-wider"
                style={{ backgroundColor: saving ? '#bdc8d2' : '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
                {saving ? 'Saving...' : 'Save Notification Settings'}
              </button>
            </>
          )}
        </Section>

        {/* About */}
        <Section title="About">
          <div className="space-y-3">
            {[
              { label: 'System', value: 'Linguist — FSRSpring Vocabulary' },
              { label: 'Algorithm', value: 'FSRS (Free Spaced Repetition Scheduler)' },
              { label: 'Architecture', value: 'Modular Monolith — Java 17 + Spring Boot' },
              { label: 'Frontend', value: 'Next.js 16 + Tailwind CSS v4' },
              { label: 'Database', value: 'MySQL (production) / H2 (dev)' },
            ].map(({ label, value }) => (
              <div key={label} className="flex justify-between items-center p-3 rounded-xl"
                style={{ backgroundColor: '#f5f3f3' }}>
                <span className="text-xs font-bold uppercase tracking-wide" style={{ color: '#3e4850' }}>{label}</span>
                <span className="text-xs font-bold" style={{ color: '#1b1c1c' }}>{value}</span>
              </div>
            ))}
          </div>
        </Section>
      </div>
    </AdminLayout>
  );
}
