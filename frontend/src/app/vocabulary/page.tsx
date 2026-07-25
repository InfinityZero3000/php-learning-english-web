import { Suspense } from "react";
import { VocabularyPage } from "@/features/vocabulary/vocabulary-page";

export default function Page() {
  return (
    <Suspense>
      <VocabularyPage />
    </Suspense>
  );
}
