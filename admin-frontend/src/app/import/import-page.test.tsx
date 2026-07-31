import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ImportWorkspace } from './page';
import {
  ApiError, adminImports, auth,
  type AdminImportItem, type AdminImportRun, type User,
} from '@/lib/api';

const assign = vi.fn();

const run = {
  id: 1,
  request_id: '11111111-1111-4111-8111-111111111111',
  entity: 'categories',
  status: 'review_ready',
  requested_limit: 10,
  reset: false,
  starting_cursor: 0,
  processed: null,
  skipped: null,
  result_cursor: 7,
  error_code: null,
  error_message: null,
  created_at: '2026-07-29T00:00:00.000Z',
  updated_at: '2026-07-29T00:00:00.000Z',
  items_count: 3,
  counts: { new: 2, upstream_update: 1 },
} satisfies AdminImportRun;

const category = {
  id: 11, parent_item_id: null, entity: 'category', source_system: 'lexilingo', external_id: 'cat-1',
  natural_key: 'fresh', candidate_payload: { name: 'Fresh', slug: 'fresh' }, base_snapshot: null,
  classification: 'new', selected_action: 'add', dependencies: [], validation_errors: [],
  target_id: null, base_revision: null, reviewed_at: null, applied_at: null,
} satisfies AdminImportItem;

const course = {
  ...category,
  id: 12, entity: 'course', external_id: 'course-1', natural_key: 'x',
  candidate_payload: { title: 'New title', slug: 'x' }, base_snapshot: { title: 'Old title', slug: 'x' },
  classification: 'upstream_update', selected_action: 'replace', target_id: 5, base_revision: 3,
} satisfies AdminImportItem;

const unit = {
  ...category,
  id: 13, entity: 'unit', external_id: 'unit-1', natural_key: null,
  candidate_payload: { title: 'Child unit' }, parent_item_id: 11,
  dependencies: [{ entity: 'category', external_id: 'cat-1' }],
} satisfies AdminImportItem;

const items = [category, course, unit];

function stub(overrides: Partial<AdminImportRun> = {}, staged: AdminImportItem[] = items) {
  vi.spyOn(adminImports, 'checkpoints').mockResolvedValue([]);
  vi.spyOn(adminImports, 'history').mockResolvedValue([{ ...run, ...overrides }]);
  vi.spyOn(adminImports, 'run').mockResolvedValue({ ...run, ...overrides });
  vi.spyOn(adminImports, 'items').mockResolvedValue(staged);
  vi.spyOn(auth, 'adminMe').mockResolvedValue({ id: 1, name: 'Admin', email: 'a@b.test', email_verified_at: null, role: 'admin' } satisfies User);
}

async function openFirstRun() {
  fireEvent.click(await screen.findByRole('button', { name: /categories/ }));
  await screen.findByRole('table');
}

describe('import review workspace', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    assign.mockReset();
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...window.location, search: '', assign },
    });
  });

  afterEach(() => { vi.useRealTimers(); });

  it('lists run history with classification counts, server default actions and a field diff', async () => {
    stub();
    render(<ImportWorkspace />);

    const history = await screen.findByRole('list', { name: 'Import run history' });
    expect(within(history).getByText('categories')).toBeInTheDocument();
    await openFirstRun();

    const counts = screen.getByRole('list', { name: 'Classification counts' });
    expect(within(counts).getByText('new: 2')).toBeInTheDocument();
    expect(within(counts).getByText('upstream update: 1')).toBeInTheDocument();

    expect(screen.getByRole('combobox', { name: 'Action for cat-1' })).toHaveValue('add');
    expect(screen.getByRole('combobox', { name: 'Action for course-1' })).toHaveValue('replace');

    fireEvent.click(screen.getByRole('button', { name: 'Diff course-1' }));
    const diff = await screen.findByRole('region', { name: 'Changes for course-1' });
    expect(within(diff).getByText('Old title')).toBeInTheDocument();
    expect(within(diff).getByText('New title')).toBeInTheDocument();
    expect(within(diff).queryByText('slug')).not.toBeInTheDocument();

    expect(screen.queryByLabelText(/password/i)).not.toBeInTheDocument();
    expect(document.body.textContent).not.toMatch(/password|secret|api key|token/i);
  });

  it('warns about cascaded exclusion and saves only the reviewer edits as a draft', async () => {
    stub();
    const saveDraft = vi.spyOn(adminImports, 'saveDraft').mockResolvedValue({ ...run, status: 'review_ready' });
    render(<ImportWorkspace />);
    await openFirstRun();

    expect(screen.getByRole('button', { name: 'Save draft' })).toBeDisabled();
    fireEvent.change(screen.getByRole('combobox', { name: 'Action for cat-1' }), { target: { value: 'exclude' } });
    expect(await screen.findByRole('note')).toHaveTextContent('Excluded with its parent record.');

    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }));
    await waitFor(() => expect(saveDraft).toHaveBeenCalledWith(1, [{ id: 11, action: 'exclude' }]));
  });

  it('blocks approval until invalid records are excluded', async () => {
    const invalid = { ...category, id: 14, external_id: 'bad-1', classification: 'invalid', selected_action: null, validation_errors: ['Missing name'] } satisfies AdminImportItem;
    stub({ counts: { invalid: 1 } }, [invalid]);
    render(<ImportWorkspace />);
    await openFirstRun();

    expect(screen.getByRole('button', { name: 'Approve' })).toBeDisabled();
    expect(screen.getByText('Invalid records must be excluded before approval.')).toBeInTheDocument();

    fireEvent.change(screen.getByRole('combobox', { name: 'Action for bad-1' }), { target: { value: 'exclude' } });
    await waitFor(() => expect(screen.getByRole('button', { name: 'Approve' })).toBeEnabled());
  });

  it('applies an approved run and refreshes the draft on a stale-catalog conflict', async () => {
    stub({ status: 'approved' });
    const apply = vi.spyOn(adminImports, 'apply').mockRejectedValue(new ApiError(409, 'Catalog changed after review.'));
    render(<ImportWorkspace />);
    await openFirstRun();
    vi.mocked(adminImports.items).mockClear();

    fireEvent.click(screen.getByRole('button', { name: 'Apply to catalog' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('The draft was refreshed'));
    expect(adminImports.items).toHaveBeenCalledWith(1);

    apply.mockResolvedValue({ ...run, status: 'completed' });
    fireEvent.click(screen.getByRole('button', { name: 'Apply to catalog' }));
    await waitFor(() => expect(apply).toHaveBeenCalledTimes(2));
    expect(screen.getByRole('status')).toHaveTextContent('Applied: completed.');
    expect(screen.getByRole('button', { name: 'Apply to catalog' })).toBeDisabled();
  });

  it('redirects a 428 to Google step-up with a safe run-scoped return path', async () => {
    stub({ status: 'approved' });
    vi.spyOn(adminImports, 'apply').mockRejectedValue(new ApiError(428, 'Recent Google verification is required.'));
    render(<ImportWorkspace />);
    await openFirstRun();

    fireEvent.click(screen.getByRole('button', { name: 'Apply to catalog' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('/api/v1/auth/oauth/google/admin?return=%2Fimport%3Frun%3D1'));
  });

  it('polls an active run every five seconds, slows down after a minute and stops on unmount', async () => {
    vi.useFakeTimers();
    stub({ status: 'fetching' });
    const tick = (ms: number) => act(async () => { await vi.advanceTimersByTimeAsync(ms); });
    const { unmount } = render(<ImportWorkspace />);
    await tick(0);

    fireEvent.click(screen.getByRole('button', { name: /categories/ }));
    await tick(0);
    const poll = vi.mocked(adminImports.run);
    poll.mockClear();

    await tick(5_000);
    expect(poll).toHaveBeenCalledTimes(1);

    await tick(55_000);
    expect(poll).toHaveBeenCalledTimes(12);

    await tick(5_000);
    expect(poll).toHaveBeenCalledTimes(12);
    await tick(25_000);
    expect(poll).toHaveBeenCalledTimes(13);

    unmount();
    await tick(60_000);
    expect(poll).toHaveBeenCalledTimes(13);
  });
});
