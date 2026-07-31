import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RolesPage from './page';
import { ApiError, adminUsers, roleManagement, type AdminRole, type AdminUser, type TeacherScope } from '@/lib/api';

vi.mock('@/components/AdminLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

const assign = vi.fn();
const teacher = { id: 2, name: 'Teacher One', email: 't@example.test', role: 'teacher' } as AdminUser;
const learner = { id: 3, name: 'Learner One', email: 'l@example.test', role: 'learner' } as AdminUser;
const scope = { id: 9, teacher, learner, assigned_at: '2026-07-29T00:00:00.000Z' } as TeacherScope;
const meta = { page: 1, per_page: 100, total: 1, last_page: 1 };

function stub(scopes: TeacherScope[] = [scope]) {
  vi.spyOn(roleManagement, 'roles').mockResolvedValue([{ id: 1, name: 'Teacher', slug: 'teacher', users_count: 1 } as AdminRole]);
  vi.spyOn(roleManagement, 'scopes').mockResolvedValue(scopes);
  vi.spyOn(adminUsers, 'list')
    .mockResolvedValueOnce({ data: [teacher], meta })
    .mockResolvedValueOnce({ data: [learner], meta });
}

describe('roles and teacher scope page', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    assign.mockReset();
    Object.defineProperty(window, 'location', { configurable: true, value: { ...window.location, assign } });
  });

  it('assigns a teacher scope and shows a plain failure message', async () => {
    stub([]);
    const create = vi.spyOn(roleManagement, 'assign').mockResolvedValue(scope);
    render(<RolesPage />);
    await screen.findByRole('heading', { name: 'Assign teacher' });

    fireEvent.change(screen.getByRole('combobox', { name: /Teacher/ }), { target: { value: '2' } });
    fireEvent.change(screen.getByRole('combobox', { name: /Learner/ }), { target: { value: '3' } });
    fireEvent.click(screen.getByRole('button', { name: 'Assign scope' }));
    await waitFor(() => expect(create).toHaveBeenCalledWith(2, 3));
    expect(await screen.findByRole('status')).toHaveTextContent('Teacher scope assigned.');

    create.mockRejectedValue(new ApiError(422, 'Selected user is not a teacher.'));
    fireEvent.click(screen.getByRole('button', { name: 'Assign scope' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Selected user is not a teacher.'));
    expect(assign).not.toHaveBeenCalled();
  });

  it('redirects a stale Google session to the handoff instead of surfacing the 428', async () => {
    stub();
    vi.spyOn(roleManagement, 'assign').mockRejectedValue(new ApiError(428, 'Recent Google verification is required.'));
    vi.spyOn(roleManagement, 'remove').mockRejectedValue(new ApiError(428, 'Recent Google verification is required.'));
    render(<RolesPage />);
    await screen.findByRole('heading', { name: 'Assign teacher' });

    fireEvent.change(screen.getByRole('combobox', { name: /Teacher/ }), { target: { value: '2' } });
    fireEvent.change(screen.getByRole('combobox', { name: /Learner/ }), { target: { value: '3' } });
    fireEvent.click(screen.getByRole('button', { name: 'Assign scope' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('/api/v1/auth/oauth/google/admin?return=%2Froles'));

    assign.mockClear();
    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('/api/v1/auth/oauth/google/admin?return=%2Froles'));
    expect(screen.queryByText(/428|Recent Google verification/)).not.toBeInTheDocument();
  });

  it('keeps a rejected scope in the list and never persists form state outside memory', async () => {
    stub();
    vi.spyOn(roleManagement, 'remove').mockRejectedValue(new ApiError(409, 'Scope changed.'));
    render(<RolesPage />);
    await screen.findByRole('heading', { name: 'Assign teacher' });

    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Scope changed.'));
    expect(screen.getByText('Teacher One → Learner One')).toBeInTheDocument();
    expect(document.body.textContent).not.toMatch(/password|secret/i);
  });
});
