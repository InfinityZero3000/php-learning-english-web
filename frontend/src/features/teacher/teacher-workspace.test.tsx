import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { api, type CourseCard, type SupervisionAlert, type TeacherAssignment, type TeacherLearner } from "@/lib/api";
import { TeacherWorkspace } from "./teacher-workspace";

let mockSearchParams = new URLSearchParams();

vi.mock("next/navigation", () => {
  return {
    useRouter: () => ({
      replace: (url: string) => {
        const queryIndex = url.indexOf("?");
        if (queryIndex !== -1) {
          mockSearchParams = new URLSearchParams(url.substring(queryIndex));
        } else {
          mockSearchParams = new URLSearchParams();
        }
      },
      push: vi.fn(),
    }),
    usePathname: () => "/teacher",
    useSearchParams: () => mockSearchParams,
  };
});

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return {
    ...actual,
    api: {
      ...actual.api,
      teacherLearners: vi.fn(),
      teacherAlerts: vi.fn(),
      teacherAssignments: vi.fn(),
      catalogCourses: vi.fn(),
      teacherLearnerProgress: vi.fn(),
      teacherLearnerEvidence: vi.fn(),
      catalogCourseLessons: vi.fn(),
      catalogLesson: vi.fn(),
      createTeacherAssignment: vi.fn(),
      updateTeacherAssignment: vi.fn(),
      resolveAlert: vi.fn(),
      createInterventionNote: vi.fn(),
    },
  };
});

const mockLearners: TeacherLearner[] = [
  { id: 1, name: "Learner One", email: "one@example.com" },
  { id: 2, name: "Learner Two", email: "two@example.com" },
];

const mockAlerts: SupervisionAlert[] = [
  {
    id: 101,
    learner: { id: 1, name: "Learner One" },
    rule_key: "inactivity_warning",
    severity: "warning",
    state: "open",
    evidence: [{ date: "2026-07-31" }],
    detected_at: "2026-07-31T10:00:00Z",
  },
];

const mockAssignments: TeacherAssignment[] = [
  {
    id: 201,
    learner: { id: 1, name: "Learner One" },
    lesson: { id: 1, title: "Basic Grammar" },
    status: "pending",
    created_at: "2026-07-31T10:00:00Z",
  },
  {
    id: 202,
    learner: { id: 2, name: "Learner Two" },
    lesson: { id: 2, title: "Advanced Vocabulary" },
    status: "completed",
    created_at: "2026-07-30T10:00:00Z",
  },
];

const mockCourses: CourseCard[] = [
  {
    id: 1,
    title: "English 101",
    slug: "english-101",
    description: "Intro course",
    level: { id: 1, name: "Beginner", slug: "beginner" },
    topic: { id: 1, name: "General", slug: "general" },
    lesson_count: 5,
    vocabulary_count: 50,
  },
];

describe("TeacherWorkspace", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockSearchParams = new URLSearchParams();
    vi.mocked(api.teacherLearners).mockResolvedValue(mockLearners);
    vi.mocked(api.teacherAlerts).mockResolvedValue(mockAlerts);
    vi.mocked(api.teacherAssignments).mockResolvedValue(mockAssignments);
    vi.mocked(api.catalogCourses).mockResolvedValue({ data: mockCourses, meta: { total: 1 } });
    vi.mocked(api.teacherLearnerProgress).mockResolvedValue({ completed_lessons: 3, recent_events: 15 });
    vi.mocked(api.teacherLearnerEvidence).mockResolvedValue([
      { id: 1, event_type: "quiz_completed", is_correct: true, hint_level: 0, duration_ms: 1200, occurred_at: "2026-07-31T12:00:00Z" },
    ]);
  });

  it("1. insight load: renders teacher workspace and loads learner evidence on inspection", async () => {
    render(<TeacherWorkspace />);

    await waitFor(() => {
      expect(screen.getByText("Teacher workspace")).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /Learner One/i })).toBeInTheDocument();
    });

    const learnerButton = screen.getByRole("button", { name: /Learner One/i });
    await act(async () => {
      fireEvent.click(learnerButton);
    });

    await waitFor(() => {
      expect(api.teacherLearnerProgress).toHaveBeenCalledWith(1);
      expect(api.teacherLearnerEvidence).toHaveBeenCalledWith(1);
      expect(screen.getByText("Learner One · bằng chứng gần đây")).toBeInTheDocument();
      expect(screen.getByText("quiz_completed")).toBeInTheDocument();
    });
  });

  it("2. invalid assignment: shows error message on invalid submission attempt", async () => {
    render(<TeacherWorkspace />);

    await waitFor(() => {
      expect(screen.getByRole("button", { name: /Learner One/i })).toBeInTheDocument();
    });

    const learnerButton = screen.getByRole("button", { name: /Learner One/i });
    await act(async () => {
      fireEvent.click(learnerButton);
    });

    await waitFor(() => {
      expect(screen.getByRole("button", { name: /Giao cho Learner One/i })).toBeInTheDocument();
    });
    const submitButton = screen.getByRole("button", { name: /Giao cho Learner One/i });

    await act(async () => {
      fireEvent.click(submitButton);
    });

    await waitFor(() => {
      expect(screen.getByText(/Vui lòng chọn lesson và từ vựng hợp lệ/)).toBeInTheDocument();
      expect(api.createTeacherAssignment).not.toHaveBeenCalled();
    });
  });

  it("3. completed outcome: renders completed assignments and filters by assignment_status", async () => {
    render(<TeacherWorkspace />);

    await waitFor(() => {
      expect(screen.getByText("Basic Grammar")).toBeInTheDocument();
      expect(screen.getByText("Advanced Vocabulary")).toBeInTheDocument();
    });

    const filterSelectHidden = screen.getByLabelText("Filter assignments");
    await act(async () => {
      fireEvent.change(filterSelectHidden, { target: { value: "completed" } });
    });

    await waitFor(() => {
      expect(screen.getByText("Advanced Vocabulary")).toBeInTheDocument();
      expect(screen.queryByText("Basic Grammar")).not.toBeInTheDocument();
    });
  });

  it("4. alert resolution: requires evidence inspection before resolving open alert", async () => {
    vi.mocked(api.resolveAlert).mockResolvedValue({
      ...mockAlerts[0],
      state: "resolved",
    });

    render(<TeacherWorkspace />);

    await waitFor(() => {
      expect(screen.getByText(/inactivity warning/i)).toBeInTheDocument();
    });

    // Click "Xem bằng chứng" when learner 1 is not yet selected
    const resolveBtn = screen.getByRole("button", { name: /Xem bằng chứng/i });
    await act(async () => {
      fireEvent.click(resolveBtn);
    });

    await waitFor(() => {
      expect(screen.getByText(/Hãy xem bằng chứng của học viên trước khi xác nhận xử lý/)).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /Xác nhận đã xử lý/i })).toBeInTheDocument();
    });

    const confirmBtn = screen.getByRole("button", { name: /Xác nhận đã xử lý/i });
    await act(async () => {
      fireEvent.click(confirmBtn);
    });

    await waitFor(() => {
      expect(api.resolveAlert).toHaveBeenCalledWith(101, "teacher_reviewed", "Đã xem bằng chứng và lập kế hoạch hỗ trợ.");
      expect(screen.getByText(/Đã đóng cảnh báo sau khi xem bằng chứng/)).toBeInTheDocument();
    });
  });
});
