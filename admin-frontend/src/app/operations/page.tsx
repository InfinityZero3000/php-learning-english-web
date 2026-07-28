'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { operations, type AlertRule, type AuditEvent, type OperationsOverview, type ServiceProbe } from '@/lib/api';

const serviceNames = ['backend', 'ai', 'trace_cag', 'stt', 'tts'];

export default function OperationsPage() {
  const [overview, setOverview] = useState<OperationsOverview>();
  const [rules, setRules] = useState<AlertRule[]>([]);
  const [audits, setAudits] = useState<AuditEvent[]>([]);
  const [probes, setProbes] = useState<Record<string, ServiceProbe>>({});
  const [error, setError] = useState('');
  useEffect(() => {
    Promise.all([operations.overview(), operations.rules(), operations.audits()])
      .then(([status, nextRules, nextAudits]) => { setOverview(status); setRules(nextRules); setAudits(nextAudits); })
      .catch((reason) => setError(reason.message));
  }, []);

  async function probe(service: string) {
    try { const result = await operations.probe(service); setProbes((current) => ({ ...current, [service]: result })); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Probe failed'); }
  }

  return <AdminLayout title="AI & Operations">
    <div className="space-y-8">
      <section className="rounded-3xl p-8" style={{ background: 'linear-gradient(135deg,#00364d,#006590)', color: 'white' }}>
        <p className="text-xs font-black uppercase tracking-[.2em]" style={{ color: '#b9e9ff' }}>Super admin only</p>
        <h2 className="mt-3 text-4xl font-black">AI control room</h2>
        <p className="mt-2 max-w-2xl text-sm" style={{ color: '#d9f3ff' }}>Theo dõi service, TraceCAG, feature flag, quota, alert rules và audit trail mà không hiển thị secret.</p>
      </section>
      {error && <p className="rounded-xl p-4" style={{ backgroundColor: '#ffdad6', color: '#93000a' }}>{error}</p>}
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        {serviceNames.map((service) => {
          const result = probes[service];
          const configured = overview?.services[service];
          return <div key={service} className="rounded-2xl bg-white p-5" style={{ border: '2px solid #bdc8d2' }}>
            <div className="flex items-center justify-between"><span className={`h-3 w-3 rounded-full ${result?.healthy ? 'bg-emerald-500' : configured ? 'bg-amber-500' : 'bg-slate-300'}`} /><span className="text-xs font-bold uppercase">{configured ? 'configured' : 'off'}</span></div>
            <h3 className="mt-4 text-lg font-black">{service.replace('_', ' ')}</h3>
            <p className="mt-1 text-xs" style={{ color: '#5f6b74' }}>{result ? `${result.status} · ${result.latency_ms}ms` : 'Chưa probe'}</p>
            <button onClick={() => probe(service)} className="mt-4 w-full rounded-xl px-3 py-2 text-sm font-bold" style={{ backgroundColor: '#d1edff', color: '#004c6e' }}>Run probe</button>
          </div>;
        })}
      </section>
      <section className="grid gap-6 xl:grid-cols-2">
        <Panel title={`Alert rules · ${overview?.open_alerts ?? 0} open`}>
          {rules.map((rule) => <Row key={rule.id} title={rule.rule_key.replaceAll('_', ' ')} meta={`v${rule.version}`} active={rule.enabled} />)}
        </Panel>
        <Panel title="Feature flags">
          {Object.entries(overview?.features ?? {}).map(([name, enabled]) => <Row key={name} title={name.replaceAll('_', ' ')} meta={enabled ? 'enabled' : 'disabled'} active={enabled} />)}
        </Panel>
      </section>
      <Panel title="Recent operations audit">
        {audits.slice(0, 20).map((event) => <div key={event.id} className="grid grid-cols-[1fr_auto] gap-4 border-b py-3 last:border-0"><div><p className="font-bold">{event.action}</p><p className="text-xs" style={{ color: '#5f6b74' }}>{event.target_type} {event.target_id}</p></div><time className="text-xs">{new Date(event.occurred_at).toLocaleString()}</time></div>)}
        {audits.length === 0 && <p className="py-6 text-center text-sm">No operations yet.</p>}
      </Panel>
    </div>
  </AdminLayout>;
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return <section className="rounded-3xl bg-white p-6" style={{ border: '2px solid #bdc8d2' }}><h3 className="mb-4 text-xl font-black">{title}</h3>{children}</section>;
}
function Row({ title, meta, active }: { title: string; meta: string; active: boolean }) {
  return <div className="flex items-center justify-between border-b py-3 last:border-0"><span className="font-bold capitalize">{title}</span><span className="rounded-full px-3 py-1 text-xs font-bold" style={{ backgroundColor: active ? '#c5f7dc' : '#efeded', color: active ? '#075e36' : '#5f6b74' }}>{meta}</span></div>;
}
