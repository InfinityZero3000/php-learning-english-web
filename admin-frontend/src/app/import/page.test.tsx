import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ImportPage from './page';
import { ApiError, adminImports, auth } from '@/lib/api';

vi.mock('@/components/AdminLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.mock('@/lib/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api')>();
  return {
    ...actual,
    auth: { ...actual.auth, adminMe: vi.fn() },
    adminImports: {
      ...actual.adminImports,
      checkpoints: vi.fn(),
      runs: vi.fn(),
      items: vi.fn(),
      run: vi.fn(),
      apply: vi.fn(),
    },
  };
});

describe('Import history panel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);
    vi.mocked(adminImports.checkpoints).mockResolvedValue([]);
  });

  it('shows loading then the history table', async () => {
    vi.mocked(adminImports.runs).mockResolvedValue({
      data: [{
        id: '1', request_id: 'req-1', entity: 'categories', status: 'review-ready',
        requested_limit: 50, reset: false, starting_cursor: 0, processed: 1, skipped: 0,
        result_cursor: 1, error_code: null, error_message: null,
        created_at: '2026-07-31T00:00:00Z', updated_at: '2026-07-31T00:00:00Z',
        staged_new_count: 1, staged_update_count: 0, staged_invalid_count: 0,
      }],
      meta: { page: 1, per_page: 10, total: 1, last_page: 1 },
    });

    render(<ImportPage />);
    expect(await screen.findByRole('cell', { name: 'categories' })).toBeInTheDocument();
    expect(screen.getByText('View diff')).toBeInTheDocument();
  });

  it('shows an empty state when there are no runs', async () => {
    vi.mocked(adminImports.runs).mockResolvedValue({ data: [], meta: { page: 1, per_page: 10, total: 0, last_page: 1 } });

    render(<ImportPage />);
    expect(await screen.findByText('No import runs match this filter yet.')).toBeInTheDocument();
  });

  it('shows an error state and retries', async () => {
    vi.mocked(adminImports.runs)
      .mockRejectedValueOnce(new ApiError(500, 'Server error'))
      .mockResolvedValueOnce({ data: [], meta: { page: 1, per_page: 10, total: 0, last_page: 1 } });

    render(<ImportPage />);
    expect(await screen.findByText('Server error')).toBeInTheDocument();

    screen.getByText('Thử lại').click();
    await waitFor(() => expect(adminImports.runs).toHaveBeenCalledTimes(2));
  });

  it('renders staged item diffs with no apply/approve action', async () => {
    vi.mocked(adminImports.runs).mockResolvedValue({
      data: [{
        id: '1', request_id: 'req-1', entity: 'categories', status: 'review-ready',
        requested_limit: 50, reset: false, starting_cursor: 0, processed: 1, skipped: 0,
        result_cursor: 1, error_code: null, error_message: null,
        created_at: '2026-07-31T00:00:00Z', updated_at: '2026-07-31T00:00:00Z',
        staged_new_count: 1, staged_update_count: 0, staged_invalid_count: 0,
      }],
      meta: { page: 1, per_page: 10, total: 1, last_page: 1 },
    });
    vi.mocked(adminImports.items).mockResolvedValue({
      data: [{
        id: 1, admin_import_run_id: 1, entity: 'categories', external_id: 'cat-1', classification: 'new',
        incoming_snapshot: { name: 'Everyday English' }, existing_snapshot: null, errors: null,
        status: 'staged', created_at: '2026-07-31T00:00:00Z', updated_at: '2026-07-31T00:00:00Z',
      }],
      meta: { page: 1, per_page: 50, total: 1, last_page: 1 },
    });

    render(<ImportPage />);
    const viewDiff = await screen.findByText('View diff');
    viewDiff.click();

    await waitFor(() => expect(adminImports.items).toHaveBeenCalledWith('1', { classification: undefined, perPage: 50 }));
    expect(await screen.findByText('Everyday English')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /apply|approve/i })).not.toBeInTheDocument();
  });

  it('lets a super admin apply selected staged categories', async () => {
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'super_admin' } as never);
    vi.mocked(adminImports.runs).mockResolvedValue({
      data: [{
        id: '1', request_id: 'req-1', entity: 'categories', status: 'review-ready',
        requested_limit: 50, reset: false, starting_cursor: 0, processed: 1, skipped: 0,
        result_cursor: 1, error_code: null, error_message: null,
        created_at: '2026-07-31T00:00:00Z', updated_at: '2026-07-31T00:00:00Z',
        staged_new_count: 1, staged_update_count: 0, staged_invalid_count: 0,
      }],
      meta: { page: 1, per_page: 10, total: 1, last_page: 1 },
    });
    vi.mocked(adminImports.items).mockResolvedValue({
      data: [{
        id: 7, admin_import_run_id: 1, entity: 'categories', external_id: 'cat-1', classification: 'new',
        incoming_snapshot: { name: 'Everyday English' }, existing_snapshot: null, errors: null,
        status: 'staged', created_at: '2026-07-31T00:00:00Z', updated_at: '2026-07-31T00:00:00Z',
      }],
      meta: { page: 1, per_page: 50, total: 1, last_page: 1 },
    });
    vi.mocked(adminImports.apply).mockResolvedValue({ applied: [7], stale: [], failed: [] });

    render(<ImportPage />);
    const viewDiff = await screen.findByText('View diff');
    viewDiff.click();
    await screen.findByText('Everyday English');

    const checkbox = screen.getByRole('checkbox', { name: 'Select cat-1' });
    checkbox.click();
    const applyButton = screen.getByRole('button', { name: /Apply selected/i });
    applyButton.click();

    await waitFor(() => expect(adminImports.apply).toHaveBeenCalledWith('1', [7]));
    expect(await screen.findByText('Applied 1, stale 0, failed 0.')).toBeInTheDocument();
  });
});
