import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError, api, type FsrsCard } from "@/lib/api";
import ReviewPage from "./page";

const card: FsrsCard = {
  type: "fsrs_card",
  id: 3,
  vocabulary_id: 9,
  word: "resilient",
  meaning: "kiên cường",
  definition: "able to recover quickly",
  state: "learning",
  revision: 4,
};

const preview = {
  type: "fsrs_preview" as const,
  card_id: 3,
  base_revision: 4,
  generated_at: "2026-07-29T12:00:00Z",
  ratings: [
    { rating: "again" as const, due_at: "2026-07-29T12:01:00Z", interval_seconds: 60 },
    { rating: "hard" as const, due_at: "2026-07-29T12:10:00Z", interval_seconds: 600 },
    { rating: "good" as const, due_at: "2026-07-30T12:00:00Z", interval_seconds: 86400 },
    { rating: "easy" as const, due_at: "2026-08-01T12:00:00Z", interval_seconds: 259200 },
  ],
};

describe("ReviewPage", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.spyOn(api, "fsrsDue").mockResolvedValue({ data: [card], meta: { total: 1 } });
    vi.spyOn(api, "fsrsPreview").mockResolvedValue(preview);
    vi.spyOn(api, "fsrsReview").mockResolvedValue(card);
  });

  it("loads preview after reveal and renders four precise intervals", async () => {
    let resolvePreview!: (value: typeof preview) => void;
    vi.spyOn(api, "fsrsPreview").mockReturnValue(new Promise((resolve) => { resolvePreview = resolve; }));
    render(<ReviewPage />);

    fireEvent.click(await screen.findByRole("button", { name: /resilient/ }));
    expect(screen.getAllByText("Đang tính…")).toHaveLength(4);
    expect(api.fsrsPreview).toHaveBeenCalledWith(card);

    resolvePreview(preview);
    expect(await screen.findByText("1 phút")).toBeInTheDocument();
    expect(screen.getByText("10 phút")).toBeInTheDocument();
    expect(screen.getByText("1 ngày")).toBeInTheDocument();
    expect(screen.getByText("3 ngày")).toBeInTheDocument();
  });

  it("supports Space to reveal and keys 1–4 to rate", async () => {
    render(<ReviewPage />);

    await screen.findByText("resilient");
    fireEvent.keyDown(window, { code: "Space" });
    expect(await screen.findByText("1 ngày")).toBeInTheDocument();
    fireEvent.keyDown(window, { code: "Digit3" });

    await waitFor(() => expect(api.fsrsReview).toHaveBeenCalledWith(card, "good"));
    expect(await screen.findByRole("heading", { name: "Đã ôn xong hôm nay" })).toBeInTheDocument();
  });

  it("does not capture shortcuts from interactive controls", async () => {
    render(<><button type="button">Mở menu</button><ReviewPage /></>);

    await screen.findByText("resilient");
    const menu = screen.getByRole("button", { name: "Mở menu" });
    fireEvent.keyDown(menu, { code: "Space" });
    expect(screen.queryByText("Đáp án")).not.toBeInTheDocument();
    expect(api.fsrsPreview).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole("button", { name: /resilient/ }));
    await screen.findByText("1 ngày");
    fireEvent.keyDown(menu, { code: "Digit3" });
    expect(api.fsrsReview).not.toHaveBeenCalled();
  });

  it("keeps rating enabled when preview fails", async () => {
    vi.spyOn(api, "fsrsPreview").mockRejectedValue(new Error("Preview unavailable"));
    render(<ReviewPage />);

    fireEvent.click(await screen.findByRole("button", { name: /resilient/ }));
    expect(await screen.findByText("Chưa có dự đoán")).toBeInTheDocument();
    expect(screen.getByText(/Bạn vẫn có thể tự đánh giá/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /Tốt/ }));

    await waitFor(() => expect(api.fsrsReview).toHaveBeenCalledWith(card, "good"));
  });

  it("refreshes the queue after a stale revision conflict", async () => {
    const fresh = { ...card, word: "renewed", revision: 5 };
    vi.spyOn(api, "fsrsDue")
      .mockResolvedValueOnce({ data: [card], meta: { total: 1 } })
      .mockResolvedValueOnce({ data: [fresh], meta: { total: 1 } });
    vi.spyOn(api, "fsrsReview").mockRejectedValue(new ApiError(409, "Review state changed"));
    render(<ReviewPage />);

    fireEvent.click(await screen.findByRole("button", { name: /resilient/ }));
    fireEvent.click(await screen.findByRole("button", { name: /Tốt/ }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Thẻ đã thay đổi");
    expect(await screen.findByText("renewed")).toBeInTheDocument();
    expect(api.fsrsDue).toHaveBeenCalledTimes(2);
  });
});
