import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OperationsPage from './page';
import { ApiError, operations, type AlertRule, type QuotaPolicy } from '@/lib/api';

vi.mock('@/components/AdminLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

const assign = vi.fn();
const quota = { id: 1, version: 3, limits: { trace_cag_daily: 100 }, is_active: true } satisfies QuotaPolicy;
const rule = { id: 5, rule_key: 'inactivity', version: 1, enabled: true, parameters: {} } satisfies AlertRule;

function stub() {
  vi.spyOn(operations, 'overview').mockResolvedValue({ features: {}, services: { backend: true }, open_alerts: 0 });
  vi.spyOn(operations, 'usage').mockResolvedValue({ last_24_hours: 1, last_30_days: 2, degraded_30_days: 0 });
  vi.spyOn(operations, 'contracts').mockResolvedValue({ trace_cag: { version: '1', sha256: null } });
  vi.spyOn(operations, 'quota').mockResolvedValue(quota);
  vi.spyOn(operations, 'rules').mockResolvedValue([rule]);
  vi.spyOn(operations, 'audits').mockResolvedValue({ data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } });
}

describe('operations control room', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    assign.mockReset();
    Object.defineProperty(window, 'location', { configurable: true, value: { ...window.location, assign } });
    stub();
  });

  it('versions a quota policy and reports validation failures in place', async () => {
    const update = vi.spyOn(operations, 'updateQuota').mockResolvedValue({ ...quota, version: 4 });
    render(<OperationsPage />);
    const limits = await screen.findByRole('textbox', { name: 'Quota limits JSON' });

    fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));
    await waitFor(() => expect(update).toHaveBeenCalledWith({ trace_cag_daily: 100 }));
    expect(await screen.findByText('Đã kích hoạt quota policy.')).toBeInTheDocument();

    fireEvent.change(limits, { target: { value: '{"trace_cag_daily": -1}' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));
    await waitFor(() => expect(screen.getAllByRole('status').at(-1)).toHaveTextContent('Quota phải là số nguyên không âm.'));
    expect(update).toHaveBeenCalledTimes(1);
  });

  it('redirects quota and alert-rule 428 responses to the Google handoff', async () => {
    vi.spyOn(operations, 'updateQuota').mockRejectedValue(new ApiError(428, 'Recent Google verification is required.'));
    vi.spyOn(operations, 'updateRule').mockRejectedValue(new ApiError(428, 'Recent Google verification is required.'));
    render(<OperationsPage />);
    await screen.findByRole('textbox', { name: 'Quota limits JSON' });

    fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('/api/v1/auth/oauth/google/admin?return=%2Foperations'));

    assign.mockClear();
    fireEvent.click(screen.getByRole('button', { name: 'Enabled' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('/api/v1/auth/oauth/google/admin?return=%2Foperations'));
    expect(screen.queryByText(/428|Recent Google verification/)).not.toBeInTheDocument();
  });

  it('shows a 403 as a message and never exposes secrets', async () => {
    vi.spyOn(operations, 'updateRule').mockRejectedValue(new ApiError(403, 'This action is unauthorized.'));
    render(<OperationsPage />);
    await screen.findByRole('textbox', { name: 'Quota limits JSON' });

    fireEvent.click(screen.getByRole('button', { name: 'Enabled' }));
    await waitFor(() => expect(screen.getAllByRole('status').at(-1)).toHaveTextContent('This action is unauthorized.'));
    expect(assign).not.toHaveBeenCalled();
    expect(document.body.textContent).not.toMatch(/password|api key|partner/i);
  });
});
