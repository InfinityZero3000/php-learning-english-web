'use client';

import { useEffect, useState, useCallback } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { importJobs } from '@/lib/api';

interface ImportJob {
  id: string;
  status?: string;
  wordCount?: number;
  importedCount?: number;
  failedCount?: number;
  createdAt?: string;
  completedAt?: string;
  errorMessage?: string;
  [key: string]: unknown;
}

const statusStyles: Record<string, { bg: string; color: string }> = {
  COMPLETED: { bg: '#c8e6ff', color: '#004c6e' },
  PROCESSING: { bg: '#ffdf92', color: '#594400' },
  PENDING: { bg: '#efeded', color: '#3e4850' },
  FAILED: { bg: '#ffdad6', color: '#93000a' },
};

export default function ImportPage() {
  const [jobs, setJobs] = useState<ImportJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedJob, setSelectedJob] = useState<ImportJob | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await importJobs.list();
      setJobs(Array.isArray(res) ? (res as ImportJob[]) : []);
    } catch {
      setJobs([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const loadJobDetail = async (job: ImportJob) => {
    try {
      const detail = await importJobs.get(job.id);
      setSelectedJob(detail as ImportJob);
    } catch {
      setSelectedJob(job);
    }
  };

  return (
    <AdminLayout title="Import Jobs">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <p className="text-sm" style={{ color: '#3e4850' }}>
            Track word import jobs from CSV/JSON files.
          </p>
          <button onClick={load}
            className="btn-tactile flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-sm"
            style={{ backgroundColor: '#efeded', color: '#1b1c1c', borderBottom: '3px solid #bdc8d2' }}>
            <span className="material-symbols-outlined text-base">refresh</span>
            Refresh
          </button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Jobs List */}
          <div className="lg:col-span-2 rounded-3xl"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="p-5 rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <h3 className="font-extrabold" style={{ color: '#1b1c1c' }}>Import History</h3>
            </div>
            {loading ? (
              <div className="p-8 text-center text-sm" style={{ color: '#3e4850' }}>Loading...</div>
            ) : jobs.length === 0 ? (
              <div className="p-10 text-center">
                <span className="material-symbols-outlined text-5xl block mb-3" style={{ color: '#bdc8d2' }}>
                  upload_file
                </span>
                <p className="text-sm font-bold" style={{ color: '#3e4850' }}>No import jobs found.</p>
                <p className="text-xs mt-1" style={{ color: '#bdc8d2' }}>
                  Use the API to start a word import job.
                </p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead>
                    <tr style={{ backgroundColor: '#f5f3f3', borderBottom: '2px solid #efeded' }}>
                      {['Job ID', 'Status', 'Words', 'Imported', 'Failed', 'Date'].map(h => (
                        <th key={h} className="px-5 py-3 text-xs font-bold uppercase tracking-widest"
                          style={{ color: '#3e4850' }}>{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {jobs.map((job) => {
                      const sc = statusStyles[job.status ?? ''] ?? { bg: '#efeded', color: '#3e4850' };
                      const isSelected = selectedJob?.id === job.id;
                      return (
                        <tr key={job.id}
                          onClick={() => loadJobDetail(job)}
                          style={{
                            borderTop: '1px solid #efeded',
                            backgroundColor: isSelected ? '#f0f8ff' : '',
                            cursor: 'pointer',
                          }}
                          onMouseEnter={e => { if (!isSelected) (e.currentTarget.style.backgroundColor = '#f5f3f3'); }}
                          onMouseLeave={e => { if (!isSelected) (e.currentTarget.style.backgroundColor = ''); }}>
                          <td className="px-5 py-3 font-mono text-xs font-bold" style={{ color: '#006590' }}>
                            {String(job.id).substring(0, 8)}...
                          </td>
                          <td className="px-5 py-3">
                            <span className="px-2 py-1 rounded-full text-xs font-bold"
                              style={{ backgroundColor: sc.bg, color: sc.color }}>
                              {job.status ?? 'UNKNOWN'}
                            </span>
                          </td>
                          <td className="px-5 py-3 text-sm font-bold" style={{ color: '#1b1c1c' }}>
                            {job.wordCount ?? '—'}
                          </td>
                          <td className="px-5 py-3 text-sm font-bold" style={{ color: '#006590' }}>
                            {job.importedCount ?? '—'}
                          </td>
                          <td className="px-5 py-3">
                            {(job.failedCount as number) > 0 ? (
                              <span className="text-sm font-bold" style={{ color: '#ba1a1a' }}>
                                {job.failedCount}
                              </span>
                            ) : (
                              <span className="text-sm" style={{ color: '#bdc8d2' }}>0</span>
                            )}
                          </td>
                          <td className="px-5 py-3 text-xs" style={{ color: '#3e4850' }}>
                            {job.createdAt ? new Date(job.createdAt as string).toLocaleDateString() : '—'}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {/* Job Detail */}
          <div className="rounded-3xl"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="p-5 rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <h3 className="font-extrabold" style={{ color: '#1b1c1c' }}>Job Detail</h3>
            </div>
            {!selectedJob ? (
              <div className="p-8 text-center">
                <span className="material-symbols-outlined text-4xl block mb-2" style={{ color: '#bdc8d2' }}>
                  info
                </span>
                <p className="text-xs" style={{ color: '#3e4850' }}>Click a job to see details</p>
              </div>
            ) : (
              <div className="p-5 space-y-4">
                <div>
                  <p className="text-xs font-bold uppercase tracking-wider mb-1" style={{ color: '#3e4850' }}>Job ID</p>
                  <p className="font-mono text-xs break-all" style={{ color: '#1b1c1c' }}>{selectedJob.id}</p>
                </div>
                {[
                  { label: 'Status', value: selectedJob.status ?? 'Unknown' },
                  { label: 'Total Words', value: String(selectedJob.wordCount ?? '—') },
                  { label: 'Imported', value: String(selectedJob.importedCount ?? '—') },
                  { label: 'Failed', value: String(selectedJob.failedCount ?? '0') },
                  { label: 'Created', value: selectedJob.createdAt ? new Date(selectedJob.createdAt as string).toLocaleString() : '—' },
                  { label: 'Completed', value: selectedJob.completedAt ? new Date(selectedJob.completedAt as string).toLocaleString() : '—' },
                ].map(({ label, value }) => (
                  <div key={label} className="flex justify-between items-start p-3 rounded-xl"
                    style={{ backgroundColor: '#f5f3f3' }}>
                    <span className="text-xs font-bold uppercase tracking-wide" style={{ color: '#3e4850' }}>{label}</span>
                    <span className="text-xs font-bold" style={{ color: '#1b1c1c' }}>{value}</span>
                  </div>
                ))}
                {selectedJob.errorMessage && (
                  <div className="p-3 rounded-xl" style={{ backgroundColor: '#ffdad6', border: '1px solid #ba1a1a' }}>
                    <p className="text-xs font-bold uppercase tracking-wide mb-1" style={{ color: '#93000a' }}>Error</p>
                    <p className="text-xs" style={{ color: '#93000a' }}>{selectedJob.errorMessage}</p>
                  </div>
                )}
                {selectedJob.importedCount != null && selectedJob.wordCount != null && (
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wide mb-2" style={{ color: '#3e4850' }}>Progress</p>
                    <div className="h-3 rounded-full" style={{ backgroundColor: '#efeded' }}>
                      <div className="h-full rounded-full"
                        style={{
                          width: `${Math.min(100, ((selectedJob.importedCount as number) / Math.max(1, selectedJob.wordCount as number)) * 100)}%`,
                          backgroundColor: '#006590',
                        }} />
                    </div>
                    <p className="text-xs mt-1 font-bold" style={{ color: '#006590' }}>
                      {selectedJob.importedCount} / {selectedJob.wordCount}
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
