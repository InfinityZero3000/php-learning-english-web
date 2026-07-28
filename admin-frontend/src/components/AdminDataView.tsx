'use client';

export function PageHeading({ eyebrow = 'Learning operations', title, description, actions }: { eyebrow?: string; title: string; description: string; actions?: React.ReactNode }) {
  return <header className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"><div><p className="text-xs font-black uppercase tracking-[.18em] text-[#006590]">{eyebrow}</p><h2 className="mt-2 text-3xl font-black text-[#1b1c1c]">{title}</h2><p className="mt-1 font-medium text-[#3e4850]">{description}</p></div>{actions}</header>;
}

export function StateNotice({ state, message, retry }: { state: 'loading' | 'error' | 'empty'; message?: string; retry?: () => void }) {
  const copy = state === 'loading' ? 'Đang tải dữ liệu…' : state === 'empty' ? (message ?? 'Chưa có dữ liệu trong khoảng thời gian này.') : (message ?? 'Không thể tải dữ liệu.');
  return <div className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-10 text-center"><p className="font-bold text-[#3e4850]">{copy}</p>{state === 'error' && retry && <button onClick={retry} className="mt-4 rounded-xl bg-[#006590] px-5 py-3 font-bold text-white">Thử lại</button>}</div>;
}

export function StatCard({ label, value, accent = '#006590' }: { label: string; value: React.ReactNode; accent?: string }) {
  return <article className="rounded-2xl border-2 border-[#bdc8d2] border-b-4 bg-white p-5"><p className="text-xs font-black uppercase tracking-widest text-[#6e7881]">{label}</p><p className="mt-2 text-3xl font-black tabular-nums" style={{ color: accent }}>{value}</p></article>;
}

export function DataPanel({ title, children, id }: { title: string; children: React.ReactNode; id?: string }) {
  return <section id={id} className="rounded-3xl border-2 border-[#bdc8d2] border-b-4 bg-white p-6"><h3 className="mb-5 text-xl font-black text-[#1b1c1c]">{title}</h3>{children}</section>;
}

export function DateRange({ from, to, onFrom, onTo, onApply }: { from: string; to: string; onFrom: (value: string) => void; onTo: (value: string) => void; onApply: () => void }) {
  return <div className="flex flex-wrap items-end gap-2"><label className="text-xs font-bold text-[#3e4850]">Từ<input type="date" value={from} onChange={(event) => onFrom(event.target.value)} className="mt-1 block rounded-xl border-2 border-[#bdc8d2] bg-white px-3 py-2" /></label><label className="text-xs font-bold text-[#3e4850]">Đến<input type="date" value={to} onChange={(event) => onTo(event.target.value)} className="mt-1 block rounded-xl border-2 border-[#bdc8d2] bg-white px-3 py-2" /></label><button onClick={onApply} className="rounded-xl bg-[#006590] px-4 py-2.5 font-bold text-white">Áp dụng</button></div>;
}
