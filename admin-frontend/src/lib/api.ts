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

export const operations = {
  overview: () => request<{ data: OperationsOverview }>('/api/v1/admin/operations').then(({ data }) => data),
  probe: (service: string) => request<{ data: ServiceProbe }>('/api/v1/admin/operations/service-probes', {
    method: 'POST',
    body: JSON.stringify({ service }),
  }).then(({ data }) => data),
  contracts: () => request<{ data: ContractStatus }>('/api/v1/admin/operations/contracts').then(({ data }) => data),
  usage: () => request<{ data: OperationsUsage }>('/api/v1/admin/operations/usage').then(({ data }) => data),
  quota: () => request<{ data: QuotaPolicy }>('/api/v1/admin/operations/quota-policy').then(({ data }) => data),
  updateQuota: (limits: Record<string, number>) =>
    request<{ data: QuotaPolicy }>('/api/v1/admin/operations/quota-policy', {
      method: 'PUT',
      headers: { 'X-Request-ID': crypto.randomUUID() },
      body: JSON.stringify({ limits }),
    }).then(({ data }) => data),
  rules: () => request<{ data: AlertRule[] }>('/api/v1/admin/operations/alert-rules').then(({ data }) => data),
  updateRule: (id: number, enabled: boolean, parameters: Record<string, unknown>) =>
    request<{ data: AlertRule }>(`/api/v1/admin/operations/alert-rules/${id}`, {
      method: 'PUT',
      headers: { 'X-Request-ID': crypto.randomUUID() },
      body: JSON.stringify({ enabled, parameters }),
    }).then(({ data }) => data),
  audits: () => request<{ data: AuditEvent[] }>('/api/v1/admin/operations/audit-events').then(({ data }) => data),
};

export type OperationsOverview = { features: Record<string, boolean>; services: Record<string, boolean>; open_alerts: number };
export type ServiceProbe = { service: string; healthy: boolean; status: number; latency_ms: number };
export type QuotaPolicy = { id: number; version: number; limits: Record<string, number>; is_active: boolean };
export type AlertRule = { id: number; rule_key: string; version: number; enabled: boolean; parameters: Record<string, unknown> };
export type AuditEvent = { id: number; action: string; target_type?: string; target_id?: string; occurred_at: string };
export type ContractStatus = { trace_cag: { version: string; sha256: string | null } };
export type OperationsUsage = { last_24_hours: number; last_30_days: number; degraded_30_days: number };

// Auth
let adminSession: Promise<User> | undefined;

export const auth = {
  me: () => request<{ data: User }>('/api/v1/auth/me').then(({ data }) => data),
  adminMe: () => {
    adminSession ??= request<{ data: User }>('/api/v1/admin/session')
      .then(({ data }) => data)
      .catch((error) => {
        adminSession = undefined;
        throw error;
      });
    return adminSession;
  },
  completeGoogleAdmin: (handoff: string) =>
    request<{ data: { user: User; return: string } }>('/api/v1/auth/oauth/google/admin/handoff', {
      method: 'POST',
      body: JSON.stringify({ handoff }),
    }).then(({ data }) => data),
  logout: () => request<void>('/api/v1/auth/logout', { method: 'POST' }).finally(() => { adminSession = undefined; }),
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
  list: (params?: { search?: string; role?: string; page?: number; perPage?: number }) => {
    const q = new URLSearchParams();
    if (params?.search) q.set('search', params.search);
    if (params?.role) q.set('role', params.role);
    if (params?.page != null) q.set('page', String(params.page));
    if (params?.perPage != null) q.set('per_page', String(params.perPage));
    return request<{ data: AdminUser[]; meta: PageMeta }>(`/api/v1/admin/users?${q}`);
  },
  get: (id: number) => request<{ data: AdminUser }>(`/api/v1/admin/users/${id}`).then(({ data }) => data),
  assignRole: (id: number, role: string) =>
    request<{ data: AdminUser }>(`/api/v1/admin/users/${id}/role`, {
      method: 'PUT', headers: { 'X-Request-ID': crypto.randomUUID() }, body: JSON.stringify({ role })
    }).then(({ data }) => data),
};

export const roleManagement = {
  roles: () => request<{ data: AdminRole[] }>('/api/v1/admin/roles').then(({ data }) => data),
  scopes: () => request<{ data: TeacherScope[] }>('/api/v1/admin/operations/teacher-assignments').then(({ data }) => data),
  assign: (teacherId: number, learnerId: number) =>
    request<{ data: TeacherScope }>('/api/v1/admin/operations/teacher-assignments', {
      method: 'POST', headers: { 'X-Request-ID': crypto.randomUUID() }, body: JSON.stringify({ teacher_id: teacherId, learner_id: learnerId })
    }).then(({ data }) => data),
  remove: (id: number) => request<void>(`/api/v1/admin/operations/teacher-assignments/${id}`, {
    method: 'DELETE', headers: { 'X-Request-ID': crypto.randomUUID() }
  }),
};

export type PageMeta = { page: number; per_page: number; total: number; last_page: number };
export type AdminUser = { id: number; name: string; email: string; role: 'learner' | 'teacher' | 'admin' | 'super_admin'; email_verified_at?: string; created_at?: string };
export type AdminRole = { id: number; name: string; slug: AdminUser['role']; users_count: number };
export type TeacherScope = { id: number; teacher: Pick<AdminUser, 'id' | 'name' | 'email'>; learner: Pick<AdminUser, 'id' | 'name' | 'email'>; assigned_at: string };

export const adminCourses = {
  list: (search = '') => request<{ data: AdminCourse[]; meta: PageMeta }>(`/api/v1/admin/catalog/courses?search=${encodeURIComponent(search)}&per_page=100`),
  create: (payload: CourseWrite) => request<{ data: AdminCourse }>('/api/v1/admin/catalog/courses', {
    method: 'POST', headers: { 'X-Request-ID': crypto.randomUUID() }, body: JSON.stringify(payload)
  }).then(({ data }) => data),
  update: (id: number, payload: CourseWrite) => request<{ data: AdminCourse }>(`/api/v1/admin/catalog/courses/${id}`, {
    method: 'PUT', headers: { 'X-Request-ID': crypto.randomUUID() }, body: JSON.stringify(payload)
  }).then(({ data }) => data),
};

export type AdminCourse = { id: number; title: string; slug: string; description?: string; status: 'draft' | 'published' | 'archived'; language?: string; estimated_duration?: number; units_count?: number; lessons_count?: number };
export type CourseWrite = Pick<AdminCourse, 'title' | 'slug' | 'status'> & Partial<Pick<AdminCourse, 'description' | 'language' | 'estimated_duration'>>;
