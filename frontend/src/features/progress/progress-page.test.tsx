"use client";

import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ProgressPage } from "@/features/progress/progress-page";
import * as api from "@/lib/api";

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, fetchDashboard: vi.fn(), fetchMyProgress: vi.fn() };
});

const dashboard = {
  overview: { completed_lessons: 8, quiz_attempts: 3, average_score: 85 },
  recent_activity: [],
  recent_attempts: [{ id: 1, quiz_id: 2, score: 85 }],
};

describe("ProgressPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.fetchDashboard).mockResolvedValue(dashboard);
    vi.mocked(api.fetchMyProgress).mockResolvedValue([
      { id: 1, lesson_id: 4, lesson: { id: 4, title: "Greetings" } },
    ]);
  });

  it("renders the Laravel progress contract", async () => {
    render(<ProgressPage />);

    expect(await screen.findByText("Greetings")).toBeInTheDocument();
    expect(screen.getByText("8")).toBeInTheDocument();
    expect(screen.getByText("3")).toBeInTheDocument();
    expect(screen.getByText("85%")).toBeInTheDocument();
    expect(screen.getByText("Score: 85%")).toBeInTheDocument();
  });

  it("shows truthful empty states", async () => {
    vi.mocked(api.fetchDashboard).mockResolvedValue({
      overview: { completed_lessons: 0, quiz_attempts: 0, average_score: 0 },
      recent_activity: [],
      recent_attempts: [],
    });
    vi.mocked(api.fetchMyProgress).mockResolvedValue([]);

    render(<ProgressPage />);

    expect(await screen.findByText("No lessons completed yet.")).toBeInTheDocument();
    expect(screen.getByText("No quiz attempts yet.")).toBeInTheDocument();
  });

  it("retries both APIs after an error", async () => {
    vi.mocked(api.fetchDashboard).mockRejectedValueOnce(new Error("Network Error"));

    render(<ProgressPage />);

    fireEvent.click(await screen.findByText("Retry"));
    await waitFor(() => expect(api.fetchDashboard).toHaveBeenCalledTimes(2));
  });
});
