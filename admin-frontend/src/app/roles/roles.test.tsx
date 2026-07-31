/**
 * fix(ui)/#50 – Roles/Teacher Scope page: loading/error state, form accessibility, async feedback
 *
 * Theo checklist Issue #50:
 *  - Form có label/select wrapping pattern
 *  - Async message dùng role="status"
 *  - Scopes list có accessible structure
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import * as api from '@/lib/api';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace: vi.fn() }),
  usePathname: () => '/roles',
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock('@/components/AdminLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}));

vi.mock('@/lib/api');

const mockRoles: api.AdminRole[] = [
  { id: 1, name: 'Learner', slug: 'learner', users_count: 5 },
  { id: 2, name: 'Teacher', slug: 'teacher', users_count: 2 },
  { id: 3, name: 'Admin', slug: 'admin', users_count: 1 },
  { id: 4, name: 'Super Admin', slug: 'super_admin', users_count: 1 },
];

const mockTeacher: api.AdminUser = { id: 10, name: 'Nguyễn Văn A', email: 'teacher@test.com', role: 'teacher' };
const mockLearner: api.AdminUser = { id: 20, name: 'Trần Thị B', email: 'learner@test.com', role: 'learner' };

const emptyPage = { data: [] as api.AdminUser[], meta: { page: 1, per_page: 100, total: 0, last_page: 1 } };
const teacherPage = { data: [mockTeacher], meta: { page: 1, per_page: 100, total: 1, last_page: 1 } };
const learnerPage = { data: [mockLearner], meta: { page: 1, per_page: 100, total: 1, last_page: 1 } };

// Static import ensures mock is applied before component is loaded
import RolesPage from './page';

// waitForData: chờ đến khi page render xong (loading biến mất)
async function waitForData() {
  // Sau khi load xong, "Loading controls" sẽ không còn
  // Dùng "Assign teacher" heading – chỉ xuất hiện khi không loading
  await screen.findByText('Assign teacher');
}

describe('Roles page – loading state', () => {
  beforeEach(() => vi.resetAllMocks());

  it('hiển thị loading khi đang tải', () => {
    vi.mocked(api.roleManagement.roles).mockReturnValue(new Promise(() => {}));
    vi.mocked(api.roleManagement.scopes).mockReturnValue(new Promise(() => {}));
    vi.mocked(api.adminUsers.list).mockReturnValue(new Promise(() => {}));

    render(<RolesPage />);

    expect(screen.getByText(/Loading controls/i)).toBeInTheDocument();
  });
});

describe('Roles page – error state', () => {
  beforeEach(() => vi.resetAllMocks());

  it('hiển thị error message khi API thất bại', async () => {
    vi.mocked(api.roleManagement.roles).mockRejectedValue(new Error('Unauthorized'));
    vi.mocked(api.roleManagement.scopes).mockResolvedValue([]);
    vi.mocked(api.adminUsers.list).mockResolvedValue(emptyPage);

    render(<RolesPage />);

    await screen.findByText(/Unauthorized/i);
  });
});

describe('Roles page – data display', () => {
  beforeEach(() => vi.resetAllMocks());

  it('hiển thị role cards cho mỗi role', async () => {
    vi.mocked(api.roleManagement.roles).mockResolvedValue(mockRoles);
    vi.mocked(api.roleManagement.scopes).mockResolvedValue([]);
    vi.mocked(api.adminUsers.list).mockResolvedValue(emptyPage);

    render(<RolesPage />);

    // "Assign teacher" heading chỉ xuất hiện sau khi data load xong
    await waitForData();

    // Role count cards: mỗi role có p với name duy nhất
    // Dùng getAllByText vì có thể có label trùng tên
    expect(screen.getAllByText('Learner').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Teacher').length).toBeGreaterThan(0);
    expect(screen.getByText('Admin')).toBeInTheDocument();
    expect(screen.getByText('Super Admin')).toBeInTheDocument();
  });

  it('hiển thị "No teacher scopes" khi chưa có assignment', async () => {
    vi.mocked(api.roleManagement.roles).mockResolvedValue(mockRoles);
    vi.mocked(api.roleManagement.scopes).mockResolvedValue([]);
    vi.mocked(api.adminUsers.list).mockResolvedValue(emptyPage);

    render(<RolesPage />);

    await screen.findByText(/No teacher scopes/i);
  });
});

describe('Role assign form – accessibility', () => {
  beforeEach(() => vi.resetAllMocks());

  it('form Assign teacher có label cho mỗi select', async () => {
    vi.mocked(api.roleManagement.roles).mockResolvedValue(mockRoles);
    vi.mocked(api.roleManagement.scopes).mockResolvedValue([]);
    vi.mocked(api.adminUsers.list).mockImplementation(async (params) => {
      if (params?.role === 'teacher') return teacherPage;
      if (params?.role === 'learner') return learnerPage;
      return emptyPage;
    });

    render(<RolesPage />);

    await waitForData();

    // Form phải có label cho Teacher select
    const teacherLabel = screen.getAllByText(/^Teacher$/)[0];
    expect(teacherLabel).toBeInTheDocument();
    // Form phải có label cho Learner select
    const learnerLabel = screen.getAllByText(/^Learner$/)[0];
    expect(learnerLabel).toBeInTheDocument();

    // Các select có thể focus
    const selects = screen.getAllByRole('combobox');
    expect(selects.length).toBeGreaterThanOrEqual(2);
  });

  it('button Assign scope có thể submit form', async () => {
    vi.mocked(api.roleManagement.roles).mockResolvedValue(mockRoles);
    vi.mocked(api.roleManagement.scopes).mockResolvedValue([]);
    vi.mocked(api.adminUsers.list).mockImplementation(async (params) => {
      if (params?.role === 'teacher') return teacherPage;
      if (params?.role === 'learner') return learnerPage;
      return emptyPage;
    });
    vi.mocked(api.roleManagement.assign).mockResolvedValue({
      id: 1, teacher: mockTeacher, learner: mockLearner,
      assigned_at: new Date().toISOString(),
    });

    render(<RolesPage />);
    await waitForData();

    const assignBtn = screen.getByRole('button', { name: /Assign scope/i });
    expect(assignBtn).toBeInTheDocument();
  });
});

describe('Role assign – async feedback accessibility', () => {
  beforeEach(() => vi.resetAllMocks());

  it('thông báo kết quả assign dùng role="status"', async () => {
    vi.mocked(api.roleManagement.roles).mockResolvedValue(mockRoles);
    vi.mocked(api.roleManagement.scopes).mockResolvedValue([]);
    vi.mocked(api.adminUsers.list).mockImplementation(async (params) => {
      if (params?.role === 'teacher') return teacherPage;
      if (params?.role === 'learner') return learnerPage;
      return emptyPage;
    });
    vi.mocked(api.roleManagement.assign).mockResolvedValue({
      id: 1, teacher: mockTeacher, learner: mockLearner,
      assigned_at: new Date().toISOString(),
    });

    render(<RolesPage />);
    await waitForData();

    // Chọn teacher và learner
    const selects = screen.getAllByRole('combobox');
    await userEvent.selectOptions(selects[0], String(mockTeacher.id));
    await userEvent.selectOptions(selects[1], String(mockLearner.id));

    const assignBtn = screen.getByRole('button', { name: /Assign scope/i });
    await userEvent.click(assignBtn);

    const statusEl = await screen.findByRole('status');
    expect(statusEl.textContent).toMatch(/Teacher scope assigned/i);
  });
});

describe('Teacher scope removal – async feedback', () => {
  beforeEach(() => vi.resetAllMocks());

  it('thông báo kết quả remove dùng role="status"', async () => {
    const activeScope: api.TeacherScope = {
      id: 99, teacher: mockTeacher, learner: mockLearner,
      assigned_at: new Date().toISOString(),
    };

    vi.mocked(api.roleManagement.roles).mockResolvedValue(mockRoles);
    vi.mocked(api.roleManagement.scopes).mockResolvedValue([activeScope]);
    vi.mocked(api.adminUsers.list).mockResolvedValue(emptyPage);
    vi.mocked(api.roleManagement.remove).mockResolvedValue(undefined);

    render(<RolesPage />);
    await screen.findByText(/Nguyễn Văn A → Trần Thị B/);

    const removeBtn = screen.getByRole('button', { name: /Remove/i });
    await userEvent.click(removeBtn);

    const statusEl = await screen.findByRole('status');
    expect(statusEl.textContent).toMatch(/Teacher scope removed/i);
  });
});
