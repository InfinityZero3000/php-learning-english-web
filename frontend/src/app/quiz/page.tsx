import { Suspense } from "react";
import { QuizRoute } from "@/features/quiz/lesson-quiz";

export default function Page() {
  return <Suspense><QuizRoute /></Suspense>;
}
