import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminLayout from './AdminLayout';
import { ApiError, auth } from '@/lib/api';

const replace = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ replace }) }));
vi.mock('./Sidebar', () => ({ default: () => <aside>Sidebar</aside> }));
vi.mock('./TopBar', () => ({ default: () => <header>Top bar</header> }));
vi.mock('@/lib/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api')>();
  return { ...actual, auth: { ...actual.auth, adminMe: vi.fn(), logout: vi.fn() } };
});

describe('AdminLayout guard', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders for admins', async () => {
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 1, role: 'admin' } as never);
    render(<AdminLayout><p>Protected</p></AdminLayout>);
    expect(await screen.findByText('Protected')).toBeInTheDocument();
  });

  it('redirects unauthenticated users', async () => {
    vi.mocked(auth.adminMe).mockRejectedValue(new ApiError(401, 'Unauthenticated'));
    render(<AdminLayout><p>Protected</p></AdminLayout>);
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/login'));
  });

  it('logs out non-admin users', async () => {
    vi.mocked(auth.adminMe).mockResolvedValue({ id: 2, role: 'learner' } as never);
    vi.mocked(auth.logout).mockResolvedValue(undefined);
    render(<AdminLayout><p>Protected</p></AdminLayout>);
    await waitFor(() => expect(auth.logout).toHaveBeenCalled());
    expect(replace).toHaveBeenCalledWith('/login?forbidden=1');
  });
});
