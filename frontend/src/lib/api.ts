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

function apiRequest<T>(path: string, init?: RequestInit) {
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
  importJobs: () => request<ImportJob[]>("/api/import/jobs")
};

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
