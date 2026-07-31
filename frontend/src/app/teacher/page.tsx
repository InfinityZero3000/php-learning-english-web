import { Suspense } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { TeacherWorkspace } from "@/features/teacher/teacher-workspace";

export default function TeacherPage() {
  return (
    <Suspense fallback={<AppShellLoading label="Loading teacher workspace..." />}>
      <TeacherWorkspace />
    </Suspense>
  );
}
