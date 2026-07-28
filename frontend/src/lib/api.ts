import type {
  AppUser,
  DictionaryLookup,
  ImportJob,
  ImportRow,
  NotificationItem,
  PageResponse,
  QuizSession,
  ReminderSettings,
  Topic,
  TrustedFlashcard,
  UserProgress,
  Word
} from "@/types/api";
import { initializeCsrf, xsrfToken } from "@/lib/csrf";

type Query = Record<string, string | number | boolean | null | undefined>;

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

function toQuery(query?: Query) {
  const params = new URLSearchParams();
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value));
    }
  });
  const text = params.toString();
  return text ? `?${text}` : "";
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const method = init?.method?.toUpperCase() ?? "GET";
  if (!["GET", "HEAD", "OPTIONS"].includes(method)) await initializeCsrf();
  const token = xsrfToken();
  const response = await fetch(path, {
    ...init,
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { "X-XSRF-TOKEN": token } : {}),
      ...init?.headers
    }
  });

  if (!response.ok) {
    let message = response.statusText || "Request failed";
    let errors: Record<string, string[]> | undefined;
    try {
      const body = await response.json();
      if (body?.message) message = body.message;
      else if (body?.error) message = body.error;
      errors = body?.errors;
    } catch {
      // Keep status text when response is not JSON.
    }
    throw new ApiError(response.status, message, errors);
  }

  if (response.status === 204) return undefined as T;
  return response.json() as Promise<T>;
}

type Envelope<T> = { data: T; meta: Record<string, unknown> };

export function apiRequest<T>(path: string, init?: RequestInit) {
  return request<Envelope<T>>(path, init).then((response) => response.data);
}

export const api = {
  me: () => apiRequest<AppUser>("/api/v1/auth/me"),
  words: (query?: Query) => request<Word[]>(`/api/words${toQuery(query)}`),
  wordsPage: (query?: Query) => request<PageResponse<Word>>(`/api/words${toQuery(query)}`),
  word: (id: number) => request<Word>(`/api/words/${id}`),
  createWord: (word: Partial<Word>) =>
    request<Word>("/api/words", { method: "POST", body: JSON.stringify(word) }),
  updateWord: (id: number, word: Partial<Word>) =>
    request<Word>(`/api/words/${id}`, { method: "PUT", body: JSON.stringify(word) }),
  deleteWord: (id: number) => request<void>(`/api/words/${id}`, { method: "DELETE" }),
  categories: () => request<string[]>("/api/words/categories"),
  partsOfSpeech: () => request<string[]>("/api/words/parts-of-speech"),
  wordCount: () => request<{ count: number }>("/api/words/count"),
  randomWords: (limit = 1) => request<Word[]>(`/api/words/random${toQuery({ limit })}`),
  topics: () => request<Topic[]>("/api/topics"),
  fsrsStats: () => request<Record<string, number>>("/api/fsrs/stats"),
  dueWords: (limit = 20) => request<UserProgress[]>(`/api/fsrs/due${toQuery({ limit })}`),
  reviewWord: (wordId: number, rating: number, responseMs = 0) =>
    request<UserProgress>(`/api/fsrs/review${toQuery({ wordId, rating, responseMs })}`, {
      method: "POST"
    }),
  progress: () => request<UserProgress[]>("/api/progress"),
  progressStats: () => request<Record<string, number>>("/api/progress/stats"),
  reviewQueue: () => request<UserProgress[]>("/api/progress/review"),
  quizStats: () => request<Record<string, number>>("/api/quiz/stats"),
  recentQuizSessions: () => request<QuizSession[]>("/api/quiz/sessions/recent"),
  startQuiz: (query: { count?: number; category?: string; difficulty?: string; topicId?: number; cefrLevel?: string; type?: string }) =>
    request<{ sessionId: number; words: Word[]; totalQuestions: number; distractors: Word[] }>(
      `/api/quiz/start${toQuery(query)}`,
      { method: "POST" }
    ),
  submitQuizAnswer: (sessionId: number, wordId: number, correct: boolean) =>
    request<QuizSession>(`/api/quiz/session/${sessionId}/answer${toQuery({ wordId, correct })}`, {
      method: "POST"
    }),
  completeQuiz: (sessionId: number) =>
    request<QuizSession>(`/api/quiz/session/${sessionId}/complete`, { method: "POST" }),
  streak: () => request<Record<string, number>>("/api/streak"),
  checkInStreak: () => request<Record<string, number>>("/api/streak/check-in", { method: "POST" }),
  notifications: () => request<NotificationItem[]>("/api/notifications"),
  unreadNotifications: () => request<{ unread: number }>("/api/notifications/unread-count"),
  markNotificationRead: (id: number) => request<void>(`/api/notifications/${id}/read`, { method: "POST" }),
  reminderSettings: () => request<ReminderSettings>("/api/notifications/settings"),
  updateReminderSettings: (settings: Partial<ReminderSettings>) =>
    request<ReminderSettings>("/api/notifications/settings", {
      method: "PUT",
      body: JSON.stringify(settings)
    }),
  flashcards: (search?: string) => request<TrustedFlashcard[]>(`/api/flashcards${toQuery({ search })}`),
  importFlashcards: (source: string, topic: string) =>
    request<TrustedFlashcard[]>(`/api/flashcards/import${toQuery({ source, topic })}`, {
      method: "POST"
    }),
  saveFlashcard: (id: number) =>
    request<Word>(`/api/flashcards/${id}/save-to-vocab${toQuery({ difficulty: "BEGINNER", category: "Imported" })}`, {
      method: "POST"
    }),
  enrichWord: (id: number) => request<unknown>(`/api/enrichment/words/${id}`, { method: "POST" }),
  translateRows: (rows: ImportRow[]) =>
    request<{ rows: ImportRow[] }>("/api/enrichment/translate", {
      method: "POST",
      body: JSON.stringify({ rows })
    }),
  lookupDictionary: (word: string) => request<DictionaryLookup>(`/api/dictionary/lookup/${encodeURIComponent(word)}`),
  commitImport: (payload: Record<string, unknown>) =>
    request<Record<string, unknown>>("/api/import/words/commit", {
      method: "POST",
      body: JSON.stringify(payload)
    }),
  importJobs: () => request<ImportJob[]>("/api/import/jobs"),
  learningPlan: () => apiRequest<LearningPlan>("/api/v1/learning/plan"),
  catalogCourses: () => request<Envelope<CourseCard[]> & { meta: { total?: number } }>("/api/v1/catalog/courses"),
  enrollments: () => apiRequest<Enrollment[]>("/api/v1/enrollments"),
  enroll: (courseId: number) => apiRequest<Enrollment>("/api/v1/enrollments", {
    method: "POST", body: JSON.stringify({ course_id: courseId })
  }),
  startSession: (enrollmentId: number) => apiRequest<LearningSession>("/api/v1/learning/sessions", {
    method: "POST",
    headers: { "X-Request-ID": crypto.randomUUID() },
    body: JSON.stringify({ enrollment_id: enrollmentId })
  }),
  nextActivity: (sessionId: number) => apiRequest<LearningSession>(`/api/v1/learning/sessions/${sessionId}/next`),
  submitAttempt: (sessionId: number, payload: LearningAttempt) => apiRequest<{ event_id: number; is_correct: boolean }>(
    `/api/v1/learning/sessions/${sessionId}/attempts`,
    { method: "POST", headers: { "X-Request-ID": crypto.randomUUID() }, body: JSON.stringify(payload) }
  ),
  traceCag: (payload: TraceCagInput) => apiRequest<{ assistance: Assistance }>("/api/v1/ai/trace-cag", {
    method: "POST", headers: { "X-Request-ID": crypto.randomUUID() }, body: JSON.stringify(payload)
  }),
  completeSession: (sessionId: number) => apiRequest<LearningSession>(`/api/v1/learning/sessions/${sessionId}/complete`, {
    method: "POST", headers: { "X-Request-ID": crypto.randomUUID() }
  }),
  fsrsDue: (perPage = 20) => request<Envelope<FsrsCard[]> & { meta: { total: number } }>(`/api/v1/fsrs/due?per_page=${perPage}`),
  fsrsReview: (card: FsrsCard, rating: "again" | "hard" | "good" | "easy") => apiRequest<FsrsCard>("/api/v1/fsrs/review", {
    method: "POST",
    headers: { "X-Request-ID": crypto.randomUUID() },
    body: JSON.stringify({ user_vocabulary_id: card.id, rating, base_revision: card.revision })
  }),
  teacherLearners: () => apiRequest<TeacherLearner[]>("/api/v1/teacher/learners"),
  teacherAlerts: () => apiRequest<SupervisionAlert[]>("/api/v1/teacher/alerts"),
  resolveAlert: (id: number, resolutionCode: string, note: string) => apiRequest<SupervisionAlert>(`/api/v1/teacher/alerts/${id}/resolve`, {
    method: "POST", body: JSON.stringify({ resolution_code: resolutionCode, note })
  }),
  teacherAssignments: () => apiRequest<TeacherAssignment[]>("/api/v1/teacher/assignments")
};

export type LearningPlan = { type: "learning_plan"; items: Array<{ id: number; type: "teacher_lesson" | "fsrs_review" | "course_activity" | "remediation"; priority: number }> };
export type CourseCard = { id: number; title: string; description?: string; level?: { name: string }; lessons_count?: number; estimated_duration?: number };
export type Enrollment = { id: number; course_id: number; title: string; status: string };
export type Activity = { id: string; type: string; vocabulary_id: number; word: string; meaning: string; practice_only: boolean };
export type LearningSession = { id: number; course_id: number; lesson_id: number; status: string; completed_at?: string; activity?: Activity | null; summary?: Record<string, unknown> };
export type LearningAttempt = { activity_id: string; answer: string; duration_ms: number; hint_count: number };
export type TraceCagInput = { session_id: number; activity_id: string; input_type: "answer" | "hint_request" | "pronunciation" | "tutor_message"; text: string };
export type Assistance = { diagnosis: { codes: string[]; summary: string }; hints: Array<{ level: number; text: string }>; feedback: string; recommended_action: string; message: string; degraded: boolean };
export type FsrsCard = { type: "fsrs_card"; id: number; vocabulary_id: number; word: string; meaning: string; definition?: string; state: string; due_at?: string; revision: number; scheduled_days?: number };
export type TeacherLearner = { id: number; name: string; email: string };
export type SupervisionAlert = { id: number; rule_key: string; severity: string; state: string; evidence: Array<Record<string, unknown>>; detected_at: string; learner: TeacherLearner };
export type TeacherAssignment = { id: number; status: string; due_at?: string; learner: TeacherLearner; lesson?: { title: string }; vocabulary?: { word: string } };

export const auth = {
  register: (name: string, email: string, password: string, passwordConfirmation: string) =>
    apiRequest<{ message: string }>("/api/v1/auth/register", {
      method: "POST",
      body: JSON.stringify({ name, email, password, password_confirmation: passwordConfirmation })
    }),
  login: (email: string, password: string) =>
    apiRequest<AppUser>("/api/v1/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password })
    }),
  logout: () => request<void>("/api/v1/auth/logout", { method: "POST" }),
  me: api.me,
  forgotPassword: (email: string) =>
    apiRequest<{ message: string }>("/api/v1/auth/password/forgot", {
      method: "POST",
      body: JSON.stringify({ email })
    }),
  resetPassword: (payload: { token: string; email: string; password: string; password_confirmation: string }) =>
    apiRequest<{ message: string }>("/api/v1/auth/password/reset", {
      method: "POST",
      body: JSON.stringify(payload)
    })
};

export const profile = {
  update: (name: string) =>
    apiRequest<AppUser>("/api/v1/profile", {
      method: "PUT",
      body: JSON.stringify({ name })
    }),
  changePassword: (currentPassword: string, password: string, passwordConfirmation: string) =>
    apiRequest<null>("/api/v1/profile/password", {
      method: "PUT",
      body: JSON.stringify({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation
      })
    }),
  destroy: (password: string) =>
    request<void>("/api/v1/profile", {
      method: "DELETE",
      body: JSON.stringify({ password })
    })
};
