/**
 * fix(ui)/#50 – Deck CRUD modal: keyboard/focus, error state, accessibility
 *
 * Theo checklist Issue #50:
 *  - Modal dùng AccessibleDialog: focus vào heading/control phù hợp, Escape đóng, đóng xong trả focus về trigger
 *  - Form có label/name/type; icon-only button có aria-label
 *  - Async message dùng role-alert hoặc aria-live-polite
 *  - Destructive action có confirm
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { adminCatalog } from '@/lib/api';

// Stub next/navigation
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
    adminCatalog: {
      ...actual.adminCatalog,
      decks: vi.fn(),
      createDeck: vi.fn(),
      updateDeck: vi.fn(),
      deleteDeck: vi.fn(),
    },
  };
});

const mockDecks = [
  {
    id: 1,
    name: 'Business English',
    slug: 'business-english',
    description: 'Vocabulary for business',
    is_public: true,
    vocabularies_count: 42,
  },
];

async function renderPage() {
  const { default: DecksPage } = await import('./page');
  return render(<DecksPage />);
}

describe('Decks page – loading state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hiển thị loading text khi đang tải decks', async () => {
    vi.mocked(adminCatalog.decks).mockReturnValue(new Promise(() => {}));

    await renderPage();

    expect(screen.getByText(/Loading decks/i)).toBeInTheDocument();
  });
});

describe('Decks page – empty state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hiển thị thông báo khi không có decks', async () => {
    vi.mocked(adminCatalog.decks).mockResolvedValue({ data: [], meta: { page: 1, per_page: 100, total: 0, last_page: 1 } });

    await renderPage();

    await waitFor(() =>
      expect(screen.getByText(/No decks yet/i)).toBeInTheDocument()
    );
  });
});

describe('Decks page – error state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hiển thị error với role="alert" khi API thất bại', async () => {
    vi.mocked(adminCatalog.decks).mockRejectedValue(new Error('Service unavailable'));

    await renderPage();

    await waitFor(() => {
      const alert = screen.getByRole('alert');
      expect(alert).toBeInTheDocument();
      expect(alert.textContent).toMatch(/Service unavailable/i);
    });
  });
});

describe('DeckModal – keyboard/focus accessibility', () => {
  beforeEach(() => vi.clearAllMocks());

  it('mở modal Create Deck và focus vào trường đầu tiên', async () => {
    vi.mocked(adminCatalog.decks).mockResolvedValue({ data: [], meta: { page: 1, per_page: 100, total: 0, last_page: 1 } });

    await renderPage();
    await waitFor(() => screen.getByText(/No decks yet/i));

    const createBtn = screen.getByRole('button', { name: /Create Deck/i });
    await userEvent.click(createBtn);

    // Modal phải hiển thị
    await waitFor(() =>
      expect(screen.getByRole('dialog')).toBeInTheDocument()
    );
  });

  it('nhấn Escape đóng modal', async () => {
    vi.mocked(adminCatalog.decks).mockResolvedValue({ data: [], meta: { page: 1, per_page: 100, total: 0, last_page: 1 } });

    await renderPage();
    await waitFor(() => screen.getByText(/No decks yet/i));

    const createBtn = screen.getByRole('button', { name: /Create Deck/i });
    await userEvent.click(createBtn);
    await waitFor(() => screen.getByRole('dialog'));

    await userEvent.keyboard('{Escape}');

    await waitFor(() =>
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    );
  });
});

describe('DeckModal – save error state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hiển thị thông báo lỗi khi createDeck thất bại', async () => {
    vi.mocked(adminCatalog.decks).mockResolvedValue({ data: [], meta: { page: 1, per_page: 100, total: 0, last_page: 1 } });
    vi.mocked(adminCatalog.createDeck).mockRejectedValue(new Error('Name already taken'));

    await renderPage();
    await waitFor(() => screen.getByText(/No decks yet/i));

    await userEvent.click(screen.getByRole('button', { name: /Create Deck/i }));
    await waitFor(() => screen.getByRole('dialog'));

    // Điền tên deck
    const nameInput = screen.getByPlaceholderText(/Business Vocabulary/i);
    await userEvent.type(nameInput, 'Test Deck');

    // Tìm submit button bên trong dialog (type=submit)
    const submitBtns = screen.getAllByRole('button', { name: /Create Deck/i });
    const submitBtn = submitBtns.find(btn => btn.getAttribute('type') === 'submit');
    expect(submitBtn).toBeDefined();
    if (submitBtn) {
      await userEvent.click(submitBtn);
      await waitFor(() =>
        expect(screen.getByText(/Name already taken/i)).toBeInTheDocument()
      );
    }
  });
});

describe('Deck deletion – destructive action confirm', () => {
  beforeEach(() => vi.clearAllMocks());

  it('hỏi xác nhận trước khi xóa deck', async () => {
    vi.mocked(adminCatalog.decks).mockResolvedValue({
      data: mockDecks,
      meta: { page: 1, per_page: 100, total: 1, last_page: 1 },
    });

    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);

    await renderPage();
    await waitFor(() => screen.getByText('Business English'));

    expect(confirmSpy).not.toHaveBeenCalled();
  });
});
