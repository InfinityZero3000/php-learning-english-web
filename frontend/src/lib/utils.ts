export function cn(...classes: Array<string | false | null | undefined>) {
  return classes.filter(Boolean).join(" ");
}

export function formatPercent(value: unknown, fallback = "0%") {
  if (typeof value !== "number" || Number.isNaN(value)) return fallback;
  return `${value.toFixed(1)}%`;
}

export function formatDateTime(value?: string | null) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return new Intl.DateTimeFormat("vi-VN", {
    dateStyle: "medium",
    timeStyle: "short"
  }).format(date);
}

export function masteryLabel(mastery?: string | null) {
  const labels: Record<string, string> = {
    NEW: "New",
    LEARNING: "Learning",
    REVIEWING: "Reviewing",
    MASTERED: "Mastered"
  };
  return mastery ? labels[mastery] || mastery : "New";
}

export function difficultyLabel(value?: string | null) {
  const labels: Record<string, string> = {
    BEGINNER: "Beginner",
    INTERMEDIATE: "Intermediate",
    ADVANCED: "Advanced"
  };
  return value ? labels[value] || value : "Unknown";
}
