'use client';

import { useEffect, useState, useCallback } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { words } from '@/lib/api';

interface Word {
  id: number;
  word: string;
  meaning?: string;
  definition?: string;
  translation?: string;
  category?: string;
  partOfSpeech?: string;
  difficulty?: string;
  example?: string;
  pronunciation?: string;
}

interface WordsPage {
  content?: Word[];
  totalElements?: number;
  totalPages?: number;
  number?: number;
}

const DIFFICULTIES = ['', 'BEGINNER', 'INTERMEDIATE', 'ADVANCED'];

const difficultyStyle: Record<string, { bg: string; color: string }> = {
  BEGINNER: { bg: '#c8e6ff', color: '#004c6e' },
  INTERMEDIATE: { bg: '#ffdf92', color: '#594400' },
  ADVANCED: { bg: '#ffdad6', color: '#93000a' },
};

function WordModal({ word, onClose, onSave }: {
  word: Partial<Word> | null;
  onClose: () => void;
  onSave: (data: Partial<Word>) => Promise<void>;
}) {
  const [form, setForm] = useState<Partial<Word>>(word ?? {});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const set = (k: keyof Word, v: string) => setForm(f => ({ ...f, [k]: v }));

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
      <div className="w-full max-w-lg rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '6px solid #bdc8d2' }}>
        <div className="p-6 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
          <h3 className="text-lg font-extrabold" style={{ color: '#1b1c1c' }}>
            {form.id ? 'Edit Flashcard' : 'New Flashcard'}
          </h3>
          <button onClick={onClose} className="p-2 rounded-xl" onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#e9e8e7')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>
        <form onSubmit={submit} className="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
          {error && <div className="p-3 rounded-xl text-sm font-semibold" style={{ backgroundColor: '#ffdad6', color: '#93000a' }}>{error}</div>}
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Word / Phrase *</label>
            <input required value={form.word ?? ''} onChange={e => set('word', e.target.value)} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="e.g. Ephemeral" />
          </div>
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Translation / Meaning</label>
            <textarea value={form.translation ?? ''} onChange={e => set('translation', e.target.value)} rows={2} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium resize-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="Enter meaning..." />
          </div>
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Definition</label>
            <textarea value={form.definition ?? ''} onChange={e => set('definition', e.target.value)} rows={2} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium resize-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="Full definition..." />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Difficulty</label>
              <select value={form.difficulty ?? ''} onChange={e => set('difficulty', e.target.value)} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }}>
                {DIFFICULTIES.map(d => <option key={d} value={d}>{d || 'Select...'}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Category</label>
              <input value={form.category ?? ''} onChange={e => set('category', e.target.value)} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="e.g. Travel" />
            </div>
          </div>
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Pronunciation</label>
            <input value={form.pronunciation ?? ''} onChange={e => set('pronunciation', e.target.value)} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="/ɛˈfɛm.ər.əl/" />
          </div>
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider mb-1.5" style={{ color: '#3e4850' }}>Example Sentence</label>
            <input value={form.example ?? ''} onChange={e => set('example', e.target.value)} className="w-full p-3.5 rounded-xl outline-none text-sm font-medium" style={{ border: '2px solid #bdc8d2', backgroundColor: '#fbf9f9', color: '#1b1c1c' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} placeholder="e.g. The ephemeral beauty of cherry blossoms..." />
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="btn-tactile flex-1 py-3 rounded-xl font-bold text-sm uppercase tracking-wide" style={{ backgroundColor: '#efeded', color: '#1b1c1c', borderBottom: '4px solid #bdc8d2' }}>Cancel</button>
            <button type="submit" disabled={saving} className="btn-tactile flex-1 py-3 rounded-xl font-bold text-sm uppercase tracking-wide" style={{ backgroundColor: saving ? '#bdc8d2' : '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
              {saving ? 'Saving...' : (form.id ? 'Update' : 'Add Flashcard')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function FlashcardsPage() {
  const [data, setData] = useState<WordsPage | null>(null);
  const [page, setPage] = useState(0);
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [difficultyFilter, setDifficultyFilter] = useState('');
  const [categories, setCategories] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [modal, setModal] = useState<{ open: boolean; word: Partial<Word> | null }>({ open: false, word: null });
  const [deleting, setDeleting] = useState<number | null>(null);
  const [totalElements, setTotalElements] = useState(0);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [res, cats] = await Promise.allSettled([
        words.list({ page, size: 15, search: search || undefined, category: categoryFilter || undefined, difficulty: difficultyFilter || undefined }),
        words.categories(),
      ]);
      if (res.status === 'fulfilled') {
        const d = res.value as WordsPage;
        setData(d);
        setTotalElements(d.totalElements ?? (Array.isArray(d) ? (d as unknown as Word[]).length : 0));
      }
      if (cats.status === 'fulfilled') setCategories(cats.value as string[]);
    } finally {
      setLoading(false);
    }
  }, [page, search, categoryFilter, difficultyFilter]);

  useEffect(() => { load(); }, [load]);

  const handleSave = async (formData: Partial<Word>) => {
    if (formData.id) await words.update(formData.id, formData);
    else await words.create(formData);
    load();
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this flashcard?')) return;
    setDeleting(id);
    try { await words.delete(id); load(); } finally { setDeleting(null); }
  };

  const wordList: Word[] = Array.isArray(data) ? (data as unknown as Word[]) : (data?.content ?? []);
  const totalPages = data?.totalPages ?? 1;

  return (
    <AdminLayout title="Flashcards">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Flashcards</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
              {loading ? '...' : `${totalElements.toLocaleString()} cards total`}
            </p>
          </div>
          <button onClick={() => setModal({ open: true, word: {} })} className="btn-tactile flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-wide" style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
            <span className="material-symbols-outlined text-base">add</span>
            New Flashcard
          </button>
        </div>

        {/* Filters */}
        <div className="flex flex-wrap gap-3">
          <div className="relative">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg" style={{ color: '#3e4850' }}>search</span>
            <input value={searchInput} onChange={e => setSearchInput(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { setSearch(searchInput); setPage(0); } }} placeholder="Search flashcards..." className="pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c', width: '240px' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
          </div>
          <select value={categoryFilter} onChange={e => { setCategoryFilter(e.target.value); setPage(0); }} className="py-2.5 px-4 rounded-xl text-sm font-bold outline-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c' }}>
            <option value="">All Categories</option>
            {categories.map(c => <option key={c} value={c}>{c}</option>)}
          </select>
          <select value={difficultyFilter} onChange={e => { setDifficultyFilter(e.target.value); setPage(0); }} className="py-2.5 px-4 rounded-xl text-sm font-bold outline-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c' }}>
            <option value="">All Levels</option>
            {DIFFICULTIES.filter(Boolean).map(d => <option key={d} value={d}>{d}</option>)}
          </select>
          <button onClick={() => { setSearch(searchInput); setPage(0); }} className="btn-tactile px-4 py-2.5 rounded-xl font-bold text-sm" style={{ backgroundColor: '#efeded', color: '#1b1c1c', borderBottom: '3px solid #bdc8d2' }}>
            Search
          </button>
        </div>

        {/* Table */}
        <div className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="overflow-x-auto" style={{ borderRadius: '22px 22px 0 0', overflowY: 'hidden' }}>
            <table className="w-full text-left">
              <thead>
                <tr style={{ backgroundColor: '#f5f3f3', borderBottom: '2px solid #bdc8d2' }}>
                  {['Word', 'Meaning', 'Category', 'Difficulty', 'Part of Speech', 'Actions'].map(h => (
                    <th key={h} className="px-5 py-4 text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={6} className="p-10 text-center text-sm" style={{ color: '#3e4850' }}>Loading flashcards...</td></tr>
                ) : wordList.length === 0 ? (
                  <tr><td colSpan={6} className="p-10 text-center" style={{ color: '#3e4850' }}>
                    <span className="material-symbols-outlined text-4xl block mb-2" style={{ color: '#bdc8d2' }}>style</span>
                    <p className="text-sm font-medium">No flashcards found. Add your first one!</p>
                  </td></tr>
                ) : wordList.map(w => {
                  const ds = difficultyStyle[w.difficulty ?? ''] ?? { bg: '#efeded', color: '#3e4850' };
                  return (
                    <tr key={w.id} style={{ borderTop: '1px solid #efeded' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <td className="px-5 py-4">
                        <p className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{w.word}</p>
                        {w.pronunciation && <p className="text-xs font-mono mt-0.5" style={{ color: '#006590' }}>{w.pronunciation}</p>}
                      </td>
                      <td className="px-5 py-4 text-sm max-w-xs" style={{ color: '#3e4850' }}>
                        <span className="line-clamp-2">{w.meaning ?? w.translation ?? w.definition ?? '—'}</span>
                      </td>
                      <td className="px-5 py-4 text-sm" style={{ color: '#3e4850' }}>{w.category ?? '—'}</td>
                      <td className="px-5 py-4">
                        {w.difficulty ? (
                          <span className="px-3 py-1 rounded-full text-xs font-bold" style={{ backgroundColor: ds.bg, color: ds.color }}>{w.difficulty}</span>
                        ) : <span style={{ color: '#bdc8d2' }}>—</span>}
                      </td>
                      <td className="px-5 py-4 text-sm" style={{ color: '#3e4850' }}>{w.partOfSpeech ?? '—'}</td>
                      <td className="px-5 py-4">
                        <div className="flex gap-2">
                          <button onClick={() => setModal({ open: true, word: w })} className="p-1.5 rounded-lg" style={{ color: '#006590' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#e8f4ff')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                            <span className="material-symbols-outlined text-base">edit</span>
                          </button>
                          <button onClick={() => handleDelete(w.id)} disabled={deleting === w.id} className="p-1.5 rounded-lg" style={{ color: '#ba1a1a' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#ffdad6')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                            <span className="material-symbols-outlined text-base">{deleting === w.id ? 'hourglass_empty' : 'delete'}</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          {totalPages > 1 && (
            <div className="flex justify-between items-center px-5 py-4" style={{ borderTop: '2px solid #bdc8d2' }}>
              <span className="text-sm font-medium" style={{ color: '#3e4850' }}>Page {page + 1} of {totalPages}</span>
              <div className="flex gap-2">
                <button disabled={page === 0} onClick={() => setPage(p => p - 1)} className="btn-tactile px-4 py-2 rounded-xl text-sm font-bold" style={{ backgroundColor: page === 0 ? '#efeded' : '#006590', color: page === 0 ? '#bdc8d2' : '#ffffff', borderBottom: '3px solid #004c6e' }}>← Prev</button>
                <button disabled={page >= totalPages - 1} onClick={() => setPage(p => p + 1)} className="btn-tactile px-4 py-2 rounded-xl text-sm font-bold" style={{ backgroundColor: page >= totalPages - 1 ? '#efeded' : '#006590', color: page >= totalPages - 1 ? '#bdc8d2' : '#ffffff', borderBottom: '3px solid #004c6e' }}>Next →</button>
              </div>
            </div>
          )}
        </div>
      </div>

      {modal.open && <WordModal word={modal.word} onClose={() => setModal({ open: false, word: null })} onSave={handleSave} />}
    </AdminLayout>
  );
}
