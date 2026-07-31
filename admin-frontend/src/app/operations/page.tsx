'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { operations, redirectToGoogleStepUp, type AlertRule, type AuditEvent, type ContractStatus, type OperationsOverview, type OperationsUsage, type QuotaPolicy, type ServiceProbe } from '@/lib/api';
import { DataPanel, PageHeading, StatCard } from '@/components/AdminDataView';

const services = ['backend', 'ai', 'trace_cag', 'stt', 'tts'];
type Panels = { overview?: OperationsOverview; usage?: OperationsUsage; contract?: ContractStatus; quota?: QuotaPolicy; rules?: AlertRule[]; audits?: AuditEvent[] };

export default function OperationsPage() { return <AdminLayout title="AI & Operations" requiredRole="super_admin"><PageContent /></AdminLayout>; }
function PageContent() {
  const [panels, setPanels] = useState<Panels>({}); const [failed, setFailed] = useState<string[]>([]); const [loading, setLoading] = useState(true); const [message, setMessage] = useState(''); const [quotaJson, setQuotaJson] = useState('{}'); const [probes, setProbes] = useState<Record<string, ServiceProbe>>({});
  const load = useCallback(async () => {
    setLoading(true); const calls = [operations.overview(), operations.usage(), operations.contracts(), operations.quota(), operations.rules(), operations.audits()] as const;
    const [overview, usage, contract, quota, rules, audits] = await Promise.allSettled(calls); const next: Panels = {}; const errors: string[] = [];
    if (overview.status === 'fulfilled') next.overview = overview.value; else errors.push('overview');
    if (usage.status === 'fulfilled') next.usage = usage.value; else errors.push('usage');
    if (contract.status === 'fulfilled') next.contract = contract.value; else errors.push('contract');
    if (quota.status === 'fulfilled') next.quota = quota.value; else errors.push('quota');
    if (rules.status === 'fulfilled') next.rules = rules.value; else errors.push('rules');
    if (audits.status === 'fulfilled') next.audits = audits.value.data; else errors.push('audits');
    setPanels(next); setFailed(errors); if (next.quota) setQuotaJson(JSON.stringify(next.quota.limits, null, 2)); setLoading(false);
  }, []);
  useEffect(() => { void Promise.resolve().then(load); }, [load]);
  function handleError(error: unknown, fallback: string) { if (redirectToGoogleStepUp(error, '/operations')) return; setMessage(error instanceof Error ? error.message : fallback); }
  async function probe(service: string) { try { const value = await operations.probe(service); setProbes((current) => ({ ...current, [service]: value })); } catch (e) { handleError(e, 'Probe thất bại.'); } }
  async function saveQuota(event: React.FormEvent) { event.preventDefault(); try { const limits = JSON.parse(quotaJson) as Record<string, number>; if (Object.values(limits).some((value) => !Number.isInteger(value) || value < 0)) throw new Error('Quota phải là số nguyên không âm.'); const quota = await operations.updateQuota(limits); setPanels((current) => ({ ...current, quota })); setMessage('Đã kích hoạt quota policy.'); } catch (e) { handleError(e, 'Không thể cập nhật quota.'); } }
  async function toggle(rule: AlertRule) { try { const updated = await operations.updateRule(rule.id, !rule.enabled, rule.parameters); setPanels((current) => ({ ...current, rules: current.rules?.map((item) => item.id === rule.id ? updated : item) })); } catch (e) { handleError(e, 'Không thể cập nhật alert rule.'); } }
  return <div className="space-y-6"><PageHeading eyebrow="Super admin only" title="AI control room" description="Service health, TraceCAG contract, quota, alerts và audit — không hiển thị secret." />{loading && <p className="rounded-xl bg-white p-5 font-bold">Đang tải các panel…</p>}{failed.length > 0 && <p role="status" className="rounded-xl bg-[#ffdf92] p-4 font-bold text-[#594400]">Không tải được: {failed.join(', ')}. Các panel còn lại vẫn hoạt động. <button onClick={() => void load()} className="underline">Thử lại</button></p>}{message && <p role="status" className="rounded-xl bg-[#e8f4ff] p-4 font-bold text-[#004c6e]">{message}</p>}
    <section className="grid gap-4 md:grid-cols-3"><StatCard label="AI requests · 24h" value={panels.usage?.last_24_hours ?? '—'} /><StatCard label="AI requests · 30d" value={panels.usage?.last_30_days ?? '—'} /><StatCard label="Degraded · 30d" value={panels.usage?.degraded_30_days ?? '—'} accent="#ba1a1a" /></section>
    <DataPanel title="Service probes"><div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">{services.map((service) => <article key={service} className="rounded-2xl bg-[#f5f3f3] p-4"><p className="font-black capitalize">{service.replace('_', ' ')}</p><p className="mt-1 text-xs text-[#6e7881]">{probes[service] ? `${probes[service].status} · ${probes[service].latency_ms} ms` : panels.overview?.services[service] ? 'Configured' : 'Unavailable'}</p><button onClick={() => void probe(service)} className="mt-3 rounded-xl bg-[#d1edff] px-3 py-2 text-sm font-bold text-[#004c6e]">Probe</button></article>)}</div></DataPanel>
    <section id="controls" className="grid gap-6 xl:grid-cols-2"><DataPanel title={`Quota policy · v${panels.quota?.version ?? '—'}`}><form onSubmit={saveQuota} className="space-y-3"><textarea aria-label="Quota limits JSON" value={quotaJson} onChange={(event) => setQuotaJson(event.target.value)} rows={7} className="w-full rounded-xl border-2 border-[#bdc8d2] p-3 font-mono text-sm" /><button className="rounded-xl bg-[#006590] px-5 py-3 font-bold text-white">Save quota</button></form></DataPanel><DataPanel title={`Alert rules · ${panels.overview?.open_alerts ?? '—'} open`}>{panels.rules?.length ? panels.rules.map((rule) => <div key={rule.id} className="flex justify-between gap-3 border-b py-3"><span className="font-bold">{rule.rule_key}</span><button onClick={() => void toggle(rule)} className="rounded-full bg-[#e8f4ff] px-3 py-1 text-xs font-bold text-[#006590]">{rule.enabled ? 'Enabled' : 'Disabled'}</button></div>) : <p className="text-sm text-[#6e7881]">Chưa có alert rule.</p>}</DataPanel></section>
    <DataPanel title="TraceCAG contract"><p className="font-bold">Version {panels.contract?.trace_cag.version ?? '—'}</p><p className="break-all font-mono text-xs text-[#6e7881]">{panels.contract?.trace_cag.sha256 ?? 'Không có schema hash.'}</p></DataPanel>
    <DataPanel title="Recent audit">{panels.audits?.length ? panels.audits.slice(0, 20).map((event) => <div key={event.id} className="flex justify-between gap-4 border-b py-3"><span className="font-bold">{event.action}</span><time className="text-xs">{new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(event.occurred_at))}</time></div>) : <p className="text-sm text-[#6e7881]">Chưa có audit event.</p>}</DataPanel>
  </div>;
}
