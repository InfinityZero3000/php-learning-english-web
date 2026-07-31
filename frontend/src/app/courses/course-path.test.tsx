import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { CoursePathView } from "./[id]/page";
import { api, lessonQuizzes } from "@/lib/api";
import type { CourseLearningPath } from "@/types/api";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));

const path: CourseLearningPath = {
  type: "course_learning_path",
  course: {
    id: 7,
    title: "English foundations",
    slug: "english-foundations",
    description: "A deliberate path from first words to confident conversation.",
    estimated_duration: 120,
    total_xp: 500,
  },
  enrollment: { id: 19, status: "active" },
  progress: { completed_lessons: 1, total_lessons: 3, percentage: 33 },
  units: [
    {
      id: 4,
      title: "Meet and greet",
      description: "Build a useful opening vocabulary.",
      sort_order: 0,
      completed_lessons: 1,
      total_lessons: 2,
      lessons: [
        {
          id: 10,
          title: "Hello",
          sort_order: 0,
          estimated_minutes: 8,
          xp_reward: 20,
          status: "completed",
          is_current: false,
          prerequisites: [],
          locked_reason: null,
        },
        {
          id: 11,
          title: "Introduce yourself",
          sort_order: 1,
          estimated_minutes: 12,
          xp_reward: 30,
          status: "locked",
          is_current: false,
          prerequisites: [{ id: 10, title: "Hello", completed: false }],
          locked_reason: "Complete Hello first.",
        },
      ],
    },
    {
      id: 5,
      title: "Real conversation",
      sort_order: 1,
      completed_lessons: 0,
      total_lessons: 1,
      lessons: [
        {
          id: 12,
          title: "Coffee shop",
          sort_order: 0,
          estimated_minutes: 15,
          xp_reward: 40,
          status: "available",
          is_current: true,
          prerequisites: [],
          locked_reason: null,
        },
      ],
    },
  ],
  next_action: { type: "start_lesson", enrollment_id: 19, lesson_id: 12 },
};

describe("learner course path", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    push.mockReset();
    vi.spyOn(api, "coursePath").mockResolvedValue(path);
    vi.spyOn(lessonQuizzes, "list").mockResolvedValue([]);
  });

  it("renders the server-owned unit path and starts the server-selected lesson", async () => {
    const start = vi.spyOn(api, "startSession").mockResolvedValue({ id: 77, course_id: 7, lesson_id: 12, status: "active" });
    render(<CoursePathView courseId={7} />);

    expect(screen.getByText("Đang tải lộ trình…")).toBeInTheDocument();
    expect(await screen.findByRole("heading", { name: "English foundations" })).toBeInTheDocument();
    expect(screen.getByText("33%")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Meet and greet" })).toBeInTheDocument();
    expect(screen.getByText("Complete Hello first.")).toBeInTheDocument();
    const current = screen.getByRole("article", { name: "Coffee shop" });
    expect(within(current).getByText("Bài hiện tại")).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Tiếp tục học" }));
    await waitFor(() => expect(start).toHaveBeenCalledWith(19, 12));
    expect(push).toHaveBeenCalledWith("/session/77");
  });

  it("resumes a session and enrolls without deriving eligibility in the client", async () => {
    vi.spyOn(api, "coursePath").mockResolvedValueOnce({
      ...path,
      next_action: { type: "resume_session", session_id: 88, lesson_id: 12 },
    });
    const start = vi.spyOn(api, "startSession");
    const { unmount } = render(<CoursePathView courseId={7} />);
    fireEvent.click(await screen.findByRole("button", { name: "Tiếp tục phiên đang học" }));
    expect(push).toHaveBeenCalledWith("/session/88");
    expect(start).not.toHaveBeenCalled();
    unmount();

    vi.spyOn(api, "coursePath")
      .mockResolvedValueOnce({ ...path, enrollment: null, next_action: null })
      .mockResolvedValueOnce(path);
    const enroll = vi.spyOn(api, "enroll").mockResolvedValue({ id: 19, course_id: 7, title: "English foundations", status: "active" });
    render(<CoursePathView courseId={7} />);
    fireEvent.click(await screen.findByRole("button", { name: "Tham gia khóa học" }));
    await waitFor(() => expect(enroll).toHaveBeenCalledWith(7));
    expect(await screen.findByRole("button", { name: "Tiếp tục học" })).toBeInTheDocument();
  });

  it("shows empty and retryable error states", async () => {
    vi.spyOn(api, "coursePath")
      .mockRejectedValueOnce(new Error("Path unavailable"))
      .mockResolvedValueOnce({ ...path, units: [], progress: { completed_lessons: 0, total_lessons: 0, percentage: 0 }, next_action: null });
    render(<CoursePathView courseId={7} />);

    expect(await screen.findByRole("alert")).toHaveTextContent("Path unavailable");
    fireEvent.click(screen.getByRole("button", { name: "Thử lại" }));
    expect(await screen.findByText("Khóa học chưa có bài học đã xuất bản.")).toBeInTheDocument();
  });

  it("does not infer completion when an active enrollment has no next action", async () => {
    vi.spyOn(api, "coursePath").mockResolvedValue({
      ...path,
      next_action: null,
      enrollment: { id: 19, status: "active" },
      progress: { completed_lessons: 0, total_lessons: 3, percentage: 0 },
    });
    render(<CoursePathView courseId={7} />);

    expect(await screen.findByRole("heading", { name: "Hiện chưa có bài học khả dụng" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Chưa có bài khả dụng" })).toBeDisabled();
    expect(screen.queryByText("Bạn đã đi hết lộ trình hiện tại")).not.toBeInTheDocument();
  });
});
