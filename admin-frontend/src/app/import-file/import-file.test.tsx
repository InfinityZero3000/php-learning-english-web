import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, adminFileImports } from '@/lib/api';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace: vi.fn() }),
  usePathname: () => '/',
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock('@/components/AdminLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}));

vi.mock('@/lib/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api')>();
  return {
    ...actual,
    adminFileImports: {
      templateUrl: '/api/v1/admin/imports/file/template',
      upload: vi.fn(),
      runs: vi.fn(),
      items: vi.fn(),
      apply: vi.fn(),
    },
  };
});

const RUN = {
  id: 'run-1', request_id: 'req-1', entity: 'file_catalog', status: 'review-ready',
  requested_limit: 1, reset: false, starting_cursor: 0, processed: 1, skipped: 0,
  result_cursor: null, error_code: null, error_message: null,
  created_at: '2026-01-01T00:00:00Z', updated_at: '2026-01-01T00:00:00Z',
  staged_new_count: 1, staged_invalid_count: 0,
} as never;

const STAGED_ITEM = {
  id: 1, admin_import_run_id: 1, entity: 'file_catalog', external_id: 'everyday-english',
  classification: 'new',
  incoming_snapshot: {
    title: 'Everyday English', slug: 'everyday-english',
    units: [{ title: 'Greetings', lessons: [{ title: 'Saying Hello', content: '', vocabulary: [{ word: 'hello', meaning: 'a greeting', pronunciation: null, part_of_speech: null, example: null }] }] }],
  },
  existing_snapshot: null, errors: null, status: 'staged',
  created_at: '2026-01-01T00:00:00Z', updated_at: '2026-01-01T00:00:00Z',
} as never;

async function renderPage() {
  const { default: ImportFilePage } = await import('./page');
  return render(<ImportFilePage />);
}

describe('Import file page', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows the run and a staged-course count after a successful upload', async () => {
    vi.mocked(adminFileImports.runs).mockResolvedValue({ data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } });
    vi.mocked(adminFileImports.upload).mockResolvedValue({
      run: RUN,
      staged_items: { data: [STAGED_ITEM], meta: { page: 1, per_page: 25, total: 1, last_page: 1 } },
    });

    await renderPage();
    await waitFor(() => screen.getByText(/No file imports yet/i));

    const file = new File(['course_title,...'], 'import.csv', { type: 'text/csv' });
    const input = screen.getByLabelText(/File/i) as HTMLInputElement;
    await userEvent.upload(input, file);
    await userEvent.click(screen.getByRole('button', { name: /^Upload$/i }));

    await waitFor(() => expect(adminFileImports.upload).toHaveBeenCalledWith(file));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent(/Staged 1 course/i));
  });

  it('surfaces a validation error from a rejected file without crashing the page', async () => {
    vi.mocked(adminFileImports.runs).mockResolvedValue({ data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } });
    vi.mocked(adminFileImports.upload).mockRejectedValue(new ApiError(422, 'Header must contain exactly these columns...'));

    await renderPage();
    await waitFor(() => screen.getByText(/No file imports yet/i));

    const file = new File(['bad,header'], 'bad.csv', { type: 'text/csv' });
    await userEvent.upload(screen.getByLabelText(/File/i), file);
    await userEvent.click(screen.getByRole('button', { name: /^Upload$/i }));

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent(/File rejected/i));
  });

  it('redirects through the Google step-up handoff when apply answers 428', async () => {
    vi.mocked(adminFileImports.runs).mockResolvedValue({ data: [RUN], meta: { page: 1, per_page: 20, total: 1, last_page: 1 } });
    vi.mocked(adminFileImports.items).mockResolvedValue({ data: [STAGED_ITEM], meta: { page: 1, per_page: 50, total: 1, last_page: 1 } });
    vi.mocked(adminFileImports.apply).mockRejectedValue(new ApiError(428, 'Recent Google verification is required.'));

    const original = window.location;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).location;
    window.location = { ...original, assign: vi.fn() } as unknown as Location;

    await renderPage();
    await waitFor(() => screen.getByRole('button', { name: /Review/i }));
    await userEvent.click(screen.getByRole('button', { name: /Review/i }));

    await waitFor(() => screen.getByLabelText(/Select Everyday English/i));
    await userEvent.click(screen.getByLabelText(/Select Everyday English/i));
    await userEvent.click(screen.getByRole('button', { name: /Apply selected/i }));

    await waitFor(() => expect(window.location.assign).toHaveBeenCalledWith(expect.stringContaining('/api/v1/auth/oauth/google/admin?return=%2Fimport-file')));

    window.location = original;
  });
});
