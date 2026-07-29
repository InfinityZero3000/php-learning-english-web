import { beforeEach, describe, expect, it, vi } from 'vitest';
import { adminUnits, ApiError, auth } from './api';

describe('admin session API client', () => {
  beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=admin%20token';
    vi.restoreAllMocks();
  });

  it('initializes CSRF and sends session credentials', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ data: { id: 1, role: 'admin' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }));

    await auth.login('admin@example.com', 'password');
    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/v1/auth/login', expect.objectContaining({
      credentials: 'include',
      headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'admin token' }),
    }));
  });

  it('parses Laravel errors', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Unauthorized' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      }));
    await expect(auth.login('admin@example.com', 'bad')).rejects.toMatchObject({ status: 401, message: 'Unauthorized' } as Partial<ApiError>);
  });

  it('sends the collision-safe unit reorder contract', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }));

    await adminUnits.reorder(9, [4, 2]);
    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/v1/admin/catalog/units/reorder', expect.objectContaining({
      method: 'PUT',
      headers: expect.objectContaining({ 'X-Request-ID': expect.any(String) }),
      body: JSON.stringify({ course_id: 9, unit_ids: [4, 2] }),
    }));
  });
});
