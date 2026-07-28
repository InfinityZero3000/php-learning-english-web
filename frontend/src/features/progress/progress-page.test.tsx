"use client";

import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ProgressPage } from "@/features/progress/progress-page";
import * as api from "@/lib/api";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock("@/features/auth/auth-context", () => ({
  useAuth: () => ({ status: "authenticated" }),
}));

vi.mock("@/components/ui/toast", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, fetchDashboard: vi.fn(), fetchMyProgress: vi.fn() };
});

const emptyDashboard = {
  total_words: 0,
  total_bookmarks: 0,
  completed_lessons: 0,
  total_lessons: 0,
  recent_attempts: [],
  course_progress: [],
};

describe("ProgressPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("shows overview stats on success", async () => {
    vi.mocked(api.fetchDashboard).mockResolvedValue({
      total_words: 25,
      total_bookmarks: 5,
      completed_lessons: 8,
      total_lessons: 16,
      recent_attempts: [{ id: 1, score: 85 }],
      course_progress: [],
    });
    vi.mocked(api.fetchMyProgress).mockResolvedValue([
      { id: 1, progress_percent: 80 },
      { id: 2, progress_percent: 90 },
    ]);

    render(<ProgressPage />);

    await waitFor(() => {
      expect(screen.getByText("25")).toBeInTheDocument();
    });
    expect(screen.getAllByText("8").length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText("16")).toBeInTheDocument();
    expect(screen.getByText("50%")).toBeInTheDocument();
    expect(screen.getByText("Total Words")).toBeInTheDocument();
    expect(screen.getByText("Lessons Done")).toBeInTheDocument();
    expect(screen.getByText("Total Lessons")).toBeInTheDocument();
  });

  it("shows empty state with zero values", async () => {
    vi.mocked(api.fetchDashboard).mockResolvedValue(emptyDashboard);
    vi.mocked(api.fetchMyProgress).mockResolvedValue([]);

    render(<ProgressPage />);

    await waitFor(() => {
      expect(screen.getAllByText("0%").length).toBeGreaterThanOrEqual(1);
    });
    expect(screen.getByText("No quiz sessions yet.")).toBeInTheDocument();
    expect(screen.getByText("No courses started yet.")).toBeInTheDocument();
  });

  it("shows error message and retry button on API failure", async () => {
    vi.mocked(api.fetchDashboard).mockResolvedValue(emptyDashboard);
    vi.mocked(api.fetchMyProgress).mockRejectedValue(new Error("Network Error"));

    render(<ProgressPage />);

    await waitFor(() => {
      expect(screen.getByText(/Network Error/)).toBeInTheDocument();
    });
    expect(screen.getByText("Retry")).toBeInTheDocument();
  });

  it("shows dashboard data even when progress fails", async () => {
    vi.mocked(api.fetchDashboard).mockResolvedValue({
      total_words: 42,
      total_bookmarks: 3,
      completed_lessons: 5,
      total_lessons: 20,
      recent_attempts: [],
      course_progress: [],
    });
    vi.mocked(api.fetchMyProgress).mockRejectedValue(new Error("Network Error"));

    render(<ProgressPage />);

    await waitFor(() => {
      expect(screen.getByText("42")).toBeInTheDocument();
    });
    expect(screen.getByText("3")).toBeInTheDocument();
  });
});