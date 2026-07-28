'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { adminCatalog, type AdminLevel } from '@/lib/api';

const LEVELS = [
  { icon: 'child_care', bg: '#c8e6ff', iconColor: '#004c6e', badgeBg: '#c8e6ff', badgeColor: '#004c6e', borderColor: '#88ceff' },
  { icon: 'school', bg: '#ffdf92', iconColor: '#594400', badgeBg: '#ffdf92', badgeColor: '#594400', borderColor: '#f4bf00' },
  { icon: 'psychology', bg: '#ffdad6', iconColor: '#93000a', badgeBg: '#ffdad6', badgeColor: '#93000a', borderColor: '#ff8a80' },
  { icon: 'workspace_premium', bg: '#f4d9ff', iconColor: '#5d068e', badgeBg: '#f4d9ff', badgeColor: '#5d068e', borderColor: '#d087ff' },
];

export default function LevelsPage() {
  return <AdminLayout title="Levels"><PageContent /></AdminLayout>;
}

function PageContent() {
  const [levels, setLevels] = useState<AdminLevel[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState<AdminLevel>();
  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [saving, setSaving] = useState(false);

  const load = () => {
    let active = true;
    adminCatalog.levels({ perPage: 100 }).then(response => { if (active) setLevels(response.data); })
      .catch(reason => { if (active) setError(reason instanceof Error ? reason.message : 'Could not load levels.'); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  };

  useEffect(load, []);

  async function save(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true); setError('');
    try {
      if (editing) await adminCatalog.updateLevel(editing.id, { name, slug, sort_order: editing.sort_order });
      else await adminCatalog.createLevel({ name, slug, sort_order: levels.length });
      setEditing(undefined); setName(''); setSlug(''); setLoading(true); load();
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save level.'); }
    finally { setSaving(false); }
  }

  async function remove(level: AdminLevel) {
    if (!confirm(`Delete level “${level.name}”?`)) return;
    try { await adminCatalog.deleteLevel(level.id); setLoading(true); load(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not delete level.'); }
  }

  return (
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Difficulty Levels</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
              Levels from the active learning catalog.
            </p>
          </div>
        </div>

        {error && <div role="alert" className="rounded-2xl p-5 font-bold" style={{ backgroundColor: '#ffdad6', color: '#93000a' }}>{error}</div>}
        <form onSubmit={save} className="grid gap-3 rounded-2xl border-2 border-[#bdc8d2] bg-white p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
          <label className="font-bold">Name<input required value={name} onChange={event => { setName(event.target.value); if (!editing) setSlug(event.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')); }} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3" /></label>
          <label className="font-bold">Slug<input required value={slug} onChange={event => setSlug(event.target.value)} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3" /></label>
          <div className="flex gap-2"><button disabled={saving} className="rounded-xl bg-[#006590] px-5 py-3 font-black text-white disabled:opacity-50">{editing ? 'Update' : 'Add level'}</button>{editing && <button type="button" onClick={() => { setEditing(undefined); setName(''); setSlug(''); }} className="rounded-xl px-4 py-3 font-bold">Cancel</button>}</div>
        </form>

        {/* Level cards */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {loading ? <p className="py-16 text-center font-bold">Loading levels…</p> : levels.map((item, index) => {
            const level = LEVELS[index % LEVELS.length];
            return (
            <div key={item.id} className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: `2px solid ${level.borderColor}`, borderBottom: `5px solid ${level.borderColor}` }}>
              <div className="p-6">
                <div className="flex items-center gap-4 mb-5">
                  <div className="w-14 h-14 rounded-2xl flex items-center justify-center" style={{ backgroundColor: level.bg }}>
                    <span className="material-symbols-outlined" style={{ color: level.iconColor, fontSize: '26px', fontVariationSettings: "'FILL' 1" }}>{level.icon}</span>
                  </div>
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <h3 className="text-xl font-extrabold" style={{ color: '#1b1c1c' }}>{item.name}</h3>
                      <span className="px-2.5 py-0.5 rounded-full text-xs font-bold" style={{ backgroundColor: level.badgeBg, color: level.badgeColor }}>/{item.slug}</span>
                    </div>
                    <p className="text-sm font-medium" style={{ color: '#3e4850' }}>Catalog level with {item.courses_count ?? 0} linked courses.</p>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3 mb-5">
                  <div className="p-3.5 rounded-xl" style={{ backgroundColor: '#f5f3f3' }}>
                    <p className="text-[10px] font-black uppercase tracking-widest mb-1" style={{ color: '#6e7881' }}>FSRS Interval Range</p>
                    <p className="text-sm font-extrabold" style={{ color: '#006590' }}>{item.courses_count ?? 0} courses</p>
                  </div>
                  <div className="p-3.5 rounded-xl" style={{ backgroundColor: '#f5f3f3' }}>
                    <p className="text-[10px] font-black uppercase tracking-widest mb-1" style={{ color: '#6e7881' }}>Difficulty Key</p>
                    <p className="text-sm font-mono font-extrabold" style={{ color: '#1b1c1c' }}>{item.sort_order ?? index}</p>
                  </div>
                </div>
                <div className="flex justify-end gap-2"><button onClick={() => { setEditing(item); setName(item.name); setSlug(item.slug); }} className="rounded-xl border-2 border-[#88ceff] px-4 py-2 font-bold text-[#006590]">Edit</button><button onClick={() => void remove(item)} className="rounded-xl border-2 border-[#ffb4ab] px-4 py-2 font-bold text-[#93000a]">Delete</button></div>

              </div>
            </div>
          );})}
        </div>

        {levels.length === 0 && !loading && !error && <div className="rounded-3xl p-12 text-center" style={{ border: '2px solid #bdc8d2' }}>No levels configured.</div>}
      </div>
  );
}
