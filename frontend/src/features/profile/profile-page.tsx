"use client";

import { IconBellRinging, IconCheck, IconDoorEnter, IconPhoto, IconUser } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  backgroundOptions,
  cacheBackgroundImage,
  clearSelectedBackground,
  isBackgroundImageCached,
  readSelectedBackground,
  saveSelectedBackground,
  type BackgroundOption
} from "@/lib/background-settings";
import { api, profile } from "@/lib/api";
import { cn, formatDateTime } from "@/lib/utils";
import type { AppUser, ReminderSettings } from "@/types/api";

const defaultReminderSettings: ReminderSettings = {
  reviewRemindersEnabled: true,
  preferredReminderTime: "20:00",
  eveningReminderEnabled: true,
  eveningReminderTime: "21:30",
  comebackReminderEnabled: true,
  comebackReminderIntervalDays: 3,
  eveningRemainingThreshold: 10
};

function toTimeInput(value?: string) {
  return value ? value.slice(0, 5) : "";
}

export function ProfilePage() {
  const [user, setUser] = useState<AppUser | null>(null);
  const [summary, setSummary] = useState({ words: 0, streak: 0 });
  const [loading, setLoading] = useState(true);
  const [selectedBackgroundId, setSelectedBackgroundId] = useState<string | null>(null);
  const [cachedBackgroundIds, setCachedBackgroundIds] = useState<Set<string>>(new Set());
  const [savingBackgroundId, setSavingBackgroundId] = useState<string | null>(null);
  const [reminderSettings, setReminderSettings] = useState<ReminderSettings>(defaultReminderSettings);
  const [savingReminders, setSavingReminders] = useState(false);
  const [reminderStatus, setReminderStatus] = useState<string | null>(null);
  const [displayName, setDisplayName] = useState("");
  const [profileStatus, setProfileStatus] = useState("");

  useEffect(() => {
    Promise.all([
      api.me().catch(() => null),
      api.wordCount().catch(() => ({ count: 0 })),
      api.streak().catch(() => ({} as Record<string, number>)),
      api.reminderSettings().catch(() => null),
    ]).then(([me, words, streak, reminders]) => {
      setUser(me);
      setDisplayName(me?.name ?? "");
      setSummary({ words: words.count ?? 0, streak: streak.currentStreak ?? 0 });
      if (reminders) {
        setReminderSettings({
          ...defaultReminderSettings,
          ...reminders,
          preferredReminderTime: toTimeInput(reminders.preferredReminderTime),
          eveningReminderTime: toTimeInput(reminders.eveningReminderTime)
        });
      }
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  async function saveProfile(event: React.FormEvent) {
    event.preventDefault();
    setProfileStatus("");
    try {
      const updated = await profile.update(displayName);
      setUser(updated);
      setProfileStatus("Saved");
    } catch {
      setProfileStatus("Could not save");
    }
  }

  useEffect(() => {
    const selected = readSelectedBackground();
    setSelectedBackgroundId(selected?.id ?? null);

    Promise.all(
      backgroundOptions.map(async (option) => ({
        id: option.id,
        cached: await isBackgroundImageCached(option)
      }))
    ).then((items) => {
      setCachedBackgroundIds(new Set(items.filter((item) => item.cached).map((item) => item.id)));
    });
  }, []);

  async function selectBackground(option: BackgroundOption) {
    setSavingBackgroundId(option.id);
    const cached = await cacheBackgroundImage(option);

    if (cached) {
      setCachedBackgroundIds((current) => new Set(current).add(option.id));
    }

    saveSelectedBackground(option);
    setSelectedBackgroundId(option.id);
    setSavingBackgroundId(null);
  }

  function selectDefaultBackground() {
    clearSelectedBackground();
    setSelectedBackgroundId(null);
  }

  function updateReminderSetting<K extends keyof ReminderSettings>(key: K, value: ReminderSettings[K]) {
    setReminderStatus(null);
    setReminderSettings((current) => ({ ...current, [key]: value }));
  }

  async function saveReminderSettings() {
    setSavingReminders(true);
    setReminderStatus(null);
    try {
      const saved = await api.updateReminderSettings(reminderSettings);
      setReminderSettings({
        ...defaultReminderSettings,
        ...saved,
        preferredReminderTime: toTimeInput(saved.preferredReminderTime),
        eveningReminderTime: toTimeInput(saved.eveningReminderTime)
      });
      setReminderStatus("Saved");
    } catch {
      setReminderStatus("Could not save");
    } finally {
      setSavingReminders(false);
    }
  }

  if (loading) return <AppShellLoading label="Loading profile..." />;

  return (
    <div className="mx-auto max-w-7xl space-y-6">
        <section className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
          <Card className="overflow-hidden">
            <CardContent className="p-0">
              <div className="grid gap-0 md:grid-cols-[220px_1fr]">
                <div className="flex items-center justify-center bg-[radial-gradient(circle_at_50%_20%,hsl(var(--accent)),hsl(var(--muted))_72%)] p-8">
                  <div className="rounded-full border-2 border-border bg-card p-2 shadow-sm">
                    {user?.avatarUrl ? (
                      <img
                        src={user.avatarUrl}
                        alt={user.name || user.email}
                        referrerPolicy="no-referrer"
                        className="h-28 w-28 rounded-full border-4 border-primary object-cover"
                      />
                    ) : (
                      <div className="flex h-28 w-28 items-center justify-center rounded-full border-4 border-primary bg-accent text-primary">
                        <IconUser className="h-14 w-14" />
                      </div>
                    )}
                  </div>
                </div>
                <div className="flex min-h-[220px] flex-col justify-center gap-5 p-7 md:p-8">
                  <div>
                    <p className="mb-2 font-display text-xs font-bold uppercase tracking-[0.14em] text-primary">Learner Profile</p>
                    <h2 className="font-display text-3xl font-bold leading-tight text-foreground md:text-4xl">{user?.name || "Guest learner"}</h2>
                    <p className="mt-2 text-base font-bold text-muted-foreground">{user?.email || "Sign in to sync your progress"}</p>
                  </div>
                  {!user ? (
                    <Button asChildCompat="a" className="w-fit">
                      <a href="/login"><IconDoorEnter className="h-4 w-4" /> Sign in</a>
                    </Button>
                  ) : null}
                </div>
              </div>
            </CardContent>
          </Card>

          <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
            <Card>
              <CardHeader className="pb-3">
                <CardTitle>Total Words</CardTitle>
                <CardDescription>Vocabulary in your collection</CardDescription>
              </CardHeader>
              <CardContent>
                <p className="font-display text-4xl font-bold text-primary">{summary.words}</p>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-3">
                <CardTitle>Current Streak</CardTitle>
                <CardDescription>Consecutive study days</CardDescription>
              </CardHeader>
              <CardContent>
                <p className="font-display text-4xl font-bold text-primary">{summary.streak}</p>
              </CardContent>
            </Card>
          </div>
        </section>

        <Card>
          <CardHeader>
            <CardTitle>Account</CardTitle>
            <CardDescription>Your sync and login details</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-3 text-sm font-semibold text-muted-foreground md:grid-cols-3">
              <div className="rounded-xl border-2 border-border bg-muted/40 p-4">
                <p className="mb-1 font-display text-xs font-bold uppercase tracking-[0.12em] text-primary">Role</p>
                <p className="text-foreground">{user?.role || "Guest"}</p>
              </div>
              <div className="rounded-xl border-2 border-border bg-muted/40 p-4">
                <p className="mb-1 font-display text-xs font-bold uppercase tracking-[0.12em] text-primary">Created</p>
                <p className="text-foreground">{formatDateTime(user?.createdAt)}</p>
              </div>
              <div className="rounded-xl border-2 border-border bg-muted/40 p-4">
                <p className="mb-1 font-display text-xs font-bold uppercase tracking-[0.12em] text-primary">Last login</p>
                <p className="text-foreground">{formatDateTime(user?.lastLoginAt)}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {user && (
          <Card>
            <CardHeader>
              <CardTitle>Display name</CardTitle>
              <CardDescription>How your name appears in the app.</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={saveProfile} className="flex max-w-xl gap-3">
                <Input value={displayName} onChange={(event) => setDisplayName(event.target.value)} required />
                <Button type="submit">Save</Button>
              </form>
              {profileStatus && <p className="mt-2 text-sm font-semibold text-muted-foreground">{profileStatus}</p>}
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <IconBellRinging className="h-5 w-5 text-primary" />
              Review Reminders
            </CardTitle>
            <CardDescription>Choose when due-card reminders appear.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">
            <div className="grid gap-5 lg:grid-cols-3">
              <div className="flex min-h-[132px] flex-col justify-between rounded-xl border-2 border-border bg-muted/40 p-4">
                <span className="flex items-start gap-3">
                  <input
                    id="daily-reminder-enabled"
                    type="checkbox"
                    className="mt-1 h-5 w-5 accent-primary"
                    checked={reminderSettings.reviewRemindersEnabled}
                    onChange={(event) => updateReminderSetting("reviewRemindersEnabled", event.target.checked)}
                  />
                  <label htmlFor="daily-reminder-enabled">
                    <span className="block font-display text-sm font-bold uppercase tracking-[0.05em]">Daily due</span>
                    <span className="mt-1 block text-sm font-medium text-muted-foreground">Today&apos;s due queue</span>
                  </label>
                </span>
                <Input
                  type="time"
                  value={reminderSettings.preferredReminderTime}
                  onChange={(event) => updateReminderSetting("preferredReminderTime", event.target.value)}
                  disabled={!reminderSettings.reviewRemindersEnabled}
                  aria-label="Daily due reminder time"
                />
              </div>

              <div className="flex min-h-[132px] flex-col justify-between rounded-xl border-2 border-border bg-muted/40 p-4">
                <span className="flex items-start gap-3">
                  <input
                    id="evening-reminder-enabled"
                    type="checkbox"
                    className="mt-1 h-5 w-5 accent-primary"
                    checked={reminderSettings.eveningReminderEnabled}
                    onChange={(event) => updateReminderSetting("eveningReminderEnabled", event.target.checked)}
                  />
                  <label htmlFor="evening-reminder-enabled">
                    <span className="block font-display text-sm font-bold uppercase tracking-[0.05em]">Evening fallback</span>
                    <span className="mt-1 block text-sm font-medium text-muted-foreground">If reviews remain</span>
                  </label>
                </span>
                <Input
                  type="time"
                  value={reminderSettings.eveningReminderTime}
                  onChange={(event) => updateReminderSetting("eveningReminderTime", event.target.value)}
                  disabled={!reminderSettings.eveningReminderEnabled}
                  aria-label="Evening reminder time"
                />
              </div>

              <div className="flex min-h-[132px] flex-col justify-between rounded-xl border-2 border-border bg-muted/40 p-4">
                <span className="flex items-start gap-3">
                  <input
                    id="comeback-reminder-enabled"
                    type="checkbox"
                    className="mt-1 h-5 w-5 accent-primary"
                    checked={reminderSettings.comebackReminderEnabled}
                    onChange={(event) => updateReminderSetting("comebackReminderEnabled", event.target.checked)}
                  />
                  <label htmlFor="comeback-reminder-enabled">
                    <span className="block font-display text-sm font-bold uppercase tracking-[0.05em]">Comeback</span>
                    <span className="mt-1 block text-sm font-medium text-muted-foreground">Every few inactive days</span>
                  </label>
                </span>
                <Input
                  type="number"
                  min={2}
                  max={3}
                  value={reminderSettings.comebackReminderIntervalDays}
                  onChange={(event) => updateReminderSetting("comebackReminderIntervalDays", Number(event.target.value))}
                  disabled={!reminderSettings.comebackReminderEnabled}
                  aria-label="Comeback reminder interval days"
                />
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <Button type="button" onClick={saveReminderSettings} disabled={savingReminders}>
                {savingReminders ? "Saving..." : "Save Reminders"}
              </Button>
              {reminderStatus ? (
                <span className="font-display text-sm font-bold text-muted-foreground">{reminderStatus}</span>
              ) : null}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Background</CardTitle>
            <CardDescription>Choose a landscape for the whole app. Selected images are saved in this browser for faster loading.</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              <button
                type="button"
                onClick={selectDefaultBackground}
                className={cn(
                  "group relative aspect-[4/3] overflow-hidden rounded-xl border-2 bg-card p-5 text-left transition hover:-translate-y-0.5 hover:border-primary hover:shadow-lifted",
                  selectedBackgroundId === null ? "border-primary ring-2 ring-primary/20" : "border-border"
                )}
                aria-pressed={selectedBackgroundId === null}
              >
                <span className="absolute inset-0 bg-[radial-gradient(circle_at_20%_0%,rgba(28,176,246,0.18),transparent_70%),linear-gradient(135deg,hsl(var(--background)),hsl(var(--muted)))]" />
                <span className="relative flex h-full flex-col justify-between">
                  <span className="font-display text-lg font-bold text-foreground">Default</span>
                  <span className="text-sm font-bold text-muted-foreground">Original app background</span>
                </span>
                {selectedBackgroundId === null ? (
                  <span className="absolute right-4 top-4 rounded-full bg-primary p-2 text-primary-foreground">
                    <IconCheck className="h-5 w-5" />
                  </span>
                ) : null}
              </button>

              {backgroundOptions.map((option) => {
                const selected = selectedBackgroundId === option.id;
                const cached = cachedBackgroundIds.has(option.id);
                const saving = savingBackgroundId === option.id;

                return (
                  <button
                    key={option.id}
                    type="button"
                    onClick={() => selectBackground(option)}
                    className={cn(
                      "group relative aspect-[4/3] overflow-hidden rounded-xl border-2 bg-card text-left transition hover:-translate-y-0.5 hover:border-primary hover:shadow-lifted disabled:cursor-wait disabled:opacity-80",
                      selected ? "border-primary ring-2 ring-primary/20" : "border-border"
                    )}
                    aria-label={`Use ${option.name} as app background`}
                    aria-pressed={selected}
                    disabled={saving}
                  >
                    <img
                      src={option.previewUrl}
                      alt=""
                      className="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105"
                      loading="lazy"
                      decoding="async"
                    />
                    <span className="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent" />
                    <span className="relative flex h-full flex-col justify-between p-5 text-white">
                      <span className="font-display text-lg font-bold drop-shadow">{option.name}</span>
                      <span className="inline-flex w-fit items-center gap-1 rounded-full bg-white/90 px-3 py-1.5 text-xs font-bold text-primary">
                        {saving ? (
                          "Saving..."
                        ) : cached ? (
                          <>
                            <IconCheck className="h-3.5 w-3.5" />
                            Saved
                          </>
                        ) : (
                          <>
                            <IconPhoto className="h-3.5 w-3.5" />
                            Local
                          </>
                        )}
                      </span>
                    </span>
                    {selected ? (
                      <span className="absolute right-4 top-4 rounded-full bg-primary p-2 text-primary-foreground">
                        <IconCheck className="h-5 w-5" />
                      </span>
                    ) : null}
                  </button>
                );
              })}
            </div>
          </CardContent>
        </Card>
    </div>
  );
}
