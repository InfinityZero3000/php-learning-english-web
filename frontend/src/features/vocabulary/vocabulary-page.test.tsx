"use client";

import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { VocabularyPage } from "@/features/vocabulary/vocabulary-page";
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
  return { ...actual, fetchBookmarks: vi.fn(), toggleBookmarkVocabulary: vi.fn() };
});

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1 } };

describe("VocabularyPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.fetchBookmarks).mockReset();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("shows loading then empty bookmarks", async () => {
    vi.mocked(api.fetchBookmarks).mockResolvedValue(emptyPage);

    render(<VocabularyPage />);

    expect(screen.getByText(/Loading vocabulary/i)).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText(/No bookmarked vocabulary yet/i)).toBeInTheDocument();
    });
  });

  it("shows empty state", async () => {
    vi.mocked(api.fetchBookmarks).mockResolvedValue(emptyPage);

    render(<VocabularyPage />);

    await waitFor(() => {
      expect(screen.getByText(/No bookmarked vocabulary yet/i)).toBeInTheDocument();
    });
  });

  it("shows bookmarks with words", async () => {
    vi.mocked(api.fetchBookmarks).mockResolvedValue({
      data: [
        {
          id: 1,
          bookmarkable_type: "vocabulary",
          bookmarkable_id: 1,
          vocabulary: { id: 1, word: "hello", meaning: "greeting word" },
          created_at: "2026-01-01T00:00:00Z",
        },
      ],
      meta: { current_page: 1, last_page: 1 },
    });

    render(<VocabularyPage />);

    await waitFor(() => {
      expect(screen.getByText("hello")).toBeInTheDocument();
    });
  });

  it("redirects to login on 401", async () => {
    vi.mocked(api.fetchBookmarks).mockRejectedValue(new api.ApiError(401, "Unauthenticated"));

    render(<VocabularyPage />);

    await waitFor(() => {
      expect(push).toHaveBeenCalledWith(expect.stringContaining("/login"));
    });
  });

  it("shows empty state after network error", async () => {
    vi.mocked(api.fetchBookmarks).mockRejectedValue(new Error("Network Error"));

    render(<VocabularyPage />);

    await waitFor(() => {
      expect(screen.getByText(/No bookmarked vocabulary yet/i)).toBeInTheDocument();
    });
  });
});