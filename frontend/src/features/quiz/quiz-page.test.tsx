"use client";

import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { QuizPage } from "@/features/quiz/quiz-page";
import * as api from "@/lib/api";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));

vi.mock("@/features/auth/auth-context", () => ({
  useAuth: () => ({ status: "authenticated" }),
}));

vi.mock("@/components/ui/toast", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, fetchQuiz: vi.fn(), fetchQuizzes: vi.fn(), submitQuiz: vi.fn() };
});

describe("QuizPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.fetchQuizzes).mockResolvedValue([{ id: 1, title: "Test Quiz", questions_count: 1 }]);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("renders setup screen initially", () => {
    render(<QuizPage />);

    expect(screen.getByText(/Quiz Setup/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Choose a quiz/i)).toBeInTheDocument();
  });

  it("shows loading then quiz questions on successful start", async () => {
    vi.mocked(api.fetchQuiz).mockResolvedValue({
      id: 1,
      title: "Test Quiz",
      questions: [
        {
          id: 1,
          content: "What is 2+2?",
          answers: [
            { id: 1, content: "3" },
            { id: 2, content: "4" },
          ],
        },
      ],
    });

    render(<QuizPage />);

    const input = await screen.findByLabelText(/Choose a quiz/i);
    fireEvent.change(input, { target: { value: "1" } });
    fireEvent.click(screen.getByText(/Start Quiz/i));

    // Shows loading
    expect(screen.getByText(/Loading quiz/i)).toBeInTheDocument();

    // Then shows first question
    await waitFor(() => {
      expect(screen.getByText("What is 2+2?")).toBeInTheDocument();
    });
  });

  it("redirects to login on 401 when starting quiz", async () => {
    vi.mocked(api.fetchQuiz).mockRejectedValue(new api.ApiError(401, "Unauthenticated"));

    render(<QuizPage />);

    const input = await screen.findByLabelText(/Choose a quiz/i);
    fireEvent.change(input, { target: { value: "1" } });
    fireEvent.click(screen.getByText(/Start Quiz/i));

    await waitFor(() => {
      expect(push).toHaveBeenCalledWith(expect.stringContaining("/login"));
    });
  });

  it("stays on setup when quiz has no questions", async () => {
    vi.mocked(api.fetchQuiz).mockResolvedValue({
      id: 1,
      title: "Empty Quiz",
      questions: [],
    });

    render(<QuizPage />);

    const input = await screen.findByLabelText(/Choose a quiz/i);
    fireEvent.change(input, { target: { value: "1" } });
    fireEvent.click(screen.getByText(/Start Quiz/i));

    await waitFor(() => {
      expect(screen.getByText(/Quiz Setup/i)).toBeInTheDocument();
    });
  });
});
