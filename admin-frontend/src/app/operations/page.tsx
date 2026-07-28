'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import {
  operations,
  type AlertRule,
  type AuditEvent,
  type ContractStatus,
  type OperationsOverview,
  type OperationsUsage,
  type QuotaPolicy,
  type ServiceProbe,
} from '@/lib/api';

const serviceNames = ['backend', 'ai', 'trace_cag', 'stt', 'tts'];

export default function OperationsPage() {
  const [overview, setOverview] = useState<OperationsOverview>();
  const [usage, setUsage] = useState<OperationsUsage>();
  const [contract, setContract] = useState<ContractStatus>();
  const [quota, setQuota] = useState<QuotaPolicy>();
  const [rules, setRules] = useState<AlertRule[]>([]);
  const [audits, setAudits] = useState<AuditEvent[]>([]);
  const [probes, setProbes] = useState<Record<string, ServiceProbe>>({});
  const [password, setPassword] = useState('');
  const [quotaJson, setQuotaJson] = useState('{"trace_cag_daily":100,"speech_daily":50}');
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [message, setMessage] = useState('');

  async function load() {
    try {
      const [status, nextUsage, nextContract, nextQuota, nextRules, nextAudits] = await Promise.all([
        operations.overview(), operations.usage(), operations.contracts(),
        operations.quota(), operations.rules(), operations.audits(),
      ]);
      setOverview(status);
      setUsage(nextUsage);
      setContract(nextContract);
      setQuota(nextQuota);
      setRules(nextRules);
      setAudits(nextAudits);
      if (nextQuota?.is_active) setQuotaJson(JSON.stringify(nextQuota.limits, null, 2));
      setState('ready');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Không thể tải dữ liệu vận hành.');
      setState('error');
    }
  }

  useEffect(() => { void Promise.resolve().then(load); }, []);

  async function probe(service: string) {
    try {
      const result = await operations.probe(service);
      setProbes((current) => ({ ...current, [service]: result }));
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Probe thất bại.');
    }
  }

  async function saveQuota(event: React.FormEvent) {
    event.preventDefault();
    try {
      const limits = JSON.parse(quotaJson) as Record<string, number>;
      if (Object.values(limits).some((value) => !Number.isInteger(value) || value < 0)) throw new Error('Mỗi quota phải là số nguyên không âm.');
      setQuota(await operations.updateQuota(limits, password));
      setPassword('');
      setMessage('Đã kích hoạt quota policy mới.');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Không thể cập nhật quota.');
    }
  }

  async function toggleRule(rule: AlertRule) {
    if (!password) {
      setMessage('Nhập mật khẩu hiện tại trước khi thay đổi alert rule.');
      return;
    }
    try {
      const updated = await operations.updateRule(rule.id, !rule.enabled, rule.parameters ?? {}, password);
      setRules((current) => [updated, ...current.filter((item) => item.rule_key !== updated.rule_key)]);
      setPassword('');
      setMessage(`Đã tạo phiên bản ${updated.version} cho ${updated.rule_key}.`);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Không thể cập nhật alert rule.');
    }
  }

  return (
    <AdminLayout title="AI & Operations" requiredRole="super_admin">
      <div className="space-y-8">
        <section className="rounded-3xl bg-gradient-to-br from-[#00364d] to-[#006590] p-8 text-white">
          <p className="text-xs font-black uppercase tracking-[.2em] text-[#b9e9ff]">Super admin only</p>
          <h2 className="mt-3 text-4xl font-black text-balance">AI control room</h2>
          <p className="mt-2 max-w-2xl text-sm text-[#d9f3ff]">Service health, TraceCAG contract, quota, deterministic alert rules và audit trail — không hiển thị secret.</p>
        </section>

        {message && <p aria-live="polite" className="rounded-xl bg-[#ffdad6] p-4 font-semibold text-[#93000a]">{message}</p>}
        {state === 'loading' && <p aria-live="polite" className="rounded-2xl bg-white p-8 text-center font-bold">Đang tải dữ liệu vận hành…</p>}
        {state === 'error' && <button onClick={() => { setState('loading'); setMessage(''); void load(); }} className="rounded-xl bg-[#006590] px-5 py-3 font-bold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">Thử lại</button>}

        {state === 'ready' && <>
          <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            {serviceNames.map((service) => {
              const result = probes[service];
              const configured = overview?.services[service];
              return <article key={service} className="rounded-2xl border-2 border-[#bdc8d2] bg-white p-5">
                <div className="flex items-center justify-between"><span aria-hidden="true" className={`h-3 w-3 rounded-full ${result?.healthy ? 'bg-emerald-500' : configured ? 'bg-amber-500' : 'bg-slate-300'}`} /><span className="text-xs font-bold uppercase">{configured ? 'configured' : 'off'}</span></div>
                <h3 className="mt-4 text-lg font-black">{service.replace('_', ' ')}</h3>
                <p className="mt-1 text-xs text-[#5f6b74]">{result ? `${result.status} · ${result.latency_ms} ms` : 'Chưa probe'}</p>
                <button onClick={() => probe(service)} className="mt-4 w-full rounded-xl bg-[#d1edff] px-3 py-2 text-sm font-bold text-[#004c6e] hover:bg-[#b9e9ff] focus-visible:outline focus-visible:outline-2">Chạy probe</button>
              </article>;
            })}
          </section>

          <section className="grid gap-4 md:grid-cols-3">
            <Metric label="AI requests · 24h" value={usage?.last_24_hours ?? 0} />
            <Metric label="AI requests · 30d" value={usage?.last_30_days ?? 0} />
            <Metric label="Degraded · 30d" value={usage?.degraded_30_days ?? 0} />
          </section>

          <section className="grid gap-6 xl:grid-cols-2">
            <Panel title={`Quota policy · v${quota?.version ?? 0}`}>
              <form onSubmit={saveQuota} className="space-y-4">
                <label className="block font-bold" htmlFor="quota-json">Limits JSON</label>
                <textarea id="quota-json" name="limits" value={quotaJson} onChange={(event) => setQuotaJson(event.target.value)} rows={7} spellCheck={false} className="w-full rounded-xl border-2 border-[#bdc8d2] p-3 font-mono text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#006590]" />
                <label className="block font-bold" htmlFor="operations-password">Mật khẩu hiện tại</label>
                <input id="operations-password" name="password" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required className="w-full rounded-xl border-2 border-[#bdc8d2] p-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#006590]" />
                <button className="rounded-xl bg-[#006590] px-5 py-3 font-bold text-white hover:bg-[#004c6e] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">Kích hoạt policy mới</button>
              </form>
            </Panel>
            <Panel title={`Alert rules · ${overview?.open_alerts ?? 0} open`}>
              {rules.length === 0 && <p className="py-6 text-center text-sm">Chưa có alert rule.</p>}
              {rules.map((rule) => <div key={rule.id} className="flex items-center justify-between gap-4 border-b py-3 last:border-0">
                <div className="min-w-0"><p className="truncate font-bold capitalize">{rule.rule_key.replaceAll('_', ' ')}</p><p className="text-xs text-[#5f6b74]">Version {rule.version}</p></div>
                <button onClick={() => toggleRule(rule)} className={`rounded-full px-3 py-2 text-xs font-bold ${rule.enabled ? 'bg-[#c5f7dc] text-[#075e36]' : 'bg-[#efeded] text-[#5f6b74]'}`}>{rule.enabled ? 'Enabled' : 'Disabled'}</button>
              </div>)}
            </Panel>
          </section>

          <Panel title="Contract đang triển khai">
            <p className="font-bold">TraceCAG {contract?.trace_cag.version ?? '—'}</p>
            <p className="mt-1 break-all font-mono text-xs text-[#5f6b74]">{contract?.trace_cag.sha256 ?? 'Không tìm thấy schema hash'}</p>
          </Panel>

          <Panel title="Recent operations audit">
            {audits.slice(0, 20).map((event) => <div key={event.id} className="grid grid-cols-[1fr_auto] gap-4 border-b py-3 last:border-0"><div className="min-w-0"><p className="truncate font-bold">{event.action}</p><p className="text-xs text-[#5f6b74]">{event.target_type} {event.target_id}</p></div><time className="text-xs" dateTime={event.occurred_at}>{new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(event.occurred_at))}</time></div>)}
            {audits.length === 0 && <p className="py-6 text-center text-sm">Chưa có thao tác vận hành.</p>}
          </Panel>
        </>}
      </div>
    </AdminLayout>
  );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return <section className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-6"><h3 className="mb-4 text-xl font-black">{title}</h3>{children}</section>;
}

function Metric({ label, value }: { label: string; value: number }) {
  return <article className="rounded-2xl border-2 border-[#bdc8d2] bg-white p-5"><p className="text-sm font-bold text-[#5f6b74]">{label}</p><p className="mt-2 text-3xl font-black tabular-nums">{new Intl.NumberFormat('vi-VN').format(value)}</p></article>;
}
