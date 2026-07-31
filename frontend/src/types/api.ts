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

export type CoursePathLesson = {
  id: number;
  title: string;
  slug?: string;
  sort_order: number;
  estimated_minutes?: number | null;
  xp_reward?: number;
  status: "completed" | "available" | "locked";
  is_current: boolean;
  prerequisites: Array<{ id: number; title: string; completed: boolean }>;
  locked_reason: string | null;
};

export type CoursePathUnit = {
  id: number | null;
  title: string;
  description?: string | null;
  sort_order: number;
  icon_url?: string | null;
  background_color?: string | null;
  completed_lessons: number;
  total_lessons: number;
  lessons: CoursePathLesson[];
};

export type CourseLearningPath = {
  type: "course_learning_path";
  course: {
    id: number;
    title: string;
    slug: string;
    description?: string | null;
    thumbnail_url?: string | null;
    estimated_duration?: number | null;
    total_xp?: number;
  };
  enrollment: { id: number; status: string } | null;
  progress: { completed_lessons: number; total_lessons: number; percentage: number };
  units: CoursePathUnit[];
  next_action:
    | { type: "resume_session"; session_id: number; lesson_id: number }
    | { type: "start_lesson"; enrollment_id: number; lesson_id: number }
    | null;
};

export type FsrsRating = "again" | "hard" | "good" | "easy";

export type FsrsPreview = {
  type: "fsrs_preview";
  card_id: number;
  base_revision: number;
  generated_at: string;
  ratings: Array<{
    rating: FsrsRating;
    due_at: string;
    interval_seconds: number;
  }>;
};

export type ProgressCourse = {
  id: number;
  title: string;
  enrollment_status: string;
  completed_lessons: number;
  total_lessons: number;
  progress_percent: number;
  units: Array<{
    id: number | null;
    title: string;
    completed_lessons: number;
    total_lessons: number;
    lessons: Array<{ id: number; title: string; completed: boolean }>;
  }>;
};

export type SupervisedDashboard = {
  type: "progress";
  overview: {
    completed_lessons: number;
    quiz_attempts: number;
    average_score: number;
  };
  fsrs: {
    reviewed_cards: number;
    due_count: number;
    retention: number | null;
    average_stability: number | null;
    average_difficulty: number | null;
    state_distribution: Array<{
      state: "new" | "learning" | "review" | "relearning";
      count: number;
    }>;
    forecast: Array<{ date: string; count: number }>;
  };
  courses: ProgressCourse[];
  recent_sessions: Array<{
    id: number;
    status: string;
    started_at: string | null;
    completed_at: string | null;
    course: { id: number; title: string } | null;
    lesson: { id: number; title: string } | null;
  }>;
  quiz_performance: {
    attempts: number;
    average_score: number | null;
    correct_rate: number | null;
    recent: Array<{
      id: number;
      quiz_id: number;
      quiz_title: string;
      lesson_title: string;
      score: number;
      completed_at: string | null;
    }>;
  };
  listening: {
    sessions: number;
    completed_sessions: number;
    minutes_listened: number;
    shadowing_attempts: number;
    best_pronunciation_score: number | null;
    recent: Array<{
      id: number;
      title: string;
      watched_percentage: number;
      shadowing_attempts: number;
      best_pronunciation_score: number | null;
      status: string;
    }>;
  };
  recent_activity: Array<{
    id: number;
    lesson_id: number;
    completed_at: string;
  }>;
  recent_attempts: Array<{
    id: number;
    quiz_id: number;
    score: number;
    completed_at: string;
  }>;
};
