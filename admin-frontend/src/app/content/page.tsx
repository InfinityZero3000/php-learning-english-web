'use client';

import { useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { content } from '@/lib/api';

interface YouTubeVideo {
  id?: string;
  title?: string;
  description?: string;
  thumbnail?: string;
  channelTitle?: string;
  publishedAt?: string;
  url?: string;
  [key: string]: unknown;
}

interface NewsArticle {
  title?: string;
  description?: string;
  url?: string;
  source?: { name?: string };
  publishedAt?: string;
  urlToImage?: string;
  [key: string]: unknown;
}

type Tab = 'youtube' | 'news';

export default function ContentPage() {
  const [tab, setTab] = useState<Tab>('youtube');
  const [query, setQuery] = useState('');
  const [inputQuery, setInputQuery] = useState('');
  const [videos, setVideos] = useState<YouTubeVideo[]>([]);
  const [articles, setArticles] = useState<NewsArticle[]>([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);

  const search = async () => {
    setLoading(true);
    setSearched(true);
    setQuery(inputQuery);
    try {
      if (tab === 'youtube') {
        const res = await content.youtube({ q: inputQuery || undefined });
        const items = (res as { items?: YouTubeVideo[] })?.items ?? (Array.isArray(res) ? res : []);
        setVideos(items as YouTubeVideo[]);
      } else {
        const res = await content.news({ q: inputQuery || undefined });
        const items = (res as { articles?: NewsArticle[] })?.articles ?? (Array.isArray(res) ? res : []);
        setArticles(items as NewsArticle[]);
      }
    } catch {
      setVideos([]);
      setArticles([]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <AdminLayout title="Content Feed">
      <div className="space-y-6">
        {/* Tabs + Search */}
        <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
          <div className="flex gap-2 p-1 rounded-xl" style={{ backgroundColor: '#efeded' }}>
            {(['youtube', 'news'] as Tab[]).map(t => (
              <button key={t} onClick={() => setTab(t)}
                className="px-5 py-2 rounded-lg font-bold text-sm uppercase tracking-wide transition-all"
                style={{
                  backgroundColor: tab === t ? '#006590' : 'transparent',
                  color: tab === t ? '#ffffff' : '#3e4850',
                }}>
                <span className="material-symbols-outlined text-sm mr-1 align-middle">
                  {t === 'youtube' ? 'play_circle' : 'newspaper'}
                </span>
                {t === 'youtube' ? 'YouTube' : 'News'}
              </button>
            ))}
          </div>

          <div className="flex gap-3">
            <div className="relative">
              <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-base"
                style={{ color: '#3e4850' }}>search</span>
              <input
                value={inputQuery}
                onChange={e => setInputQuery(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') search(); }}
                placeholder={tab === 'youtube' ? 'Search videos...' : 'Search news...'}
                className="pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none"
                style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c', width: '260px' }}
                onFocus={e => (e.target.style.borderColor = '#006590')}
                onBlur={e => (e.target.style.borderColor = '#bdc8d2')}
              />
            </div>
            <button onClick={search} disabled={loading}
              className="btn-tactile px-6 py-2.5 rounded-xl font-bold text-sm uppercase tracking-wide"
              style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
              {loading ? 'Loading...' : 'Search'}
            </button>
          </div>
        </div>

        {/* Results */}
        {!searched ? (
          <div className="rounded-3xl p-16 text-center"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2' }}>
            <span className="material-symbols-outlined text-6xl block mb-4" style={{ color: '#bdc8d2' }}>
              {tab === 'youtube' ? 'smart_display' : 'newspaper'}
            </span>
            <p className="font-bold text-lg" style={{ color: '#3e4850' }}>
              Search for {tab === 'youtube' ? 'YouTube videos' : 'news articles'}
            </p>
            <p className="text-sm mt-2" style={{ color: '#bdc8d2' }}>
              Enter a query above to find relevant learning content
            </p>
          </div>
        ) : loading ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {[1, 2, 3, 4, 5, 6].map(i => (
              <div key={i} className="rounded-2xl overflow-hidden animate-pulse"
                style={{ backgroundColor: '#efeded', height: '200px' }} />
            ))}
          </div>
        ) : tab === 'youtube' ? (
          videos.length === 0 ? (
            <div className="p-10 rounded-3xl text-center"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2' }}>
              <p className="text-sm font-bold" style={{ color: '#3e4850' }}>
                {query ? `No videos found for "${query}"` : 'No videos available. Check YouTube API key.'}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              {videos.map((v, i) => (
                <a key={v.id ?? i} href={v.url ?? `https://youtube.com/watch?v=${v.id}`}
                  target="_blank" rel="noopener noreferrer"
                  className="rounded-2xl overflow-hidden group transition-transform hover:-translate-y-1"
                  style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
                  {v.thumbnail && (
                    <div className="w-full h-40 overflow-hidden" style={{ backgroundColor: '#efeded' }}>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={v.thumbnail as string} alt={String(v.title ?? '')}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                    </div>
                  )}
                  <div className="p-4">
                    <p className="font-bold text-sm line-clamp-2 mb-1" style={{ color: '#1b1c1c' }}>
                      {String(v.title ?? 'Untitled')}
                    </p>
                    <p className="text-xs" style={{ color: '#3e4850' }}>
                      {String(v.channelTitle ?? '')}
                    </p>
                  </div>
                </a>
              ))}
            </div>
          )
        ) : (
          articles.length === 0 ? (
            <div className="p-10 rounded-3xl text-center"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2' }}>
              <p className="text-sm font-bold" style={{ color: '#3e4850' }}>
                {query ? `No articles found for "${query}"` : 'No articles available. Check News API key.'}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
              {articles.map((a, i) => (
                <a key={i} href={String(a.url ?? '#')} target="_blank" rel="noopener noreferrer"
                  className="flex gap-4 rounded-2xl overflow-hidden p-4 group transition-transform hover:-translate-y-0.5"
                  style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
                  {a.urlToImage && (
                    <div className="w-24 h-20 rounded-xl overflow-hidden flex-shrink-0"
                      style={{ backgroundColor: '#efeded' }}>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={String(a.urlToImage)} alt=""
                        className="w-full h-full object-cover" />
                    </div>
                  )}
                  <div className="flex-1 min-w-0">
                    <p className="font-bold text-sm line-clamp-2 mb-1" style={{ color: '#1b1c1c' }}>
                      {String(a.title ?? 'Untitled')}
                    </p>
                    <p className="text-xs line-clamp-2" style={{ color: '#3e4850' }}>
                      {String(a.description ?? '')}
                    </p>
                    <p className="text-xs mt-2 font-bold" style={{ color: '#bdc8d2' }}>
                      {a.source?.name ?? ''} · {a.publishedAt ? new Date(a.publishedAt as string).toLocaleDateString() : ''}
                    </p>
                  </div>
                </a>
              ))}
            </div>
          )
        )}
      </div>
    </AdminLayout>
  );
}
