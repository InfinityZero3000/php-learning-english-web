'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { topics } from '@/lib/api';

interface Topic {
  id: number;
  name: string;
  description?: string;
  slug?: string;
  iconEmoji?: string;
  colorHex?: string;
  wordCount?: number;
}

interface TopicForm {
  name: string;
  slug: string;
  description: string;
  iconEmoji: string;
  colorHex: string;
}

const EMPTY_FORM: TopicForm = { name: '', slug: '', description: '', iconEmoji: 'topic', colorHex: '#006590' };

const PRESET_COLORS = [
  '#006590', '#843ab4', '#755b00', '#ba1a1a', '#1b5e20', '#004c6e',
  '#0d6efd', '#e91e63', '#ff5722', '#009688', '#607d8b', '#795548',
];

const PRESET_ICONS = [
  'topic', 'pets', 'science', 'language', 'school', 'psychology',
  'business_center', 'directions_car', 'restaurant', 'sports_soccer',
  'music_note', 'flight', 'local_hospital', 'code', 'menu_book',
  'nature', 'sports', 'celebration', 'favorite', 'lightbulb',
  'emoji_events', 'travel_explore', 'family_restroom', 'fitness_center',
];

function slugify(name: string) {
  return name.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function TopicModal({
  initial,
  onClose,
  onSave,
}: {
  initial?: Topic | null;
  onClose: () => void;
  onSave: (form: TopicForm) => Promise<void>;
}) {
  const [form, setForm] = useState<TopicForm>(
    initial
      ? { name: initial.name, slug: initial.slug ?? '', description: initial.description ?? '', iconEmoji: initial.iconEmoji ?? 'topic', colorHex: initial.colorHex ?? '#006590' }
      : EMPTY_FORM
  );
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [slugEdited, setSlugEdited] = useState(!!initial);

  const set = (k: keyof TopicForm, v: string) => {
    setForm(f => {
      const next = { ...f, [k]: v };
      if (k === 'name' && !slugEdited) next.slug = slugify(v);
      return next;
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) { setError('Name is required'); return; }
    setSaving(true);
    setError('');
    try {
      await onSave(form);
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
      style={{ backgroundColor: 'rgba(0,0,0,0.4)' }}
      onClick={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-lg rounded-3xl"
        style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '6px solid #bdc8d2', boxShadow: '0 24px 64px rgba(0,0,0,0.18)' }}>
        {/* Header */}
        <div className="px-8 py-6 flex items-center justify-between rounded-t-[22px]"
          style={{ borderBottom: '2px solid #efeded', backgroundColor: '#f5f3f3' }}>
          <div>
            <h3 className="text-xl font-extrabold" style={{ color: '#1b1c1c' }}>
              {initial ? 'Edit Topic' : 'New Topic'}
            </h3>
            <p className="text-xs font-medium mt-0.5" style={{ color: '#6e7881' }}>
              {initial ? 'Update topic configuration' : 'Configure all topic settings'}
            </p>
          </div>
          <button onClick={onClose} className="w-9 h-9 flex items-center justify-center rounded-xl transition-colors"
            style={{ color: '#6e7881' }}
            onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#efeded')}
            onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
            <span className="material-symbols-outlined text-xl">close</span>
          </button>
        </div>

        {/* Preview banner */}
        <div className="mx-8 mt-6 p-4 rounded-2xl flex items-center gap-4"
          style={{ backgroundColor: form.colorHex + '18', border: `2px solid ${form.colorHex}40` }}>
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0"
            style={{ backgroundColor: form.colorHex + '28', border: `2px solid ${form.colorHex}40` }}>
            <span className="material-symbols-outlined"
              style={{ color: form.colorHex, fontSize: '28px', fontVariationSettings: "'FILL' 1" }}>
              {form.iconEmoji || 'topic'}
            </span>
          </div>
          <div>
            <p className="font-extrabold text-base" style={{ color: '#1b1c1c' }}>{form.name || 'Topic Name'}</p>
            {form.slug && <p className="text-xs font-mono mt-0.5" style={{ color: '#6e7881' }}>/{form.slug}</p>}
          </div>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="px-8 py-6 space-y-5">
          {/* Name */}
          <div>
            <label className="text-xs font-bold uppercase tracking-widest block mb-1.5" style={{ color: '#3e4850' }}>
              Topic Name <span style={{ color: '#ba1a1a' }}>*</span>
            </label>
            <input value={form.name} onChange={e => set('name', e.target.value)}
              placeholder="e.g. Business English"
              className="w-full px-4 py-2.5 rounded-xl text-sm font-medium outline-none"
              style={{ border: '2px solid #bdc8d2', backgroundColor: '#f5f3f3', color: '#1b1c1c' }}
              onFocus={e => (e.target.style.borderColor = '#006590')}
              onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
          </div>

          {/* Slug */}
          <div>
            <label className="text-xs font-bold uppercase tracking-widest block mb-1.5" style={{ color: '#3e4850' }}>URL Slug</label>
            <div className="relative">
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold" style={{ color: '#6e7881' }}>/</span>
              <input value={form.slug} onChange={e => { setSlugEdited(true); set('slug', e.target.value); }}
                placeholder="business-english"
                className="w-full pl-7 pr-4 py-2.5 rounded-xl text-sm font-mono outline-none"
                style={{ border: '2px solid #bdc8d2', backgroundColor: '#f5f3f3', color: '#1b1c1c' }}
                onFocus={e => (e.target.style.borderColor = '#006590')}
                onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
            </div>
          </div>

          {/* Description */}
          <div>
            <label className="text-xs font-bold uppercase tracking-widest block mb-1.5" style={{ color: '#3e4850' }}>Description</label>
            <textarea value={form.description} onChange={e => set('description', e.target.value)}
              placeholder="Brief description of this topic..."
              rows={3}
              className="w-full px-4 py-2.5 rounded-xl text-sm font-medium outline-none resize-none"
              style={{ border: '2px solid #bdc8d2', backgroundColor: '#f5f3f3', color: '#1b1c1c' }}
              onFocus={e => (e.target.style.borderColor = '#006590')}
              onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
          </div>

          {/* Icon */}
          <div>
            <label className="text-xs font-bold uppercase tracking-widest block mb-2" style={{ color: '#3e4850' }}>Icon</label>
            <div className="grid grid-cols-8 gap-2">
              {PRESET_ICONS.map(icon => (
                <button key={icon} type="button" onClick={() => set('iconEmoji', icon)}
                  className="w-10 h-10 rounded-xl flex items-center justify-center transition-all"
                  style={form.iconEmoji === icon
                    ? { border: `2px solid ${form.colorHex}`, backgroundColor: form.colorHex + '18' }
                    : { border: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}
                  title={icon}>
                  <span className="material-symbols-outlined"
                    style={{ color: form.iconEmoji === icon ? form.colorHex : '#3e4850', fontSize: '18px', fontVariationSettings: form.iconEmoji === icon ? "'FILL' 1" : "'FILL' 0" }}>
                    {icon}
                  </span>
                </button>
              ))}
            </div>
          </div>

          {/* Color */}
          <div>
            <label className="text-xs font-bold uppercase tracking-widest block mb-2" style={{ color: '#3e4850' }}>Theme Color</label>
            <div className="flex items-center gap-2 flex-wrap">
              {PRESET_COLORS.map(c => (
                <button key={c} type="button" onClick={() => set('colorHex', c)}
                  className="w-8 h-8 rounded-full transition-all"
                  style={{
                    backgroundColor: c,
                    border: form.colorHex === c ? `3px solid ${c}` : '3px solid transparent',
                    outline: form.colorHex === c ? `2px solid #ffffff` : 'none',
                    boxShadow: form.colorHex === c ? `0 0 0 4px ${c}40` : 'none',
                  }} />
              ))}
              <input type="color" value={form.colorHex} onChange={e => set('colorHex', e.target.value)}
                className="w-8 h-8 rounded-full cursor-pointer border-0 p-0 outline-none"
                style={{ border: '2px solid #bdc8d2' }} />
              <span className="text-xs font-mono font-bold px-2 py-1 rounded-lg" style={{ backgroundColor: '#f5f3f3', color: '#3e4850' }}>{form.colorHex}</span>
            </div>
          </div>

          {error && (
            <div className="px-4 py-3 rounded-xl text-sm font-bold"
              style={{ backgroundColor: '#ffdad6', color: '#ba1a1a', border: '2px solid #ffb4ab' }}>
              {error}
            </div>
          )}

          {/* Actions */}
          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={saving}
              className="btn-tactile flex-1 py-3 rounded-xl font-bold text-sm uppercase tracking-wide flex items-center justify-center gap-2"
              style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e', opacity: saving ? 0.7 : 1 }}>
              {saving ? (
                <><span className="material-symbols-outlined text-base animate-spin">progress_activity</span> Saving…</>
              ) : (
                <><span className="material-symbols-outlined text-base">{initial ? 'save' : 'add_circle'}</span> {initial ? 'Save Changes' : 'Create Topic'}</>
              )}
            </button>
            <button type="button" onClick={onClose}
              className="btn-tactile px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
              style={{ backgroundColor: '#f5f3f3', color: '#3e4850', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

const TOPIC_COLORS = [
  { bg: '#e8f4ff', icon: '#006590' },
  { bg: '#ffdf92', icon: '#755b00' },
  { bg: '#f4d9ff', icon: '#843ab4' },
  { bg: '#c8e6ff', icon: '#004c6e' },
  { bg: '#d8f3dc', icon: '#1b5e20' },
  { bg: '#ffdad6', icon: '#93000a' },
];

export default function TopicsPage() {
  const [topicList, setTopicList] = useState<Topic[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Topic | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<Topic | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = () => {
    setLoading(true);
    topics.list()
      .then(res => setTopicList((Array.isArray(res) ? res : []) as Topic[]))
      .catch(() => setTopicList([]))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  const filtered = topicList.filter(t =>
    !search || t.name?.toLowerCase().includes(search.toLowerCase())
  );

  const handleSave = async (form: TopicForm) => {
    if (editTarget) {
      await topics.update(editTarget.id, form);
    } else {
      await topics.create(form);
    }
    load();
  };

  const handleDelete = async () => {
    if (!deleteConfirm) return;
    setDeleting(true);
    try {
      await topics.delete(deleteConfirm.id);
      setDeleteConfirm(null);
      load();
    } finally {
      setDeleting(false);
    }
  };

  return (
    <AdminLayout title="Topics">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Topics</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
              {loading ? 'Loading…' : `${topicList.length} topics total`}
            </p>
          </div>
          <button
            className="btn-tactile flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
            style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}
            onClick={() => { setEditTarget(null); setModalOpen(true); }}
          >
            <span className="material-symbols-outlined text-base">add</span>
            New Topic
          </button>
        </div>

        {/* Search */}
        <div className="relative w-72">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg" style={{ color: '#3e4850' }}>search</span>
          <input value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Search topics..."
            className="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none"
            style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c' }}
            onFocus={e => (e.target.style.borderColor = '#006590')}
            onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
        </div>

        {/* Content */}
        {loading ? (
          <div className="py-20 text-center text-sm" style={{ color: '#3e4850' }}>Loading topics...</div>
        ) : filtered.length === 0 ? (
          <div className="py-20 text-center rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2' }}>
            <span className="material-symbols-outlined text-5xl block mb-3" style={{ color: '#bdc8d2' }}>topic</span>
            <p className="text-sm font-bold" style={{ color: '#3e4850' }}>No topics found.</p>
            <p className="text-xs mt-1 mb-4" style={{ color: '#6e7881' }}>Topics help organise flashcards by subject area.</p>
            <button
              className="btn-tactile inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm"
              style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}
              onClick={() => { setEditTarget(null); setModalOpen(true); }}>
              <span className="material-symbols-outlined text-base">add</span>
              Create first topic
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            {filtered.map((topic, i) => {
              const clr = TOPIC_COLORS[i % TOPIC_COLORS.length];
              const iconBg = topic.colorHex ? topic.colorHex + '28' : clr.bg;
              const iconColor = topic.colorHex ?? clr.icon;
              return (
                <div key={topic.id} className="rounded-2xl p-5 group transition-all relative"
                  style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}
                  onMouseEnter={e => (e.currentTarget.style.borderColor = iconColor)}
                  onMouseLeave={e => (e.currentTarget.style.borderColor = '#bdc8d2')}>
                  {/* Actions */}
                  <div className="absolute top-3 right-3 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onClick={() => { setEditTarget(topic); setModalOpen(true); }}
                      className="p-1.5 rounded-lg" style={{ color: '#006590' }}
                      onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#e8f4ff')}
                      onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <span className="material-symbols-outlined text-base">edit</span>
                    </button>
                    <button onClick={() => setDeleteConfirm(topic)}
                      className="p-1.5 rounded-lg" style={{ color: '#ba1a1a' }}
                      onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#ffdad6')}
                      onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <span className="material-symbols-outlined text-base">delete</span>
                    </button>
                  </div>

                  <div className="flex items-start gap-3 mb-3">
                    <div className="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0"
                      style={{ backgroundColor: iconBg }}>
                      <span className="material-symbols-outlined"
                        style={{ color: iconColor, fontSize: '22px', fontVariationSettings: "'FILL' 1" }}>
                        {topic.iconEmoji || 'topic'}
                      </span>
                    </div>
                    <div className="min-w-0">
                      <h3 className="font-extrabold text-sm leading-tight" style={{ color: '#1b1c1c' }}>{topic.name}</h3>
                      {topic.slug && (
                        <p className="text-[10px] font-mono mt-0.5" style={{ color: '#6e7881' }}>/{topic.slug}</p>
                      )}
                    </div>
                  </div>

                  {topic.description && (
                    <p className="text-xs line-clamp-2 mb-3" style={{ color: '#3e4850' }}>{topic.description}</p>
                  )}

                  {topic.wordCount != null && (
                    <div className="flex items-center gap-1.5 mt-3 pt-3 text-xs font-bold"
                      style={{ borderTop: '1px solid #efeded', color: iconColor }}>
                      <span className="material-symbols-outlined" style={{ fontSize: '14px' }}>style</span>
                      {topic.wordCount} words
                    </div>
                  )}

                  {topic.colorHex && (
                    <div className="absolute bottom-3 right-4 w-2.5 h-2.5 rounded-full"
                      style={{ backgroundColor: topic.colorHex }} />
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Create/Edit Modal */}
      {modalOpen && (
        <TopicModal
          initial={editTarget}
          onClose={() => { setModalOpen(false); setEditTarget(null); }}
          onSave={handleSave}
        />
      )}

      {/* Delete Confirm Modal */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
          style={{ backgroundColor: 'rgba(0,0,0,0.4)' }}>
          <div className="w-full max-w-sm rounded-3xl p-8 space-y-5"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '6px solid #bdc8d2' }}>
            <div className="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto"
              style={{ backgroundColor: '#ffdad6' }}>
              <span className="material-symbols-outlined text-2xl" style={{ color: '#ba1a1a' }}>delete_forever</span>
            </div>
            <div className="text-center">
              <h3 className="text-lg font-extrabold" style={{ color: '#1b1c1c' }}>Delete Topic</h3>
              <p className="text-sm mt-1.5" style={{ color: '#3e4850' }}>
                Are you sure you want to delete <strong>&ldquo;{deleteConfirm.name}&rdquo;</strong>? This cannot be undone.
              </p>
            </div>
            <div className="flex gap-3">
              <button onClick={handleDelete} disabled={deleting}
                className="btn-tactile flex-1 py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
                style={{ backgroundColor: '#ba1a1a', color: '#ffffff', borderBottom: '4px solid #93000a' }}>
                {deleting ? 'Deleting…' : 'Delete'}
              </button>
              <button onClick={() => setDeleteConfirm(null)}
                className="btn-tactile flex-1 py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
                style={{ backgroundColor: '#f5f3f3', color: '#3e4850', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
