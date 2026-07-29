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
      ...(options?.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
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
  audits: (params: { action?: string; search?: string; from?: string; to?: string; page?: number; perPage?: number } = {}) => {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => { if (value !== undefined && value !== '') q.set(key === 'perPage' ? 'per_page' : key, String(value)); });
    return request<{ data: AuditEvent[]; meta: PageMeta }>(`/api/v1/admin/operations/audit-events?${q}`);
  },
};

export type OperationsOverview = { features: Record<string, boolean>; services: Record<string, boolean>; open_alerts: number };
export type ServiceProbe = { service: string; healthy: boolean; status: number; latency_ms: number };
export type QuotaPolicy = { id: number; version: number; limits: Record<string, number>; is_active: boolean };
export type AlertRule = { id: number; rule_key: string; version: number; enabled: boolean; parameters: Record<string, unknown> };
export type AuditEvent = { id: number; action: string; target_type?: string | null; target_id?: string | null; actor?: Pick<AdminUser, 'id' | 'name' | 'email'> | null; occurred_at: string };
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
  evidence: (id: number, reason: string) =>
    request<{ data: LearningEvidence[] }>(`/api/v1/admin/users/${id}/evidence`, {
      method: 'POST', headers: { 'X-Request-ID': crypto.randomUUID() }, body: JSON.stringify({ reason }),
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
export type LearningEvidence = { id: number; event_type: string; response?: string | null; is_correct?: boolean | null; hint_level?: number | null; pronunciation_score?: number | null; duration_ms?: number | null; occurred_at: string; metadata?: Record<string, unknown> | null };

export type AdminSummary = { courses: number; levels: number; topics: number; vocabularies: number; decks: number };
export type AdminLevel = { id: number; name: string; slug: string; sort_order?: number; courses_count?: number };
export type AdminTopic = { id: number; name: string; slug: string; courses_count?: number; vocabularies_count?: number };
export type AdminVocabulary = {
  id: number; word: string; meaning: string; definition?: string | null; example?: string | null;
  topic_id?: number | null; topic?: Pick<AdminTopic, 'id' | 'name'> | null; pronunciation?: string | null;
  part_of_speech?: string | null; difficulty_level?: string | null; tags?: string[] | null;
  translation?: Record<string, string> | null; decks_count?: number; image_url?: string | null; audio_url?: string | null;
};
export type VocabularyDeck = { id: number; name: string; slug: string; description?: string | null; is_public: boolean; vocabularies_count?: number; vocabulary_ids?: number[] };
export type DateBucket = { date: string; count: number };
export type CourseAggregate = { course_id: number; course_title: string; attempts?: number; average_score?: number | null; completed_lessons?: number };
export type AdminLearningOverview = { type: 'learning_overview'; enrollments: number; completed_enrollments: number; completion_rate: number; sessions_started: number; sessions_completed: number; quiz_attempts: number; average_score: number | null; average_duration_ms: number | null; correct_rate: number | null };
export type AdminQuizAnalytics = { type: 'quiz_analytics'; attempts: number; by_course: CourseAggregate[]; by_date: DateBucket[] };
export type AdminFsrsAnalytics = { type: 'fsrs_analytics'; reviews: number; average_stability: number | null; average_difficulty: number | null; state_bands: Array<{ state: string; count: number }>; by_date: DateBucket[] };
export type AdminProgressAnalytics = { type: 'progress_analytics'; completed_lessons: number; by_course: CourseAggregate[]; by_date: DateBucket[] };
export type AdminImportCheckpoint = { entity: AdminImportEntity; cursor: number; last_synced_at: string | null; failures: number };
export type AdminImportEntity = 'categories' | 'courses' | 'vocabulary';
export type AdminImportRun = {
  id: string; request_id: string; entity: AdminImportEntity; status: 'pending' | 'running' | 'succeeded' | 'failed';
  requested_limit: number; reset: boolean; starting_cursor: number; processed: number | null; skipped: number | null;
  result_cursor: number | null; error_code: string | null; error_message: string | null; created_at: string; updated_at: string;
};
export type AdminNotification = { id: number; type: string; severity: string; state: string; summary: string; created_at: string | null; resolved_at: string | null; read: boolean };
export type AdminPreferences = { notifications: { operational: boolean }; ui: { compact_sidebar?: boolean } };
export type ContentFeedItem = { id?: string; title?: string; description?: string; thumbnail?: string; channelTitle?: string; publishedAt?: string; url?: string; source?: { name?: string }; urlToImage?: string };
export type LevelWrite = Pick<AdminLevel, 'name' | 'slug'> & Partial<Pick<AdminLevel, 'sort_order'>>;
export type TopicWrite = Pick<AdminTopic, 'name' | 'slug'>;
export type VocabularyWrite = Pick<AdminVocabulary, 'word' | 'meaning'> & Partial<Omit<AdminVocabulary, 'id' | 'word' | 'meaning' | 'topic' | 'decks_count'>>;
export type DeckWrite = Pick<VocabularyDeck, 'name' | 'slug'> & Partial<Pick<VocabularyDeck, 'description' | 'is_public' | 'vocabulary_ids'>>;

const query = (values: Record<string, string | number | undefined>) => {
  const params = new URLSearchParams();
  Object.entries(values).forEach(([key, value]) => { if (value !== undefined && value !== '') params.set(key, String(value)); });
  return params.toString();
};

const mutation = <T>(path: string, method: 'POST' | 'PUT' | 'DELETE', body?: unknown) => request<T>(path, {
  method,
  headers: { 'X-Request-ID': crypto.randomUUID() },
  ...(body === undefined ? {} : { body: JSON.stringify(body) }),
});

export const adminCatalog = {
  summary: () => request<{ data: AdminSummary }>('/api/v1/admin/catalog/summary').then(({ data }) => data),
  levels: (params: { search?: string; page?: number; perPage?: number } = {}) => request<{ data: AdminLevel[]; meta: PageMeta }>(`/api/v1/admin/catalog/levels?${query({ search: params.search, page: params.page, per_page: params.perPage })}`),
  createLevel: (data: LevelWrite) => mutation<{ data: AdminLevel }>('/api/v1/admin/catalog/levels', 'POST', data).then(({ data }) => data),
  updateLevel: (id: number, data: LevelWrite) => mutation<{ data: AdminLevel }>(`/api/v1/admin/catalog/levels/${id}`, 'PUT', data).then(({ data }) => data),
  deleteLevel: (id: number) => mutation<void>(`/api/v1/admin/catalog/levels/${id}`, 'DELETE'),
  topics: (params: { search?: string; page?: number; perPage?: number } = {}) => request<{ data: AdminTopic[]; meta: PageMeta }>(`/api/v1/admin/catalog/topics?${query({ search: params.search, page: params.page, per_page: params.perPage })}`),
  createTopic: (data: TopicWrite) => mutation<{ data: AdminTopic }>('/api/v1/admin/catalog/topics', 'POST', data).then(({ data }) => data),
  updateTopic: (id: number, data: TopicWrite) => mutation<{ data: AdminTopic }>(`/api/v1/admin/catalog/topics/${id}`, 'PUT', data).then(({ data }) => data),
  deleteTopic: (id: number) => mutation<void>(`/api/v1/admin/catalog/topics/${id}`, 'DELETE'),
  vocabularies: (params: { search?: string; page?: number; perPage?: number } = {}) => request<{ data: AdminVocabulary[]; meta: PageMeta }>(`/api/v1/admin/catalog/vocabularies?${query({ search: params.search, page: params.page, per_page: params.perPage })}`),
  vocabulary: (id: number) => request<{ data: AdminVocabulary }>(`/api/v1/admin/catalog/vocabularies/${id}`).then(({ data }) => data),
  createVocabulary: (data: VocabularyWrite) => mutation<{ data: AdminVocabulary }>('/api/v1/admin/catalog/vocabularies', 'POST', data).then(({ data }) => data),
  updateVocabulary: (id: number, data: VocabularyWrite) => mutation<{ data: AdminVocabulary }>(`/api/v1/admin/catalog/vocabularies/${id}`, 'PUT', data).then(({ data }) => data),
  deleteVocabulary: (id: number) => mutation<void>(`/api/v1/admin/catalog/vocabularies/${id}`, 'DELETE'),
  uploadVocabularyMedia: (id: number, body: FormData) => request<{ data: AdminVocabulary }>(`/api/v1/admin/catalog/vocabularies/${id}/media`, {
    method: 'POST', headers: { 'X-Request-ID': crypto.randomUUID() }, body,
  }).then(({ data }) => data),
  decks: (params: { search?: string; page?: number; perPage?: number } = {}) => request<{ data: VocabularyDeck[]; meta: PageMeta }>(`/api/v1/admin/catalog/decks?${query({ search: params.search, page: params.page, per_page: params.perPage })}`),
  createDeck: (data: DeckWrite) => mutation<{ data: VocabularyDeck }>('/api/v1/admin/catalog/decks', 'POST', data).then(({ data }) => data),
  updateDeck: (id: number, data: DeckWrite) => mutation<{ data: VocabularyDeck }>(`/api/v1/admin/catalog/decks/${id}`, 'PUT', data).then(({ data }) => data),
  deleteDeck: (id: number) => mutation<void>(`/api/v1/admin/catalog/decks/${id}`, 'DELETE'),
};

export const adminLearning = {
  overview: (from?: string, to?: string) => request<{ data: AdminLearningOverview }>(`/api/v1/admin/learning/overview?${query({ from, to })}`).then(({ data }) => data),
  quizzes: (from?: string, to?: string) => request<{ data: AdminQuizAnalytics }>(`/api/v1/admin/learning/quizzes?${query({ from, to })}`).then(({ data }) => data),
  fsrs: (from?: string, to?: string) => request<{ data: AdminFsrsAnalytics }>(`/api/v1/admin/learning/fsrs?${query({ from, to })}`).then(({ data }) => data),
  progress: (from?: string, to?: string) => request<{ data: AdminProgressAnalytics }>(`/api/v1/admin/learning/progress?${query({ from, to })}`).then(({ data }) => data),
  reportUrl: (from?: string, to?: string) => `/api/v1/admin/learning/reports.csv?${query({ from, to })}`,
  report: async (from?: string, to?: string) => {
    const response = await fetch(adminLearning.reportUrl(from, to), { credentials: 'include', headers: { Accept: 'text/csv' } });
    if (!response.ok) throw new ApiError(response.status, response.statusText || `HTTP ${response.status}`);
    return response.blob();
  },
};

export const adminImports = {
  checkpoints: () => request<{ data: AdminImportCheckpoint[] }>('/api/v1/admin/imports').then(({ data }) => data),
  run: (id: string) => request<{ data: AdminImportRun }>(`/api/v1/admin/imports/runs/${id}`).then(({ data }) => data),
  start: (entity: AdminImportEntity, limit: number) => mutation<{ data: AdminImportRun }>('/api/v1/admin/imports', 'POST', { entity, limit }).then(({ data }) => data),
  resume: (entity: AdminImportEntity, limit: number) => mutation<{ data: AdminImportRun }>('/api/v1/admin/imports', 'POST', { entity, limit }).then(({ data }) => data),
  reset: (entity: AdminImportEntity, limit = 100) => mutation<{ data: AdminImportRun }>('/api/v1/admin/imports/reset', 'POST', { entity, limit }).then(({ data }) => data),
};

export const adminFeed = {
  search: (source: 'youtube' | 'news', q = '') => request<{ data: ContentFeedItem[] }>(`/api/v1/admin/content-feed?${query({ source, q })}`).then(({ data }) => data),
};

export const adminNotifications = {
  list: () => request<{ data: AdminNotification[] }>('/api/v1/admin/notifications').then(({ data }) => data),
  markRead: (id: number) => mutation<{ data: { id: number; read: true } }>(`/api/v1/admin/notifications/${id}/read`, 'POST').then(({ data }) => data),
};

export const adminPreferences = {
  get: () => request<{ data: AdminPreferences }>('/api/v1/admin/preferences').then(({ data }) => data),
  update: (data: AdminPreferences) => mutation<{ data: AdminPreferences }>('/api/v1/admin/preferences', 'PUT', data).then(({ data }) => data),
};

export const adminCourses = {
  list: (params: { search?: string; status?: string; levelId?: number; page?: number; perPage?: number } = {}) => request<{ data: AdminCourse[]; meta: PageMeta }>(`/api/v1/admin/catalog/courses?${query({ search: params.search, status: params.status, level_id: params.levelId, page: params.page, per_page: params.perPage ?? 100 })}`),
  get: (id: number) => request<{ data: AdminCourse }>(`/api/v1/admin/catalog/courses/${id}`).then(({ data }) => data),
  create: (payload: CourseWrite) => request<{ data: AdminCourse }>('/api/v1/admin/catalog/courses', {
    method: 'POST', headers: { 'X-Request-ID': crypto.randomUUID() }, body: JSON.stringify(payload)
  }).then(({ data }) => data),
  update: (id: number, payload: CourseWrite) => request<{ data: AdminCourse }>(`/api/v1/admin/catalog/courses/${id}`, {
    method: 'PUT', headers: { 'X-Request-ID': crypto.randomUUID() }, body: JSON.stringify(payload)
  }).then(({ data }) => data),
  publish: (id: number) => mutation<{ data: AdminCourse }>(`/api/v1/admin/catalog/courses/${id}/publish`, 'POST').then(({ data }) => data),
  archive: (id: number) => mutation<{ data: AdminCourse }>(`/api/v1/admin/catalog/courses/${id}/archive`, 'POST').then(({ data }) => data),
  delete: (id: number) => mutation<void>(`/api/v1/admin/catalog/courses/${id}`, 'DELETE'),
};

export type AdminCourse = { id: number; title: string; slug: string; description?: string; status: 'draft' | 'published' | 'archived'; language?: string; estimated_duration?: number; units_count?: number; lessons_count?: number; level?: AdminLevel | null; topics?: AdminTopic[] };
export type CourseWrite = Pick<AdminCourse, 'title' | 'slug' | 'status'> & Partial<Pick<AdminCourse, 'description' | 'language' | 'estimated_duration'>> & { level_id?: number | null; topic_ids?: number[] };

export type AdminLesson = { id: number; course: Pick<AdminCourse, 'id' | 'title'>; title: string; slug: string; content?: string | null; sort_order: number; estimated_minutes?: number | null; status: AdminCourse['status']; vocabularies_count: number; quizzes_count: number; quizzes?: Array<{ id: number; title: string; status: string; passing_score: number }> };
export type LessonWrite = { course_id: number; title: string; slug: string; content?: string | null; sort_order: number; estimated_minutes?: number | null; status: AdminCourse['status'] };
export const adminLessons = {
  list: (params: { search?: string; courseId?: number; status?: string; page?: number; perPage?: number } = {}) => request<{ data: AdminLesson[]; meta: PageMeta }>(`/api/v1/admin/catalog/lessons?${query({ search: params.search, course_id: params.courseId, status: params.status, page: params.page, per_page: params.perPage })}`),
  get: (id: number) => request<{ data: AdminLesson }>(`/api/v1/admin/catalog/lessons/${id}`).then(({ data }) => data),
  create: (data: LessonWrite) => mutation<{ data: AdminLesson }>('/api/v1/admin/catalog/lessons', 'POST', data).then(({ data }) => data),
  update: (id: number, data: LessonWrite) => mutation<{ data: AdminLesson }>(`/api/v1/admin/catalog/lessons/${id}`, 'PUT', data).then(({ data }) => data),
  publish: (id: number) => mutation<{ data: AdminLesson }>(`/api/v1/admin/catalog/lessons/${id}/publish`, 'POST').then(({ data }) => data),
  archive: (id: number) => mutation<{ data: AdminLesson }>(`/api/v1/admin/catalog/lessons/${id}/archive`, 'POST').then(({ data }) => data),
  delete: (id: number) => mutation<void>(`/api/v1/admin/catalog/lessons/${id}`, 'DELETE'),
};

export type QuizAnswerWrite = { id?: number; content: string; is_correct: boolean };
export type QuizQuestionWrite = { id?: number; content: string; explanation?: string | null; answers: QuizAnswerWrite[] };
export type QuizWrite = { lesson_id: number; title: string; passing_score: number; status: AdminCourse['status']; questions: QuizQuestionWrite[] };
export type AdminQuiz = QuizWrite & { id: number; lesson: { id: number; title: string }; questions_count?: number; attempts_count?: number };
export const adminQuizzes = {
  list: (params: { search?: string; lessonId?: number; status?: string; page?: number; perPage?: number } = {}) => request<{ data: AdminQuiz[]; meta: PageMeta }>(`/api/v1/admin/catalog/quizzes?${query({ search: params.search, lesson_id: params.lessonId, status: params.status, page: params.page, per_page: params.perPage })}`),
  get: (id: number) => request<{ data: AdminQuiz }>(`/api/v1/admin/catalog/quizzes/${id}`).then(({ data }) => data),
  create: (data: QuizWrite) => mutation<{ data: AdminQuiz }>('/api/v1/admin/catalog/quizzes', 'POST', data).then(({ data }) => data),
  update: (id: number, data: QuizWrite) => mutation<{ data: AdminQuiz }>(`/api/v1/admin/catalog/quizzes/${id}`, 'PUT', data).then(({ data }) => data),
  delete: (id: number) => mutation<void>(`/api/v1/admin/catalog/quizzes/${id}`, 'DELETE'),
};
