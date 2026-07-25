'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { sets } from '@/lib/api';

interface Deck {
  id: number;
  name: string;
  description?: string;
  wordCount?: number;
  createdAt?: string;
  isPublic?: boolean;
}

function DeckModal({ deck, onClose, onSave }: {
  deck: Partial<Deck> | null;
  onClose: () => void;
  onSave: (data: Partial<Deck>) => Promise<void>;
}) {
  const [form, setForm] = useState<Partial<Deck>>(deck ?? {});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const set = <K extends keyof Deck>(k: K, v: Deck[K]) => setForm(f => ({ ...f, [k]: v }));

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await onSave(form);
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ backgroundColor: 'rgba(0,0,0,0.4)' }}>
      <div className="w-full max-w-md rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '6px solid #bdc8d2' }}>
        <div className="p-6 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
          <h3 className="text-lg font-extrabold" style={{ color: '#1b1c1c' }}>{form.id ? 'Edit Deck' : 'New Deck'}</h3>
          <button onClick={onClose} className="p-2 rounded-xl" onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#e9e8e7')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>
        <form onSubmit={submit} className="p-6 space-y-4">
          {error && <div className="p-3 rounded-xl text-sm font-semibold" style={{ backgroundColor: '#ffdad6', color: '#93000a' }}>{error}</div>}
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Deck Name *</label>
            <input required value={form.name ?? ''} onChange={e => set('name', e.target.value)} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="e.g. Business Vocabulary" />
          </div>
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Description</label>
            <textarea value={form.description ?? ''} onChange={e => set('description', e.target.value)} rows={3} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium resize-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="What does this deck cover?" />
          </div>
          <div className="flex items-center gap-3 p-3.5 rounded-xl" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9' }}>
            <input type="checkbox" id="isPublic" checked={form.isPublic ?? false} onChange={e => set('isPublic', e.target.checked)} className="w-4 h-4 rounded" style={{ accentColor: '#006590' }} />
            <label htmlFor="isPublic" className="text-sm font-bold" style={{ color: '#1b1c1c' }}>Public deck (visible to all users)</label>
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="btn-tactile flex-1 py-3 rounded-xl font-bold text-sm uppercase tracking-wide" style={{ backgroundColor: '#efeded', color: '#1b1c1c', borderBottom: '4px solid #bdc8d2' }}>Cancel</button>
            <button type="submit" disabled={saving} className="btn-tactile flex-1 py-3 rounded-xl font-bold text-sm uppercase tracking-wide" style={{ backgroundColor: saving ? '#bdc8d2' : '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
              {saving ? 'Saving...' : (form.id ? 'Update' : 'Create Deck')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function DecksPage() {
  const [decks, setDecks] = useState<Deck[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [modal, setModal] = useState<{ open: boolean; deck: Partial<Deck> | null }>({ open: false, deck: null });
  const [deleting, setDeleting] = useState<number | null>(null);

  const load = async () => {
    setLoading(true);
    try {
      const res = await sets.list();
      setDecks((Array.isArray(res) ? res : []) as Deck[]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const handleSave = async (data: Partial<Deck>) => {
    if (data.id) {
      // update not in current API, recreate
    } else {
      await sets.create(data);
    }
    load();
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this deck? This cannot be undone.')) return;
    setDeleting(id);
    try { await sets.delete(id); load(); } finally { setDeleting(null); }
  };

  const filtered = decks.filter(d => !search || d.name?.toLowerCase().includes(search.toLowerCase()));

  return (
    <AdminLayout title="Decks">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Vocabulary Decks</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>{decks.length} decks total</p>
          </div>
          <button onClick={() => setModal({ open: true, deck: {} })} className="btn-tactile flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-wide" style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
            <span className="material-symbols-outlined text-base">add</span>
            Create Deck
          </button>
        </div>

        {/* Search */}
        <div className="relative w-72">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg" style={{ color: '#3e4850' }}>search</span>
          <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search decks..." className="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
        </div>

        {/* Grid */}
        {loading ? (
          <div className="py-20 text-center text-sm" style={{ color: '#3e4850' }}>Loading decks...</div>
        ) : filtered.length === 0 ? (
          <div className="py-20 text-center rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2' }}>
            <span className="material-symbols-outlined text-5xl block mb-3" style={{ color: '#bdc8d2' }}>collections_bookmark</span>
            <p className="text-sm font-bold" style={{ color: '#3e4850' }}>No decks yet. Create your first vocabulary deck!</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {filtered.map(deck => (
              <div key={deck.id} className="rounded-2xl p-6 group" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
                <div className="flex justify-between items-start mb-4">
                  <div className="w-12 h-12 rounded-xl flex items-center justify-center" style={{ backgroundColor: '#e8f4ff' }}>
                    <span className="material-symbols-outlined" style={{ color: '#006590', fontSize: '22px' }}>collections_bookmark</span>
                  </div>
                  <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onClick={() => setModal({ open: true, deck })} className="p-1.5 rounded-lg" style={{ color: '#006590' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#e8f4ff')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <span className="material-symbols-outlined text-base">edit</span>
                    </button>
                    <button onClick={() => handleDelete(deck.id)} disabled={deleting === deck.id} className="p-1.5 rounded-lg" style={{ color: '#ba1a1a' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#ffdad6')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <span className="material-symbols-outlined text-base">{deleting === deck.id ? 'hourglass_empty' : 'delete'}</span>
                    </button>
                  </div>
                </div>
                <h3 className="font-extrabold text-base mb-1.5" style={{ color: '#1b1c1c' }}>{deck.name}</h3>
                {deck.description && <p className="text-sm line-clamp-2 mb-4" style={{ color: '#3e4850' }}>{deck.description}</p>}
                <div className="flex items-center gap-3 mt-auto pt-3" style={{ borderTop: '1px solid #efeded' }}>
                  <span className="flex items-center gap-1.5 text-xs font-bold" style={{ color: '#006590' }}>
                    <span className="material-symbols-outlined" style={{ fontSize: '16px' }}>style</span>
                    {deck.wordCount ?? 0} cards
                  </span>
                  {deck.isPublic !== undefined && (
                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold ml-auto" style={{ backgroundColor: deck.isPublic ? '#c8e6ff' : '#efeded', color: deck.isPublic ? '#004c6e' : '#3e4850' }}>
                      {deck.isPublic ? 'Public' : 'Private'}
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {modal.open && <DeckModal deck={modal.deck} onClose={() => setModal({ open: false, deck: null })} onSave={handleSave} />}
    </AdminLayout>
  );
}
