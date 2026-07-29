"use client";

// --- typed HTTP helper -----------------------------------------------------

export class ApiError extends Error {
  status: number;
  body: unknown;
  errors: Record<string, string[]>;

  constructor(status: number, body: unknown, errors?: Record<string, string[]>) {
    const bodyObject = typeof body === "object" && body !== null ? (body as Record<string, unknown>) : null;
    const message = bodyObject && "message" in bodyObject
      ? String(bodyObject.message)
      : String(body ?? `HTTP ${status}`);
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.body = body;
    if (errors) {
      this.errors = errors;
    } else if (bodyObject && "errors" in bodyObject && typeof bodyObject.errors === "object" && bodyObject.errors !== null) {
      this.errors = bodyObject.errors as Record<string, string[]>;
    } else {
      this.errors = {};
    }
  }
}

function xsrfToken(): string | null {
  if (typeof document === "undefined") return null;
  const token = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.slice("XSRF-TOKEN=".length);
  return token ? decodeURIComponent(token) : null;
}

export async function initializeCsrf(): Promise<void> {
  const response = await fetch("/api/v1/csrf-cookie", {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  if (!response.ok) throw new Error("Could not initialize a secure session.");
}

export async function apiRequest<T = unknown>(
  method: "GET" | "POST" | "PUT" | "DELETE" | "PATCH",
  url: string,
  body?: unknown,
  options?: { headers?: Record<string, string>; timeoutMs?: number },
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    ...options?.headers,
  };

  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  const token = xsrfToken();
  if (token) {
    headers["X-XSRF-TOKEN"] = token;
  }

  const controller = new AbortController();
  const timeoutId = options?.timeoutMs
    ? setTimeout(() => controller.abort(), options.timeoutMs)
    : undefined;

  try {
    const response = await fetch(url, {
      method,
      credentials: "include",
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });

    if (!response.ok) {
      let errorBody: unknown;
      try {
        errorBody = await response.json();
      } catch {
        errorBody = null;
      }
      throw new ApiError(response.status, errorBody);
    }

    if (response.status === 204) return undefined as unknown as T;

    return (await response.json()) as T;
  } finally {
    if (timeoutId) clearTimeout(timeoutId);
  }
}

// --- Response shapes (match Laravel ApiResponse envelope) -------------------

interface ApiEnvelope<T> {
  data: T;
  meta?: Record<string, unknown>;
}

export interface PaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

// --- Type imports -----------------------------------------------------------

import type { AppUser, ImportJob, ImportRow, Topic, TrustedFlashcard, UserProgress, Word } from "@/types/api";

// --- Auth -------------------------------------------------------------------

export async function login(email: string, password: string): Promise<ApiEnvelope<AppUser>> {
  return apiRequest("POST", "/api/v1/auth/login", { email, password });
}

export async function registerUser(
  name: string,
  email: string,
  password: string,
  passwordConfirmation: string,
): Promise<ApiEnvelope<{ message: string }>> {
  return apiRequest("POST", "/api/v1/auth/register", {
    name,
    email,
    password,
    password_confirmation: passwordConfirmation,
  });
}

export async function performLogout(): Promise<void> {
  await apiRequest("POST", "/api/v1/auth/logout");
}

export const logout = performLogout;

export async function fetchMe(): Promise<AppUser> {
  const result = await apiRequest<ApiEnvelope<AppUser>>("GET", "/api/v1/auth/me");
  return result.data;
}

export async function forgotPassword(email: string): Promise<ApiEnvelope<{ message: string }>> {
  return apiRequest("POST", "/api/v1/auth/password/forgot", { email });
}

export async function resendVerification(): Promise<ApiEnvelope<{ message: string }>> {
  return apiRequest("POST", "/api/v1/auth/email/resend");
}

export async function resetPassword(payload: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<ApiEnvelope<{ message: string }>> {
  return apiRequest("POST", "/api/v1/auth/password/reset", payload);
}

// --- Convenience objects (auto-init CSRF for auth mutations) ----------------

export const auth = {
  login: async (email: string, password: string) => {
    await initializeCsrf();
    return login(email, password);
  },
  register: async (name: string, email: string, password: string, confirmation: string) => {
    await initializeCsrf();
    return registerUser(name, email, password, confirmation);
  },
  forgotPassword: async (email: string) => {
    await initializeCsrf();
    return forgotPassword(email);
  },
  resendVerification: async () => {
    await initializeCsrf();
    return resendVerification();
  },
  resetPassword: async (payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => {
    await initializeCsrf();
    return resetPassword(payload);
  },
  me: async () => fetchMe(),
  logout: async () => {
    await initializeCsrf();
    return performLogout();
  },
};

// --- Profile ----------------------------------------------------------------

export const profile = {
  update: async (name: string): Promise<AppUser> => {
    const result = await apiRequest<ApiEnvelope<AppUser>>("PUT", "/api/v1/profile", { name });
    return result.data;
  },
};

// --- Catalog ----------------------------------------------------------------

export interface CourseResource {
  id: number;
  title: string;
  description?: string;
  thumbnail_url?: string;
  status?: string;
  total_xp?: number;
  total_lessons?: number;
  lessons_count?: number;
  units_count?: number;
  level?: { id: number; name: string; slug?: string };
  category?: { id: number; name: string; slug?: string };
  topics?: Array<{ id: number; name: string; slug?: string }>;
  created_at?: string;
  updated_at?: string;
}

export interface LessonResource {
  id: number;
  title: string;
  content?: string;
  lesson_type?: string;
  status?: string;
  sort_order?: number;
  course?: CourseResource;
  quizzes_count?: number;
  vocabularies_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface BookmarkResource {
  id: number;
  bookmark_type: "lesson" | "vocabulary";
  lesson?: {
    id: number;
    title: string;
    course?: { id: number; title: string };
  };
  vocabulary?: {
    id: number;
    word: string;
    meaning?: string;
    translation?: unknown;
  };
  created_at?: string;
}

export interface QuizResource {
  id: number;
  title?: string;
  description?: string;
  lesson_id?: number;
  lesson?: { id: number; title: string };
  questions_count?: number;
  questions?: QuestionResource[];
  created_at?: string;
}

export interface QuestionResource {
  id: number;
  content: string;
  question_type?: string;
  sort_order?: number;
  answers?: AnswerResource[];
}

export interface AnswerResource {
  id: number;
  content: string;
  sort_order?: number;
}

export interface AttemptResource {
  id: number;
  score: number;
  quiz_id?: number;
  completed_at?: string;
  created_at?: string;
}

export interface ProgressResource {
  id: number;
  lesson_id?: number;
  completed_at?: string;
  lesson?: LessonResource;
}

export interface DashboardResource {
  overview: {
    completed_lessons: number;
    quiz_attempts: number;
    average_score: number;
  };
  recent_activity: ProgressResource[];
  recent_attempts?: AttemptResource[];
}

export async function fetchCourses(params?: {
  search?: string;
  level_id?: number;
  category_id?: number;
  topic_id?: number;
  sort_by?: string;
  per_page?: number;
  page?: number;
}): Promise<{ data: CourseResource[]; meta: PaginationMeta }> {
  const searchParams = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        searchParams.set(key, String(value));
      }
    });
  }
  const qs = searchParams.toString();
  const url = `/api/v1/catalog/courses${qs ? `?${qs}` : ""}`;
  return apiRequest("GET", url);
}

export async function fetchCourse(courseId: number): Promise<CourseResource> {
  const result = await apiRequest<ApiEnvelope<CourseResource>>("GET", `/api/v1/catalog/courses/${courseId}`);
  return result.data;
}

export async function fetchCourseLessons(
  courseId: number,
  params?: { per_page?: number; page?: number },
): Promise<{ data: LessonResource[]; meta: PaginationMeta }> {
  const searchParams = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        searchParams.set(key, String(value));
      }
    });
  }
  const qs = searchParams.toString();
  const url = `/api/v1/catalog/courses/${courseId}/lessons${qs ? `?${qs}` : ""}`;
  return apiRequest("GET", url);
}

export async function fetchLessons(params?: {
  search?: string;
  course_id?: number;
  lesson_type?: string;
  per_page?: number;
  page?: number;
}): Promise<{ data: LessonResource[]; meta: PaginationMeta }> {
  const searchParams = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        searchParams.set(key, String(value));
      }
    });
  }
  const qs = searchParams.toString();
  const url = `/api/v1/catalog/lessons${qs ? `?${qs}` : ""}`;
  return apiRequest("GET", url);
}

export async function fetchLesson(lessonId: number): Promise<LessonResource> {
  const result = await apiRequest<ApiEnvelope<LessonResource>>("GET", `/api/v1/catalog/lessons/${lessonId}`);
  return result.data;
}

// --- Vocabulary (read-only public) ------------------------------------------

export interface VocabularyResource {
  id: number;
  word: string;
  meaning?: string;
  definition?: string;
  translation?: unknown;
  pronunciation?: string;
  part_of_speech?: string;
  difficulty_level?: string;
  tags?: string[];
  external_audio_url?: string;
}

export async function fetchVocabulary(params?: {
  search?: string;
  per_page?: number;
  page?: number;
}): Promise<{ data: VocabularyResource[]; meta: PaginationMeta }> {
  const searchParams = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        searchParams.set(key, String(value));
      }
    });
  }
  const qs = searchParams.toString();
  const url = `/api/v1/vocabulary${qs ? `?${qs}` : ""}`;
  return apiRequest("GET", url);
}

// --- Bookmarks (auth) -------------------------------------------------------

export async function fetchBookmarks(params?: {
  bookmark_type?: "lesson" | "vocabulary";
  per_page?: number;
  page?: number;
}): Promise<{ data: BookmarkResource[]; meta: PaginationMeta }> {
  const searchParams = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        searchParams.set(key, String(value));
      }
    });
  }
  const qs = searchParams.toString();
  const url = `/api/v1/bookmarks${qs ? `?${qs}` : ""}`;
  return apiRequest("GET", url);
}

export async function toggleBookmarkVocabulary(vocabularyId: number): Promise<ApiEnvelope<{ status: "bookmarked" | "unbookmarked" }>> {
  return apiRequest("POST", `/api/v1/bookmarks/vocabulary/${vocabularyId}/toggle`);
}

export async function toggleBookmarkLesson(lessonId: number): Promise<ApiEnvelope<{ status: "bookmarked" | "unbookmarked" }>> {
  return apiRequest("POST", `/api/v1/bookmarks/lesson/${lessonId}/toggle`);
}

// --- Quizzes (auth) ---------------------------------------------------------

export async function fetchQuiz(quizId: number): Promise<QuizResource> {
  const result = await apiRequest<ApiEnvelope<QuizResource>>("GET", `/api/v1/quizzes/${quizId}`);
  return result.data;
}

export async function fetchQuizzes(): Promise<QuizResource[]> {
  const result = await apiRequest<ApiEnvelope<QuizResource[]>>("GET", "/api/v1/quizzes");
  return result.data;
}

export interface QuizSubmissionPayload {
  answers: Array<{ question_id: number; answer_id: number | null }>;
}

export async function submitQuiz(quizId: number, payload: QuizSubmissionPayload): Promise<AttemptResource> {
  const result = await apiRequest<ApiEnvelope<AttemptResource>>("POST", `/api/v1/quizzes/${quizId}/submit`, payload);
  return result.data;
}

export async function fetchQuizHistory(quizId: number): Promise<{ data: AttemptResource[] }> {
  return apiRequest("GET", `/api/v1/quizzes/${quizId}/history`);
}

// --- Progress (auth) --------------------------------------------------------

export async function fetchMyProgress(): Promise<ProgressResource[]> {
  const result = await apiRequest<ApiEnvelope<ProgressResource[]>>("GET", "/api/v1/progress");
  return result.data;
}

export async function fetchDashboard(): Promise<DashboardResource> {
  const result = await apiRequest<ApiEnvelope<DashboardResource>>("GET", "/api/v1/progress/dashboard");
  return result.data;
}

export async function fetchCourseProgress(courseId: number): Promise<ProgressResource> {
  const result = await apiRequest<ApiEnvelope<ProgressResource>>("GET", `/api/v1/progress/course/${courseId}`);
  return result.data;
}

export async function markLessonCompleted(lessonId: number): Promise<ApiEnvelope<{ completed: boolean }>> {
  return apiRequest("POST", `/api/v1/progress/lesson/${lessonId}/complete`);
}

// --- Flashcards --------------------------------------------------------------

export async function dueWords(count: number): Promise<UserProgress[]> {
  const result = await apiRequest<ApiEnvelope<UserProgress[]>>("GET", `/api/v1/flashcards/due?count=${count}`);
  return result.data;
}

export async function publicVocabulary(params: {
  search?: string;
  perPage?: number;
}): Promise<{ words: Word[]; meta: PaginationMeta }> {
  const searchParams = new URLSearchParams();
  if (params.search) searchParams.set("search", params.search);
  if (params.perPage) searchParams.set("per_page", String(params.perPage));
  const qs = searchParams.toString();
  return apiRequest<{ words: Word[]; meta: PaginationMeta }>("GET", `/api/v1/vocabulary/public${qs ? `?${qs}` : ""}`);
}

export async function reviewWord(wordId: number, rating: number, timeSpent: number): Promise<void> {
  await apiRequest("POST", `/api/v1/flashcards/review`, {
    word_id: wordId,
    rating,
    time_spent: timeSpent,
  });
}

export async function checkInStreak(): Promise<{ streak: number } | undefined> {
  try {
    return await apiRequest("POST", "/api/v1/flashcards/streak");
  } catch {
    return undefined;
  }
}

export async function importFlashcards(source: string, query: string): Promise<TrustedFlashcard[]> {
  const result = await apiRequest<ApiEnvelope<TrustedFlashcard[]>>("POST", "/api/v1/flashcards/import", {
    source,
    query,
  });
  return result.data;
}

export async function saveFlashcard(flashcardId: number): Promise<void> {
  await apiRequest("POST", `/api/v1/flashcards/${flashcardId}/save`);
}

// --- Import ------------------------------------------------------------------

export async function topics(): Promise<Topic[]> {
  const result = await apiRequest<ApiEnvelope<Topic[]>>("GET", "/api/v1/import/topics");
  return result.data;
}

export async function importJobs(): Promise<ImportJob[]> {
  const result = await apiRequest<ApiEnvelope<ImportJob[]>>("GET", "/api/v1/import/jobs");
  return result.data;
}

export async function translateRows(rows: ImportRow[]): Promise<{ rows: ImportRow[] }> {
  const result = await apiRequest<ApiEnvelope<{ rows: ImportRow[] }>>("POST", "/api/v1/import/translate", { rows });
  return result.data;
}

export async function lookupDictionary(word: string): Promise<unknown> {
  const result = await apiRequest<ApiEnvelope<unknown>>("GET", `/api/v1/import/dictionary?word=${encodeURIComponent(word)}`);
  return result.data;
}

export async function commitImport(payload: unknown): Promise<void> {
  await apiRequest("POST", "/api/v1/import/commit", payload);
}

// --- Namespace object for convenience imports --------------------------------

export const api = {
  dueWords,
  publicVocabulary,
  reviewWord,
  checkInStreak,
  importFlashcards,
  saveFlashcard,
  topics,
  importJobs,
  translateRows,
  lookupDictionary,
  commitImport,
};
