"use client";

// --- typed HTTP helper -----------------------------------------------------

export class ApiError extends Error {
  status: number;
  body: unknown;

  constructor(status: number, body: unknown) {
    const message = typeof body === "object" && body !== null && "message" in body
      ? String((body as Record<string, unknown>).message)
      : String(body ?? `HTTP ${status}`);
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.body = body;
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

interface PaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

// --- Type imports -----------------------------------------------------------

import type { AppUser } from "@/types/api";

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

export async function logout(): Promise<void> {
  await apiRequest("POST", "/api/v1/auth/logout");
}

export async function fetchMe(): Promise<AppUser> {
  const result = await apiRequest<ApiEnvelope<AppUser>>("GET", "/api/v1/auth/me");
  return result.data;
}

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
  bookmarkable_type?: string;
  bookmarkable_id?: number;
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
  is_correct?: boolean;
  sort_order?: number;
}

export interface AttemptResource {
  id: number;
  score: number;
  total_questions?: number;
  correct_answers?: number;
  is_completed?: boolean;
  completed_at?: string;
  created_at?: string;
}

export interface ProgressResource {
  id: number;
  course?: { id: number; title: string };
  completed_lessons?: number;
  total_lessons?: number;
  progress_percent?: number;
}

export interface DashboardResource {
  total_words?: number;
  total_bookmarks?: number;
  completed_lessons?: number;
  total_lessons?: number;
  recent_attempts?: AttemptResource[];
  course_progress?: ProgressResource[];
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

export async function toggleBookmarkVocabulary(vocabularyId: number): Promise<ApiEnvelope<{ bookmarked: boolean }>> {
  return apiRequest("POST", `/api/v1/bookmarks/vocabulary/${vocabularyId}/toggle`);
}

export async function toggleBookmarkLesson(lessonId: number): Promise<ApiEnvelope<{ bookmarked: boolean }>> {
  return apiRequest("POST", `/api/v1/bookmarks/lesson/${lessonId}/toggle`);
}

// --- Quizzes (auth) ---------------------------------------------------------

export async function fetchQuiz(quizId: number): Promise<QuizResource> {
  const result = await apiRequest<ApiEnvelope<QuizResource>>("GET", `/api/v1/quizzes/${quizId}`);
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