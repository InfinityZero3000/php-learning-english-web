/**
 * fix(ui)/#50 – Import page: loading/error/retry states & accessibility
 *
 * Theo checklist Issue #50:
 *  - Async message dùng role="status" hoặc aria-live="polite"
 *  - Destructive action có confirm
 *  - Loading/empty/error state
 */
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { adminImports, auth } from '@/lib/api';

// Stub next/navigation
vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace: vi.fn() }),
  usePathname: () => '/',
  useSearchParams: () => new URLSearchParams(),
}));

// Stub AdminLayout – chỉ render children
vi.mock('@/components/AdminLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}));

vi.mock('@/lib/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api')>();
  return {
    ...actual,
    adminImports: {
      checkpoints: vi.fn(),
      resume: vi.fn(),
      reset: vi.fn(),
    },
    auth: { ...actual.auth, adminMe: vi.fn() },
  };
});

async function renderPage() {
  // Dynamic import để tránh module-level side effects
  const { default: ImportPage } = await import('./page');
  return render(<ImportPage />);
}

describe('Import page – loading state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hiển thị trạng thái loading khi đang tải checkpoints', async () => {
    // checkpoints không bao giờ resolve trong test này
    vi.mocked(adminImports.checkpoints).mockReturnValue(new Promise(() => {}));
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);

    await renderPage();

    expect(screen.getByText(/Loading checkpoints/i)).toBeInTheDocument();
  });
});

describe('Import page – empty state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hiển thị thông báo khi không có checkpoints', async () => {
    vi.mocked(adminImports.checkpoints).mockResolvedValue([]);
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);

    await renderPage();

    await waitFor(() =>
      expect(screen.getByText(/No checkpoints found/i)).toBeInTheDocument()
    );
  });
});

describe('Import page – error state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hiển thị thông báo lỗi khi API thất bại', async () => {
    vi.mocked(adminImports.checkpoints).mockRejectedValue(
      new Error('Network error')
    );
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);

    await renderPage();

    // Thông báo dùng role="status" (aria-live)
    await waitFor(() => {
      const statusEl = screen.getByRole('status');
      expect(statusEl).toBeInTheDocument();
      expect(statusEl.textContent).toMatch(/Network error/i);
    });
  });
});

describe('Import page – retry/refresh', () => {
  beforeEach(() => vi.clearAllMocks());

  it('nút Refresh gọi lại API checkpoints', async () => {
    vi.mocked(adminImports.checkpoints).mockResolvedValue([]);
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);

    await renderPage();
    await waitFor(() => screen.getByText(/No checkpoints found/i));

    const refreshBtn = screen.getByRole('button', { name: /Refresh/i });
    expect(refreshBtn).toBeInTheDocument();

    fireEvent.click(refreshBtn);

    await waitFor(() =>
      expect(vi.mocked(adminImports.checkpoints)).toHaveBeenCalledTimes(2)
    );
  });
});

describe('Import page – accessibility', () => {
  beforeEach(() => vi.clearAllMocks());

  it('status message có role="status" (aria-live region)', async () => {
    vi.mocked(adminImports.checkpoints).mockRejectedValue(
      new Error('Provider timeout')
    );
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);

    await renderPage();

    await waitFor(() =>
      expect(screen.getByRole('status')).toBeInTheDocument()
    );
  });

  it('nút Start/resume có thể focus và disabled khi đang xử lý', async () => {
    vi.mocked(adminImports.checkpoints).mockResolvedValue([]);
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);
    // resume trả về promise chưa resolve → nút bị disabled trong thời gian xử lý
    vi.mocked(adminImports.resume).mockReturnValue(new Promise(() => {}));

    await renderPage();
    await waitFor(() => screen.getByText(/No checkpoints found/i));

    const startBtn = screen.getByRole('button', { name: /Start \/ resume/i });
    fireEvent.click(startBtn);

    await waitFor(() => expect(startBtn).toBeDisabled());
  });
});
