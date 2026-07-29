import type {
  AppUser,
  CourseLearningPath,
  FsrsPreview,
  FsrsRating,
  SupervisedDashboard,
} from "@/types/api";
export type { FsrsPreview, FsrsRating, SupervisedDashboard } from "@/types/api";
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

async function uploadVoice<T>(path: string, audio: Blob, fields: Record<string, string>) {
  await initializeCsrf();
  const form = new FormData();
  form.append("audio", audio, "recording.webm");
  Object.entries(fields).forEach(([key, value]) => form.append(key, value));
  const response = await fetch(path, {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(xsrfToken() ? { "X-XSRF-TOKEN": xsrfToken() as string } : {}),
      "X-Request-ID": crypto.randomUUID(),
    },
    body: form,
  });
  if (!response.ok) {
    const body = await response.json().catch(() => null);
    throw new ApiError(response.status, body?.message || "Voice service failed");
  }
  return response.json() as Promise<Envelope<T>>;
}

async function synthesize(text: string) {
  await initializeCsrf();
  const response = await fetch("/api/v1/ai/text-to-speech", {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "audio/mpeg",
      "Content-Type": "application/json",
      ...(xsrfToken() ? { "X-XSRF-TOKEN": xsrfToken() as string } : {}),
      "X-Request-ID": crypto.randomUUID(),
    },
    body: JSON.stringify({ text, language: "en" }),
  });
  if (!response.ok) {
    const body = await response.json().catch(() => null);
    throw new ApiError(response.status, body?.message || "Text-to-speech failed");
  }
  return URL.createObjectURL(await response.blob());
}

export const api = {
  me: () => apiRequest<AppUser>("/api/v1/auth/me"),
  vocabulary: ({ search = "", page = 1, perPage = 24, topicId, saved }: { search?: string; page?: number; perPage?: number; topicId?: number; saved?: boolean } = {}) =>
    request<Envelope<VocabularyItem[]> & { meta: { current_page: number; last_page: number; per_page: number; total: number } }>(
      `/api/v1/vocabulary${toQuery({ search, page, per_page: perPage, topic_id: topicId, saved: saved ? 1 : undefined })}`
    ),
  vocabularyItem: (id: number) => apiRequest<VocabularyItem>(`/api/v1/vocabulary/${id}`),
  catalogTopics: () => apiRequest<CatalogTopic[]>("/api/v1/catalog/topics"),
  bookmarkVocabulary: (id: number, bookmarked: boolean) => apiRequest<{ vocabulary_id: number; bookmarked: boolean }>(`/api/v1/vocabulary/${id}/bookmark`, {
    method: "PUT", body: JSON.stringify({ bookmarked })
  }),
  learningPlan: () => apiRequest<LearningPlan>("/api/v1/learning/plan"),
  learningAssignments: () => apiRequest<LearnerAssignment[]>("/api/v1/assignments"),
  catalogCourses: () => request<Envelope<CourseCard[]> & { meta: { total?: number } }>("/api/v1/catalog/courses"),
  catalogCourse: (id: number) => apiRequest<CourseDetail>(`/api/v1/catalog/courses/${id}`),
  coursePath: (id: number) => apiRequest<CourseLearningPath>(`/api/v1/catalog/courses/${id}/path`),
  catalogCourseLessons: (id: number) => request<Envelope<LessonCard[]> & { meta: { total?: number } }>(`/api/v1/catalog/courses/${id}/lessons?per_page=100`),
  catalogLesson: (id: number) => apiRequest<LessonDetail>(`/api/v1/catalog/lessons/${id}`),
  enrollments: () => apiRequest<Enrollment[]>("/api/v1/enrollments"),
  enroll: (courseId: number) => apiRequest<Enrollment>("/api/v1/enrollments", {
    method: "POST", headers: { "X-Request-ID": crypto.randomUUID() }, body: JSON.stringify({ course_id: courseId })
  }),
  startSession: (enrollmentId: number, lessonId?: number) => apiRequest<LearningSession>("/api/v1/learning/sessions", {
    method: "POST",
    headers: { "X-Request-ID": crypto.randomUUID() },
    body: JSON.stringify({ enrollment_id: enrollmentId, ...(lessonId ? { lesson_id: lessonId } : {}) })
  }),
  startAssignment: (assignmentId: number) => apiRequest<LearningSession>("/api/v1/learning/sessions", {
    method: "POST",
    headers: { "X-Request-ID": crypto.randomUUID() },
    body: JSON.stringify({ assignment_id: assignmentId })
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
  fsrsPreview: (card: FsrsCard) => apiRequest<FsrsPreview>("/api/v1/fsrs/preview", {
    method: "POST",
    body: JSON.stringify({ card_id: card.id, base_revision: card.revision })
  }),
  fsrsReview: (card: FsrsCard, rating: FsrsRating) => apiRequest<FsrsCard>("/api/v1/fsrs/review", {
    method: "POST",
    headers: { "X-Request-ID": crypto.randomUUID() },
    body: JSON.stringify({ user_vocabulary_id: card.id, rating, base_revision: card.revision })
  }),
  flashcardRecommendations: (search = "") => apiRequest<QuizWord[]>(`/api/v1/flashcards/recommendations${toQuery({ search, per_page: 30 })}`),
  saveFlashcardRecommendation: (id: number) => apiRequest<{ id: number; vocabulary_id: number }>(`/api/v1/flashcards/recommendations/${id}/save`, { method: "POST" }),
  notifications: () => apiRequest<LearnerNotification[]>("/api/v1/notifications"),
  markNotificationRead: (key: string) => apiRequest<{ read: boolean }>(`/api/v1/notifications/${encodeURIComponent(key)}/read`, { method: "POST" }),
  markAllNotificationsRead: () => apiRequest<{ read: boolean }>("/api/v1/notifications/read-all", { method: "POST" }),
  importJobs: () => apiRequest<ImportJob[]>("/api/v1/learner-imports"),
  commitImport: (payload: ImportPayload) => apiRequest<{ id: number; createdCount: number; skippedCount: number }>("/api/v1/learner-imports", { method: "POST", body: JSON.stringify(payload) }),
  dictionaryLookup: (word: string) => apiRequest<DictionaryLookup>(`/api/v1/dictionary/${encodeURIComponent(word)}`),
  vocabularyFilters: () => apiRequest<string[]>("/api/v1/vocabulary-filters"),
  startVocabularyQuiz: (payload: QuizStartInput) => apiRequest<QuizStart>("/api/v1/vocabulary-quizzes", { method: "POST", body: JSON.stringify(payload) }),
  answerVocabularyQuiz: (sessionId: number, vocabularyId: number, answer: string) => apiRequest<QuizAnswerFeedback>(`/api/v1/vocabulary-quizzes/${sessionId}/answers/${vocabularyId}`, { method: "PUT", body: JSON.stringify({ answer }) }),
  completeVocabularyQuiz: (sessionId: number) => apiRequest<QuizResult>(`/api/v1/vocabulary-quizzes/${sessionId}/complete`, { method: "POST" }),
  teacherLearners: () => apiRequest<TeacherLearner[]>("/api/v1/teacher/learners"),
  teacherLearner: (id: number) => apiRequest<TeacherLearner>(`/api/v1/teacher/learners/${id}`),
  teacherLearnerProgress: (id: number) => apiRequest<TeacherLearnerProgress>(`/api/v1/teacher/learners/${id}/progress`),
  teacherLearnerEvidence: (id: number) => apiRequest<LearningEvidence[]>(`/api/v1/teacher/learners/${id}/evidence`),
  teacherAlerts: () => apiRequest<SupervisionAlert[]>("/api/v1/teacher/alerts"),
  teacherAlert: (id: number) => apiRequest<SupervisionAlert>(`/api/v1/teacher/alerts/${id}`),
  resolveAlert: (id: number, resolutionCode: string, note: string) => apiRequest<SupervisionAlert>(`/api/v1/teacher/alerts/${id}/resolve`, {
    method: "POST", body: JSON.stringify({ resolution_code: resolutionCode, note })
  }),
  teacherAssignments: () => apiRequest<TeacherAssignment[]>("/api/v1/teacher/assignments"),
  createTeacherAssignment: (payload: TeacherAssignmentInput) => apiRequest<TeacherAssignment>("/api/v1/teacher/assignments", {
    method: "POST", headers: { "X-Request-ID": crypto.randomUUID() }, body: JSON.stringify(payload)
  }),
  updateTeacherAssignment: (id: number, payload: { status?: "pending" | "in_progress" | "cancelled"; instructions?: string; due_at?: string | null }) =>
    apiRequest<TeacherAssignment>(`/api/v1/teacher/assignments/${id}`, {
      method: "PUT", headers: { "X-Request-ID": crypto.randomUUID() }, body: JSON.stringify(payload)
    }),
  createInterventionNote: (payload: { learner_id: number; supervision_alert_id?: number; assignment_id?: number; note: string }) =>
    apiRequest<Record<string, unknown>>("/api/v1/teacher/intervention-notes", {
      method: "POST", body: JSON.stringify(payload)
    }),
  supervisedProgress: () => request<Envelope<SupervisedProgress[]> & { meta: { total: number } }>("/api/v1/progress"),
  supervisedDashboard: () => apiRequest<SupervisedDashboard>("/api/v1/progress/dashboard"),
  courseProgress: (courseId: number) => apiRequest<CourseProgress>(`/api/v1/progress/course/${courseId}`),
  translate: (text: string, source = "en", target = "vi") => apiRequest<Translation>("/api/v1/ai/translate", {
    method: "POST", headers: { "X-Request-ID": crypto.randomUUID() }, body: JSON.stringify({ text, source_lang: source, target_lang: target })
  }),
  textToSpeech: synthesize,
  speechToText: (audio: Blob, sessionId: number, activityId: string) =>
    uploadVoice<Transcription>("/api/v1/ai/speech-to-text", audio, {
      language: "en", session_id: String(sessionId), activity_id: activityId
    }).then(({ data }) => data),
  assessPronunciation: (audio: Blob, referenceText: string, sessionId: number, activityId: string) =>
    uploadVoice<PronunciationAssessment>("/api/v1/ai/pronunciation", audio, {
      language: "en", reference_text: referenceText, session_id: String(sessionId), activity_id: activityId
    }).then(({ data }) => data),
  youtubeChannels: (query?: Query) => apiRequest<YouTubePage<YouTubeChannel>>(`/api/v1/media/youtube/channels${toQuery(query)}`),
  youtubeSearch: (query: Query) => apiRequest<YouTubePage<YouTubeVideo>>(`/api/v1/media/youtube/search${toQuery(query)}`),
  youtubeCaptions: (videoId: string) => apiRequest<YouTubeCaptions>(`/api/v1/media/youtube/videos/${encodeURIComponent(videoId)}/captions`),
  listeningProgress: () => apiRequest<ListeningSession[]>("/api/v1/media/listening-sessions"),
  startListening: (video: YouTubeVideo) => apiRequest<ListeningSession>("/api/v1/media/listening-sessions", { method: "POST", body: JSON.stringify({ external_id: video.id, title: video.title, channel: video.channel, thumbnail_url: video.thumbnail_url, duration_seconds: video.duration_seconds }) }),
  updateListening: (id: number, positionSeconds: number, durationSeconds: number) => apiRequest<ListeningSession>(`/api/v1/media/listening-sessions/${id}`, { method: "PUT", body: JSON.stringify({ position_seconds: Math.round(positionSeconds), duration_seconds: Math.round(durationSeconds) }) }),
  shadowCaption: (id: number, audio: Blob, referenceText: string) => uploadVoice<PronunciationAssessment>(`/api/v1/media/listening-sessions/${id}/pronunciation`, audio, { reference_text: referenceText }).then(({ data }) => data),
  completeListening: (id: number) => apiRequest<ListeningSession>(`/api/v1/media/listening-sessions/${id}/complete`, { method: "POST" })
};

export type Translation = { translated_text: string; source_lang: string; target_lang: string };
export type Transcription = { transcript: string; confidence: number | null };
export type PronunciationAssessment = { score: number; feedback: string };
export type YouTubeChannel = { id: string; title: string; thumbnail_url?: string; category?: string; cefr_level?: string };
export type YouTubeVideo = { id: string; title: string; channel?: string; thumbnail_url?: string; duration_seconds?: number; has_captions?: boolean };
export type YouTubePage<T> = { items: T[]; next_page_token?: string };
export type Caption = { start: number; duration?: number; text: string };
export type YouTubeCaptions = { items: Caption[]; language?: string };
export type ListeningSession = { id: number; external_id: string; title: string; channel?: string; thumbnail_url?: string; position_seconds: number; duration_seconds: number; watched_percentage: number; shadowing_attempts: number; best_pronunciation_score?: number; status: string; completed_at?: string };

export type LearningPlan = { type: "learning_plan"; items: Array<{ id: number; type: "teacher_lesson" | "teacher_vocabulary_practice" | "fsrs_review" | "course_activity" | "pronunciation_task" | "remediation"; priority: number }> };
export type CourseCard = { id: number; title: string; description?: string; level?: { name: string }; lessons_count?: number; estimated_duration?: number };
export type CourseDetail = CourseCard & { slug: string; units_count?: number; category?: { name: string }; topics?: Array<{ id: number; name: string }> };
export type LessonCard = { id: number; course_id: number; title: string; content?: string; sort_order: number; estimated_minutes?: number; vocabularies_count?: number };
export type LessonDetail = LessonCard & { vocabularies?: Array<{ id: number; word: string; meaning: string }> };
export type Enrollment = { id: number; course_id: number; title: string; status: string };
export type Activity = { id: string; type: string; vocabulary_id: number; word: string; meaning: string; audio_url?: string; practice_only: boolean };
export type LearningSummary = { events?: number; correct_answers?: number; duration_seconds?: number; best_pronunciation_score?: number | null; next_step?: string; completed_lesson_id?: number | null };
export type LearningSession = { id: number; course_id: number; lesson_id: number; status: string; completed_at?: string; activity?: Activity | null; summary?: LearningSummary; progress?: { completed: number; total: number } };
export type LearningAttempt = { activity_id: string; answer: string; duration_ms: number; hint_count: number };
export type TraceCagInput = { session_id: number; activity_id: string; input_type: "answer" | "hint_request" | "pronunciation" | "tutor_message"; text: string };
export type Assistance = { diagnosis: { codes: string[]; summary: string }; hints: Array<{ level: number; text: string }>; feedback: string; recommended_action: string; message: string; degraded: boolean };
export type FsrsCard = { type: "fsrs_card"; id: number; vocabulary_id: number; word: string; meaning: string; definition?: string; pronunciation?: string; example?: string; state: string; due_at?: string; revision: number; scheduled_days?: number };
export type LearnerNotification = { id: string; title: string; message: string; type: string; deepLink?: string; isRead: boolean; createdAt: string };
export type QuizWord = { id: number; word: string; translation: string; definition?: string; pronunciation?: string; category?: string; partOfSpeech?: string; difficulty?: string; example?: string };
export type ImportRow = { clientRowId: string; word: string; translation?: string; pronunciation?: string; category?: string; difficulty?: "BEGINNER" | "INTERMEDIATE" | "ADVANCED"; cefrLevel?: "A1" | "A2" | "B1" | "B2" | "C1" | "C2" | ""; topicId?: number | null; example?: string; partOfSpeech?: string; audioUrl?: string };
export type ImportJob = { id: number; targetSetName?: string; status: string; totalRows: number; createdCount: number; skippedCount: number; createdAt: string };
export type ImportPayload = { sourceType: "PASTE" | "FILE"; targetSet: { name: string } | null; rows: ImportRow[] };
export type DictionaryLookup = { firstDefinition?: string; firstExample?: string; bestPhonetic?: string; primaryPartOfSpeech?: string; bestAudioUrl?: string };
export type QuizStartInput = { count: number; category?: string; difficulty?: string; topicId?: number; cefrLevel?: string; type: "en-vi" | "vi-en" };
export type QuizQuestion = { id: number; prompt: string; choices: string[]; pronunciation?: string; category?: string };
export type QuizStart = { sessionId: number; questions: QuizQuestion[]; totalQuestions: number };
export type QuizAnswerFeedback = { vocabularyId: number; isCorrect: boolean; correctAnswer: string };
export type QuizResult = { sessionId: number; correctCount: number; incorrectCount: number; score: number; status: string };
export type TeacherLearner = { id: number; name: string; email: string };
export type SupervisionAlert = { id: number; rule_key: string; severity: string; state: string; evidence: Array<Record<string, unknown>>; detected_at: string; learner: TeacherLearner };
export type TeacherAssignment = { id: number; status: string; due_at?: string; learner: TeacherLearner; lesson?: { title: string }; vocabulary?: { word: string } };
export type LearnerAssignment = { id: number; status: string; instructions?: string; due_at?: string; teacher: { id: number; name: string }; lesson?: { id: number; title: string }; vocabulary?: { id: number; word: string; meaning: string } };
export type TeacherAssignmentInput = { learner_id: number; lesson_id?: number; vocabulary_id?: number; instructions?: string; due_at?: string };
export type TeacherLearnerProgress = { completed_lessons: number; recent_events: number };
export type LearningEvidence = { id: number; event_type: string; is_correct?: boolean; hint_level?: number; pronunciation_score?: number; duration_ms?: number; occurred_at: string; metadata?: Record<string, unknown> };
export type SupervisedProgress = { id: number; lesson_id: number; completed_at: string; lesson?: LessonCard };
export type CourseProgress = { course: { id: number; title: string }; progress_percent: number; completed_lessons: number; total_lessons: number };
export type CatalogTopic = { id: number; name: string; slug?: string };
export type VocabularyItem = { type: "vocabulary"; id: number; external_id?: string; word: string; meaning: string; definition?: string; translation?: Record<string, string>; pronunciation?: string; part_of_speech?: string; difficulty_level?: string; tags?: string[]; external_audio_url?: string; image_url?: string; audio_url?: string; example?: string; topic?: { id: number; name: string } | null; lesson?: { id: number; title: string } | null; is_bookmarked: boolean };
export type LessonQuizSummary = { id: number; lesson_id: number; title: string; passing_score: number; questions_count: number };
export type LessonQuizAnswer = { id: number; content: string };
export type LessonQuizQuestion = { id: number; content: string; answers: LessonQuizAnswer[] };
export type SubmittedLessonQuizAnswer = { question_id: number; answer_id: number; answer_content: string; is_correct: boolean; explanation?: string | null };
export type LessonQuizAttempt = {
  id: number;
  status: "active" | "completed";
  quiz?: { id: number; title: string; passing_score: number };
  questions?: LessonQuizQuestion[];
  submitted_answers?: SubmittedLessonQuizAnswer[];
  score?: number;
  correct_answers?: number;
  total_questions?: number;
  passed?: boolean;
  completed_at?: string | null;
};
export type LessonQuizAnswerFeedback = { question_id: number; answer_id: number; is_correct: boolean; explanation?: string | null };

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
    }),
  resendVerification: () => apiRequest<{ message: string }>("/api/v1/auth/email/resend", { method: "POST" })
};

export const lessonQuizzes = {
  list: (lessonId: number) => apiRequest<LessonQuizSummary[]>(`/api/v1/lesson-quizzes${toQuery({ lesson_id: lessonId })}`),
  start: (quizId: number) => apiRequest<LessonQuizAttempt>(`/api/v1/lesson-quizzes/${quizId}/attempts`, { method: "POST" }),
  get: (attemptId: number) => apiRequest<LessonQuizAttempt>(`/api/v1/lesson-quiz-attempts/${attemptId}`),
  answer: (attemptId: number, questionId: number, answerId: number) => apiRequest<LessonQuizAnswerFeedback>(`/api/v1/lesson-quiz-attempts/${attemptId}/answers/${questionId}`, {
    method: "PUT", body: JSON.stringify({ answer_id: answerId })
  }),
  complete: (attemptId: number) => apiRequest<LessonQuizAttempt>(`/api/v1/lesson-quiz-attempts/${attemptId}/complete`, { method: "POST" })
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
