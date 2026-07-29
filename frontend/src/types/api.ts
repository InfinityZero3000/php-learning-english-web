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
