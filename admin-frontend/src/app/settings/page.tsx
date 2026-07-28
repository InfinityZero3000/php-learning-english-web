'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { DataPanel, PageHeading, StateNotice } from '@/components/AdminDataView';
import { adminPreferences, type AdminPreferences } from '@/lib/api';

export default function SettingsPage() { return <AdminLayout title="Settings"><PageContent /></AdminLayout>; }
function PageContent() {
  const [data, setData] = useState<AdminPreferences>(); const [error, setError] = useState(''); const [saving, setSaving] = useState(false); const [saved, setSaved] = useState(false);
  const load = useCallback(() => { setError(''); return adminPreferences.get().then(setData).catch((e) => setError(e instanceof Error ? e.message : 'Không thể tải preferences.')); }, []);
  useEffect(() => { void Promise.resolve().then(load); }, [load]);
  async function save() { if (!data) return; setSaving(true); setSaved(false); try { setData(await adminPreferences.update(data)); setSaved(true); } catch (e) { setError(e instanceof Error ? e.message : 'Không thể lưu preferences.'); } finally { setSaving(false); } }
  if (error && !data) return <StateNotice state="error" message={error} retry={load} />;
  if (!data) return <StateNotice state="loading" />;
  return <div className="max-w-3xl space-y-6"><PageHeading title="Admin preferences" description="Chỉ tùy chỉnh trải nghiệm tài khoản quản trị; secret và model key không được chỉnh trong trình duyệt." />{error && <p role="alert" className="rounded-xl bg-[#ffdad6] p-4 font-bold text-[#93000a]">{error}</p>}<DataPanel title="Notifications"><Toggle label="Operational alerts" checked={data.notifications.operational} onChange={(checked) => setData({ ...data, notifications: { operational: checked } })} /></DataPanel><DataPanel title="Interface"><Toggle label="Compact sidebar" checked={data.ui.compact_sidebar ?? false} onChange={(checked) => setData({ ...data, ui: { ...data.ui, compact_sidebar: checked } })} /></DataPanel>{saved && <p role="status" className="rounded-xl bg-[#d8f3dc] p-4 font-bold text-[#1b5e20]">Đã lưu preferences.</p>}<button disabled={saving} onClick={() => void save()} className="rounded-xl bg-[#006590] px-6 py-3 font-bold text-white disabled:opacity-50">{saving ? 'Đang lưu…' : 'Lưu thay đổi'}</button></div>;
}
function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) { return <label className="flex cursor-pointer items-center justify-between gap-4 rounded-2xl bg-[#f5f3f3] p-4"><span className="font-bold">{label}</span><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="h-5 w-5 accent-[#006590]" /></label>; }
