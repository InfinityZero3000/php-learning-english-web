export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  role: string | null;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

function xsrfToken() {
  if (typeof document === 'undefined') return null;
  const token = document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
    ?.slice('XSRF-TOKEN='.length);
  return token ? decodeURIComponent(token) : null;
}

async function request<T>(path: string, options?: RequestInit): Promise<T> {
  const method = options?.method?.toUpperCase() ?? 'GET';
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    const csrf = await fetch('/api/v1/csrf-cookie', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
    if (!csrf.ok) throw new ApiError(csrf.status, 'Could not initialize a secure session.');
  }

  const token = xsrfToken();
  const res = await fetch(path, {
    ...options,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options?.body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { 'X-XSRF-TOKEN': token } : {}),
      ...options?.headers,
    },
  });
  if (!res.ok) {
    const body = await res.json().catch(() => null) as {
      message?: string;
      errors?: Record<string, string[]>;
    } | null;
    throw new ApiError(res.status, body?.message || res.statusText || `HTTP ${res.status}`, body?.errors);
  }
  if (res.status === 204) return undefined as T;
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) return res.json();
  return res.text() as unknown as T;
}

// Auth
export const auth = {
  login: (email: string, password: string) =>
    request<{ data: User }>('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }).then(({ data }) => data),
  me: () => request<{ data: User }>('/api/v1/auth/me').then(({ data }) => data),
  logout: () => request<void>('/api/v1/auth/logout', { method: 'POST' }),
};

// Words
export const words = {
  list: (params?: { page?: number; size?: number; category?: string; search?: string; difficulty?: string }) => {
    const q = new URLSearchParams();
    if (params?.page != null) q.set('page', String(params.page));
    if (params?.size != null) q.set('size', String(params.size));
    if (params?.category) q.set('category', params.category);
    if (params?.search) q.set('search', params.search);
    if (params?.difficulty) q.set('difficulty', params.difficulty);
    return request(`/api/words?${q}`);
  },
  get: (id: number) => request(`/api/words/${id}`),
  count: () => request<number>('/api/words/count'),
  categories: () => request<string[]>('/api/words/categories'),
  partsOfSpeech: () => request<string[]>('/api/words/parts-of-speech'),
  create: (data: unknown) =>
    request('/api/words', { method: 'POST', body: JSON.stringify(data) }),
  update: (id: number, data: unknown) =>
    request(`/api/words/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  delete: (id: number) =>
    request(`/api/words/${id}`, { method: 'DELETE' }),
  random: () => request('/api/words/random'),
};

// Vocabulary Sets
export const sets = {
  list: () => request('/api/sets'),
  get: (id: number) => request(`/api/sets/${id}`),
  create: (data: unknown) =>
    request('/api/sets', { method: 'POST', body: JSON.stringify(data) }),
  delete: (id: number) =>
    request(`/api/sets/${id}`, { method: 'DELETE' }),
  words: (id: number) => request(`/api/sets/${id}/words`),
  addWord: (setId: number, wordId: number) =>
    request(`/api/sets/${setId}/words/${wordId}`, { method: 'POST' }),
  removeWord: (setId: number, wordId: number) =>
    request(`/api/sets/${setId}/words/${wordId}`, { method: 'DELETE' }),
};

// FSRS
export const fsrs = {
  due: () => request('/api/fsrs/due'),
  stats: () => request('/api/fsrs/stats'),
  review: (data: unknown) =>
    request('/api/fsrs/review', { method: 'POST', body: JSON.stringify(data) }),
};

// Progress
export const progress = {
  all: () => request('/api/progress'),
  stats: () => request('/api/progress/stats'),
  review: () => request('/api/progress/review'),
};

// Quiz
export const quiz = {
  stats: () => request('/api/quiz/stats'),
  recentSessions: () => request('/api/quiz/sessions/recent'),
};

// Streak
export const streak = {
  get: () => request('/api/streak'),
  checkIn: () => request('/api/streak/check-in', { method: 'POST' }),
};

// Notifications
export const notifications = {
  list: () => request('/api/notifications'),
  unreadCount: async () => {
    const res = await request('/api/notifications/unread-count');
    if (typeof res === 'number') return res;
    if (res && typeof res === 'object' && 'unread' in res && typeof res.unread === 'number') return res.unread;
    return 0;
  },
  markRead: (id: number) =>
    request(`/api/notifications/${id}/read`, { method: 'POST' }),
  settings: () => request('/api/notifications/settings'),
  updateSettings: (data: unknown) =>
    request('/api/notifications/settings', { method: 'PUT', body: JSON.stringify(data) }),
};

// Import
export const importJobs = {
  list: () => request('/api/import/jobs'),
  get: (id: string) => request(`/api/import/jobs/${id}`),
  commit: (data: unknown) =>
    request('/api/import/words/commit', { method: 'POST', body: JSON.stringify(data) }),
};

// Content
export const content = {
  youtube: (params?: { q?: string }) => {
    const q = new URLSearchParams();
    if (params?.q) q.set('q', params.q);
    return request(`/api/content/youtube?${q}`);
  },
  news: (params?: { q?: string }) => {
    const q = new URLSearchParams();
    if (params?.q) q.set('q', params.q);
    return request(`/api/content/news?${q}`);
  },
  combined: () => request('/api/content/combined'),
};

// Topics
export const topics = {
  list: () => request('/api/topics'),
  get: (id: number) => request(`/api/topics/${id}`),
  create: (data: unknown) =>
    request('/api/topics', { method: 'POST', body: JSON.stringify(data) }),
  update: (id: number, data: unknown) =>
    request(`/api/topics/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  delete: (id: number) =>
    request(`/api/topics/${id}`, { method: 'DELETE' }),
};

// Word of the day
export const wordOfDay = {
  get: () => request('/api/word-of-the-day'),
};

// Flashcards
export const flashcards = {
  list: () => request('/api/flashcards'),
  sources: () => request('/api/flashcards/sources'),
};

// Admin – User Management
export const adminUsers = {
  list: (params?: { email?: string; status?: string; role?: string; from?: string; to?: string; page?: number; size?: number }) => {
    const q = new URLSearchParams();
    if (params?.email) q.set('email', params.email);
    if (params?.status) q.set('status', params.status);
    if (params?.role) q.set('role', params.role);
    if (params?.from) q.set('from', params.from);
    if (params?.to) q.set('to', params.to);
    if (params?.page != null) q.set('page', String(params.page));
    if (params?.size != null) q.set('size', String(params.size));
    return request(`/api/admin/users?${q}`);
  },
  get: (id: number) => request(`/api/admin/users/${id}`),
  history: (id: number, limit = 20) => request(`/api/admin/users/${id}/history?limit=${limit}`),
  lock: (id: number) => request(`/api/admin/users/${id}/lock`, { method: 'PUT' }),
  unlock: (id: number) => request(`/api/admin/users/${id}/unlock`, { method: 'PUT' }),
  resetPassword: (id: number) =>
    request(`/api/admin/users/${id}/reset-password`, { method: 'POST' }),
  assignRole: (id: number, role: string) =>
    request(`/api/admin/users/${id}/role`, { method: 'PUT', body: JSON.stringify({ role }) }),
};

// Admin – Audit Logs
export const auditLogs = {
  list: () => request('/api/admin/audit-logs'),
};
