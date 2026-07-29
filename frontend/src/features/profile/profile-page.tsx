"use client";

import { IconCheck, IconDoorEnter, IconKey, IconPhoto, IconTrash, IconUser } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Dialog } from "@/components/ui/dialog";
import {
  backgroundOptions,
  cacheBackgroundImage,
  clearSelectedBackground,
  isBackgroundImageCached,
  readSelectedBackground,
  saveSelectedBackground,
  type BackgroundOption
} from "@/lib/background-settings";
import { api, ApiError, profile } from "@/lib/api";
import { cn, formatDateTime } from "@/lib/utils";
import type { AppUser } from "@/types/api";

export function ProfilePage() {
  const router = useRouter();
  const [user, setUser] = useState<AppUser | null>(null);
  const [wordCount, setWordCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [selectedBackgroundId, setSelectedBackgroundId] = useState<string | null>(null);
  const [cachedBackgroundIds, setCachedBackgroundIds] = useState<Set<string>>(new Set());
  const [savingBackgroundId, setSavingBackgroundId] = useState<string | null>(null);
  const [displayName, setDisplayName] = useState("");
  const [profileStatus, setProfileStatus] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [passwordStatus, setPasswordStatus] = useState("");
  const [changingPassword, setChangingPassword] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletePassword, setDeletePassword] = useState("");
  const [deleteStatus, setDeleteStatus] = useState("");
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    Promise.all([
      api.me().catch(() => null),
      api.vocabulary({ perPage: 1 }).catch(() => null),
    ]).then(([me, words]) => {
      setUser(me);
      setDisplayName(me?.name ?? "");
      setWordCount(words?.meta.total ?? 0);
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

  async function changePassword(event: React.FormEvent) {
    event.preventDefault(); setPasswordStatus(""); setChangingPassword(true);
    try { await profile.changePassword(currentPassword, newPassword, passwordConfirmation); setCurrentPassword(""); setNewPassword(""); setPasswordConfirmation(""); setPasswordStatus("Đã đổi mật khẩu."); }
    catch (cause) { setPasswordStatus(cause instanceof ApiError ? Object.values(cause.errors ?? {}).flat()[0] ?? cause.message : "Không thể đổi mật khẩu."); }
    finally { setChangingPassword(false); }
  }

  async function deleteAccount(event: React.FormEvent) {
    event.preventDefault(); setDeleteStatus(""); setDeleting(true);
    try { await profile.destroy(deletePassword); router.replace("/login"); router.refresh(); }
    catch (cause) { setDeleteStatus(cause instanceof ApiError ? Object.values(cause.errors ?? {}).flat()[0] ?? cause.message : "Không thể xóa tài khoản."); setDeleting(false); }
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
                        width={112}
                        height={112}
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

          <div className="grid gap-5">
            <Card>
              <CardHeader className="pb-3">
                <CardTitle>Total Words</CardTitle>
                <CardDescription>Vocabulary in your collection</CardDescription>
              </CardHeader>
              <CardContent>
                <p className="font-display text-4xl font-bold text-primary">{wordCount}</p>
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

        {user && <section className="grid gap-6 xl:grid-cols-2">
          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><IconKey className="h-5 w-5 text-primary" />Bảo mật</CardTitle><CardDescription>Đổi mật khẩu đăng nhập của bạn.</CardDescription></CardHeader>
            <CardContent><form onSubmit={changePassword} className="space-y-4"><label className="block text-sm font-bold">Mật khẩu hiện tại<Input type="password" autoComplete="current-password" value={currentPassword} onChange={(event) => setCurrentPassword(event.target.value)} required /></label><label className="block text-sm font-bold">Mật khẩu mới<Input type="password" autoComplete="new-password" minLength={8} value={newPassword} onChange={(event) => setNewPassword(event.target.value)} required /></label><label className="block text-sm font-bold">Xác nhận mật khẩu mới<Input type="password" autoComplete="new-password" minLength={8} value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} required /></label><Button type="submit" disabled={changingPassword}>{changingPassword ? "Đang lưu..." : "Đổi mật khẩu"}</Button></form>{passwordStatus ? <p aria-live="polite" className="mt-3 text-sm font-semibold text-muted-foreground">{passwordStatus}</p> : null}</CardContent>
          </Card>
          <Card className="border-destructive/30">
            <CardHeader><CardTitle className="flex items-center gap-2 text-destructive"><IconTrash className="h-5 w-5" />Xóa tài khoản</CardTitle><CardDescription>Thao tác này ẩn danh dữ liệu tài khoản và không thể hoàn tác.</CardDescription></CardHeader>
            <CardContent><Button type="button" variant="destructive" onClick={() => setDeleteOpen(true)}>Xóa tài khoản</Button></CardContent>
          </Card>
        </section>}

        <Dialog open={deleteOpen} onClose={() => { if (!deleting) { setDeleteOpen(false); setDeleteStatus(""); } }} title="Xóa tài khoản?" description="Dữ liệu cá nhân của bạn sẽ được ẩn danh và không thể khôi phục." className="max-w-md">
          <form onSubmit={deleteAccount} className="space-y-4"><p className="text-sm text-muted-foreground">Nhập mật khẩu để xác nhận thao tác không thể hoàn tác này.</p><label className="block text-sm font-bold">Mật khẩu<Input autoFocus type="password" autoComplete="current-password" value={deletePassword} onChange={(event) => setDeletePassword(event.target.value)} required disabled={deleting} /></label>{deleteStatus ? <p role="alert" className="text-sm font-semibold text-destructive">{deleteStatus}</p> : null}<div className="flex justify-end gap-3"><Button type="button" variant="outline" onClick={() => setDeleteOpen(false)} disabled={deleting}>Hủy</Button><Button type="submit" variant="destructive" disabled={deleting}>{deleting ? "Đang xóa..." : "Xóa vĩnh viễn"}</Button></div></form>
        </Dialog>

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
                      width={640}
                      height={480}
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
