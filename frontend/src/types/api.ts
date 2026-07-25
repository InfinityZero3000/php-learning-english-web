export type DifficultyLevel = "BEGINNER" | "INTERMEDIATE" | "ADVANCED";
export type CefrLevel = "A1" | "A2" | "B1" | "B2" | "C1" | "C2";

export interface DictionaryLookup {
  word?: string;
  bestPhonetic?: string;
  firstDefinition?: string;
  firstExample?: string;
  primaryPartOfSpeech?: string;
  bestAudioUrl?: string;
  allSynonyms?: string;
  allAntonyms?: string;
}

export interface Topic {
  id: number;
  name: string;
  slug?: string;
  description?: string;
  iconEmoji?: string;
}

export interface Word {
  id: number;
  word: string;
  translation: string;
  pronunciation?: string;
  category?: string;
  difficulty?: DifficultyLevel;
  cefrLevel?: CefrLevel;
  partOfSpeech?: string;
  example?: string;
  imageUrl?: string;
  enrichmentStatus?: string;
  topic?: Topic | null;
}

export interface PageResponse<T> {
  content: T[];
  number: number;
  size: number;
  totalElements: number;
  totalPages: number;
  last: boolean;
}

export interface UserProgress {
  id: number;
  word: Word;
  mastery?: string;
  correctCount?: number;
  incorrectCount?: number;
  lastReviewed?: string;
  nextReviewAt?: string;
  stability?: number;
  difficulty?: number;
}

export interface QuizSession {
  id: number;
  totalQuestions?: number;
  correctAnswers?: number;
  completed?: boolean;
  score?: number;
  createdAt?: string;
  completedAt?: string;
}

export interface AppUser {
  id: number;
  email: string;
  name?: string;
  avatarUrl?: string;
  role?: string;
  email_verified_at?: string | null;
  createdAt?: string;
  lastLoginAt?: string;
}

export interface ReminderSettings {
  reviewRemindersEnabled: boolean;
  preferredReminderTime: string;
  eveningReminderEnabled: boolean;
  eveningReminderTime: string;
  comebackReminderEnabled: boolean;
  comebackReminderIntervalDays: number;
  eveningRemainingThreshold: number;
}

export interface TrustedFlashcard {
  id: number;
  front?: string;
  back?: string;
  word?: string;
  translation?: string;
  definition?: string;
  example?: string;
  level?: string;
  source?: string;
  sourceName?: string;
  topic?: string;
}

export interface ImportRow {
  clientRowId: string;
  word: string;
  translation?: string;
  pronunciation?: string;
  category?: string;
  difficulty?: DifficultyLevel;
  cefrLevel?: CefrLevel | "";
  partOfSpeech?: string;
  example?: string;
  audioUrl?: string;
  topicId?: number | null;
}

export interface ImportJob {
  id: number;
  sourceType?: string;
  fileName?: string;
  targetSetName?: string;
  status?: string;
  totalRows?: number;
  created?: number;
  skipped?: number;
  failed?: number;
  createdAt?: string;
  rows?: Array<Record<string, unknown>>;
}

export interface NotificationItem {
  id: number;
  title: string;
  message: string;
  type?: "REVIEW_REMINDER" | "DAILY_DUE_REMINDER" | "EVENING_REVIEW_REMINDER" | "OVERDUE_REMINDER" | "STREAK_ALERT" | "ACHIEVEMENT" | "SYSTEM";
  deepLink?: string;
  isRead?: boolean;
  read?: boolean;
  createdAt?: string;
}
