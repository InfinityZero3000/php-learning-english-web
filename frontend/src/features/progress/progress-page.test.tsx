import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { api } from "@/lib/api";
import { ProgressPage } from "./progress-page";

const dashboard = {
  type: "progress" as const,
  overview: { completed_lessons: 4, quiz_attempts: 2, average_score: 84 },
  fsrs: {
    reviewed_cards: 12,
    due_count: 3,
    retention: 0.876,
    average_stability: 8.5,
    average_difficulty: 4.2,
    state_distribution: [
      { state: "new" as const, count: 2 },
      { state: "learning" as const, count: 3 },
      { state: "review" as const, count: 6 },
      { state: "relearning" as const, count: 1 },
    ],
    forecast: [
      { date: "2026-07-29", count: 3 },
      { date: "2026-07-30", count: 1 },
      { date: "2026-07-31", count: 0 },
      { date: "2026-08-01", count: 4 },
      { date: "2026-08-02", count: 2 },
      { date: "2026-08-03", count: 0 },
      { date: "2026-08-04", count: 1 },
    ],
  },
  courses: [{
    id: 7,
    title: "English foundations",
    enrollment_status: "active",
    completed_lessons: 1,
    total_lessons: 2,
    progress_percent: 50,
    units: [{
      id: 4,
      title: "Meet and greet",
      completed_lessons: 1,
      total_lessons: 2,
      lessons: [
        { id: 10, title: "Hello", completed: true },
        { id: 11, title: "Introduce yourself", completed: false },
      ],
    }],
  }],
  recent_sessions: [{
    id: 31,
    status: "active",
    started_at: "2026-07-29T11:55:00Z",
    completed_at: null,
    course: { id: 7, title: "English foundations" },
    lesson: { id: 11, title: "Introduce yourself" },
  }],
  quiz_performance: {
    attempts: 2,
    average_score: 84,
    correct_rate: 80,
    recent: [{
      id: 5,
      quiz_id: 8,
      quiz_title: "Checkpoint",
      lesson_title: "Hello",
      score: 90,
      completed_at: "2026-07-29T10:00:00Z",
    }],
  },
  listening: {
    sessions: 2,
    completed_sessions: 1,
    minutes_listened: 18,
    shadowing_attempts: 6,
    best_pronunciation_score: 88.5,
    recent: [{
      id: 9,
      title: "Coffee shop listening",
      watched_percentage: 100,
      shadowing_attempts: 4,
      best_pronunciation_score: 88.5,
      status: "completed",
    }],
  },
  recent_activity: [],
  recent_attempts: [],
};

describe("ProgressPage", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.spyOn(api, "supervisedDashboard").mockResolvedValue(dashboard);
    vi.spyOn(api, "enrollments").mockResolvedValue([]);
    vi.spyOn(api, "listeningProgress").mockResolvedValue([]);
  });

  it("renders server-owned FSRS, course, session, quiz, and listening insight", async () => {
    render(<ProgressPage />);

    expect(screen.getByText("Đang tải tiến độ…")).toBeInTheDocument();
    expect(await screen.findByRole("heading", { name: "Hành trình của bạn" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Ôn 3 thẻ ngay/ })).toHaveAttribute("href", "/review");
    expect(screen.getByText("88%")).toBeInTheDocument();
    expect(screen.getByText("8.5 ngày")).toBeInTheDocument();
    expect(screen.getByRole("list", { name: "Lịch thẻ đến hạn trong 7 ngày" })).toBeInTheDocument();
    expect(screen.getByText("29/07: 3 thẻ")).toHaveClass("sr-only");
    expect(screen.getByText("Thẻ mới")).toBeInTheDocument();
    expect(screen.getAllByText("Đang học").length).toBeGreaterThan(0);
    expect(screen.getByText("Ôn tập")).toBeInTheDocument();
    expect(screen.getByText("Học lại")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "English foundations" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Meet and greet" })).toBeInTheDocument();
    expect(screen.getByRole("listitem", { name: "Hello: Đã hoàn thành" })).toBeInTheDocument();
    expect(screen.getByRole("listitem", { name: "Introduce yourself: Chưa hoàn thành" })).toBeInTheDocument();
    expect(screen.getAllByText("Checkpoint").length).toBeGreaterThan(0);
    expect(screen.getByText("Coffee shop listening")).toBeInTheDocument();
    expect(api.enrollments).not.toHaveBeenCalled();
    expect(api.listeningProgress).not.toHaveBeenCalled();
  });

  it("shows honest null and empty states", async () => {
    vi.spyOn(api, "supervisedDashboard").mockResolvedValue({
      ...dashboard,
      fsrs: {
        ...dashboard.fsrs,
        due_count: 0,
        reviewed_cards: 0,
        retention: null,
        average_stability: null,
        average_difficulty: null,
        state_distribution: dashboard.fsrs.state_distribution.map((item) => ({ ...item, count: 0 })),
        forecast: dashboard.fsrs.forecast.map((item) => ({ ...item, count: 0 })),
      },
      courses: [],
      recent_sessions: [],
      quiz_performance: { attempts: 0, average_score: null, correct_rate: null, recent: [] },
      listening: { sessions: 0, completed_sessions: 0, minutes_listened: 0, shadowing_attempts: 0, best_pronunciation_score: null, recent: [] },
    });

    render(<ProgressPage />);

    expect(await screen.findAllByText("Chưa đủ dữ liệu")).toHaveLength(3);
    expect(screen.getByText("Chưa có khóa học đang theo học.")).toBeInTheDocument();
    expect(screen.getByText("Chưa có phiên học gần đây.")).toBeInTheDocument();
    expect(screen.getByText("Chưa có kết quả quiz.")).toBeInTheDocument();
    expect(screen.getByText("Chưa có bài luyện nghe.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Mở chế độ ôn tập" })).toHaveAttribute("href", "/review");
  });

  it("offers a retry after a dashboard failure", async () => {
    vi.spyOn(api, "supervisedDashboard")
      .mockRejectedValueOnce(new Error("Dashboard unavailable"))
      .mockResolvedValueOnce(dashboard);

    render(<ProgressPage />);

    expect(await screen.findByRole("alert")).toHaveTextContent("Dashboard unavailable");
    fireEvent.click(screen.getByRole("button", { name: "Thử lại" }));
    expect(await screen.findByRole("heading", { name: "Hành trình của bạn" })).toBeInTheDocument();
  });
});
