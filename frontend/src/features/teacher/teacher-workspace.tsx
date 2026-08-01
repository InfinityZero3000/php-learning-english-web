"use client";

import { useCallback, useEffect, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { IconAlertCircle, IconCheck, IconInfoCircle, IconRefresh } from "@tabler/icons-react";
import {
  api,
  type CourseCard,
  type LearningEvidence,
  type LessonCard,
  type SupervisionAlert,
  type TeacherAssignment,
  type TeacherLearner,
  type TeacherLearnerProgress,
} from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Status, TeacherMetrics, TeacherAlerts, TeacherLearners, TeacherAssignmentsList } from "./teacher-workspace-lists";
import { TeacherEvidence, TeacherAssignmentForm, TeacherInterventionNoteForm } from "./teacher-workspace-forms";

export function TeacherWorkspace() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const learnerParam = params.get("learner");
  const urlLearnerId = learnerParam && !isNaN(Number(learnerParam)) ? Number(learnerParam) : undefined;
  const urlAssignmentStatus = (params.get("assignment_status") as "all" | "pending" | "in_progress" | "completed" | "cancelled") || "all";
  const urlAlertState = (params.get("alert_state") as "all" | "open" | "resolved") || "all";

  const [activeLearnerId, setActiveLearnerId] = useState<number | undefined>();
  const [assignmentStatusFilter, setAssignmentStatusFilter] = useState<string>(urlAssignmentStatus);
  const [alertStateFilter, setAlertStateFilter] = useState<string>(urlAlertState);

  const selectedLearner = activeLearnerId ?? urlLearnerId;

  const [learners, setLearners] = useState<TeacherLearner[]>([]);
  const [alerts, setAlerts] = useState<SupervisionAlert[]>([]);
  const [assignments, setAssignments] = useState<TeacherAssignment[]>([]);
  const [courses, setCourses] = useState<CourseCard[]>([]);
  const [progress, setProgress] = useState<TeacherLearnerProgress>();
  const [evidence, setEvidence] = useState<LearningEvidence[]>([]);
  const [lessons, setLessons] = useState<LessonCard[]>([]);
  const [courseId, setCourseId] = useState("");
  const [lessonId, setLessonId] = useState("");
  const [target, setTarget] = useState<"lesson" | "vocabulary">("lesson");
  const [vocabularyId, setVocabularyId] = useState("");
  const [vocabularies, setVocabularies] = useState<Array<{ id: number; word: string }>>([]);
  const [instructions, setInstructions] = useState("");
  const [dueAt, setDueAt] = useState("");
  const [note, setNote] = useState("");
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");
  const [messageType, setMessageType] = useState<"error" | "success" | "info">("info");

  const updateQuery = useCallback(
    (changes: Record<string, string | null>) => {
      const next = new URLSearchParams(params.toString());
      Object.entries(changes).forEach(([key, value]) => {
        if (value && value !== "all") {
          next.set(key, value);
        } else {
          next.delete(key);
        }
      });
      const queryString = next.toString();
      router.replace(`${pathname}${queryString ? `?${queryString}` : ""}`);
    },
    [params, pathname, router]
  );

  const selectLearner = useCallback(
    (id: number) => {
      setActiveLearnerId(id);
      updateQuery({ learner: String(id) });
    },
    [updateQuery]
  );

  const filterAlerts = useCallback(
    (nextState: string) => {
      setAlertStateFilter(nextState);
      updateQuery({ alert_state: nextState });
    },
    [updateQuery]
  );

  const filterAssignments = useCallback(
    (nextStatus: string) => {
      setAssignmentStatusFilter(nextStatus);
      updateQuery({ assignment_status: nextStatus });
    },
    [updateQuery]
  );

  const notify = (text: string, type: "error" | "success" | "info" = "info") => {
    setMessage(text);
    setMessageType(type);
  };

  const load = useCallback(async () => {
    setState("loading");
    setMessage("");
    try {
      const [nextLearners, nextAlerts, work, catalog] = await Promise.all([
        api.teacherLearners(),
        api.teacherAlerts(),
        api.teacherAssignments(),
        api.catalogCourses(),
      ]);
      setLearners(nextLearners);
      setAlerts(nextAlerts);
      setAssignments(work);
      setCourses(catalog.data);
      setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải teacher workspace.");
      setMessageType("error");
      setState("error");
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const inspectLearner = useCallback(async (id: number) => {
    try {
      const [nextProgress, nextEvidence] = await Promise.all([
        api.teacherLearnerProgress(id),
        api.teacherLearnerEvidence(id),
      ]);
      setProgress(nextProgress);
      setEvidence(nextEvidence);
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : "Không thể tải bằng chứng học tập.", "error");
    }
  }, []);

  useEffect(() => {
    if (selectedLearner) {
      void inspectLearner(selectedLearner);
    } else {
      setProgress(undefined);
      setEvidence([]);
    }
  }, [inspectLearner, selectedLearner]);

  async function chooseCourse(value: string) {
    setCourseId(value);
    setLessonId("");
    setVocabularies([]);
    if (!value) return;
    try {
      const response = await api.catalogCourseLessons(Number(value));
      setLessons(response.data);
    } catch {
      notify("Không thể tải danh sách bài học.", "error");
    }
  }

  async function chooseLesson(value: string) {
    setLessonId(value);
    setVocabularyId("");
    if (!value) return;
    try {
      const lesson = await api.catalogLesson(Number(value));
      setVocabularies(lesson.vocabularies ?? []);
    } catch {
      notify("Không thể tải từ vựng bài học.", "error");
    }
  }

  async function createAssignment(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedLearner) {
      notify("Vui lòng chọn học viên trước khi giao bài.", "error");
      return;
    }
    if (!lessonId || (target === "vocabulary" && !vocabularyId)) {
      notify("Vui lòng chọn lesson và từ vựng hợp lệ.", "error");
      return;
    }
    try {
      const created = await api.createTeacherAssignment({
        learner_id: selectedLearner,
        ...(target === "lesson" ? { lesson_id: Number(lessonId) } : { vocabulary_id: Number(vocabularyId) }),
        instructions: instructions || undefined,
        due_at: dueAt ? new Date(dueAt).toISOString() : undefined,
      });
      setAssignments((current) => [created, ...current]);
      setInstructions("");
      setDueAt("");
      notify("Đã giao bài cho học viên.", "success");
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : "Không thể giao bài.", "error");
    }
  }

  async function saveNote(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedLearner) {
      notify("Vui lòng chọn học viên trước khi lưu ghi chú.", "error");
      return;
    }
    if (!note.trim()) {
      notify("Vui lòng nhập nội dung ghi chú hỗ trợ.", "error");
      return;
    }
    try {
      await api.createInterventionNote({ learner_id: selectedLearner, note: note.trim() });
      setNote("");
      notify("Đã lưu intervention note.", "success");
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : "Không thể lưu ghi chú.", "error");
    }
  }

  async function resolve(alert: SupervisionAlert) {
    if (selectedLearner !== alert.learner.id) {
      selectLearner(alert.learner.id);
      notify("Hãy xem bằng chứng của học viên trước khi xác nhận xử lý.", "info");
      return;
    }
    try {
      const updated = await api.resolveAlert(alert.id, "teacher_reviewed", "Đã xem bằng chứng và lập kế hoạch hỗ trợ.");
      setAlerts((items) => items.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)));
      notify("Đã đóng cảnh báo sau khi xem bằng chứng.", "success");
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : "Không thể xử lý cảnh báo.", "error");
    }
  }

  async function updateAssignment(id: number, status: "pending" | "in_progress" | "cancelled") {
    try {
      const updated = await api.updateTeacherAssignment(id, { status });
      setAssignments((items) => items.map((item) => (item.id === id ? updated : item)));
      notify("Đã cập nhật assignment.", "success");
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : "Không thể cập nhật assignment.", "error");
    }
  }

  if (state === "loading") return <Status text="Đang tải teacher workspace…" />;
  if (state === "error") {
    return (
      <Status
        text={message}
        action={
          <Button onClick={load}>
            <IconRefresh className="mr-2 h-5 w-5" />
            Thử lại
          </Button>
        }
      />
    );
  }

  const activeLearner = learners.find((item) => item.id === selectedLearner);
  const filteredAlerts = alerts.filter((alert) => alertStateFilter === "all" || alert.state === alertStateFilter);
  const filteredAssignments = assignments.filter(
    (item) => assignmentStatusFilter === "all" || item.status === assignmentStatusFilter
  );

  return (
    <div className="mx-auto max-w-7xl space-y-7">
      <header>
        <p className="font-bold uppercase tracking-[.14em] text-primary">Supervised learning</p>
        <h2 className="font-display text-4xl font-bold text-balance">Teacher workspace</h2>
        <p className="mt-2 text-muted-foreground">Bằng chứng trước, can thiệp sau — chỉ trong phạm vi học viên được phân công.</p>
      </header>

      {message && (
        <p
          role="status"
          aria-live="polite"
          className={`flex items-center gap-2 rounded-xl border-2 p-4 font-semibold ${
            messageType === "error"
              ? "border-red-300 bg-red-50 text-red-800"
              : messageType === "success"
              ? "border-green-300 bg-green-50 text-green-800"
              : "border-amber-300 bg-amber-50 text-amber-800"
          }`}
        >
          {messageType === "error" ? (
            <IconAlertCircle className="h-5 w-5 shrink-0" />
          ) : messageType === "success" ? (
            <IconCheck className="h-5 w-5 shrink-0" />
          ) : (
            <IconInfoCircle className="h-5 w-5 shrink-0" />
          )}
          {message}
        </p>
      )}

      <TeacherMetrics
        totalLearners={learners.length}
        openAlerts={alerts.filter((item) => item.state === "open").length}
        pendingAssignments={assignments.filter((item) => item.status !== "completed").length}
      />

      <div className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
        <TeacherAlerts
          alerts={filteredAlerts}
          selectedLearnerId={selectedLearner}
          alertStateFilter={alertStateFilter}
          onFilterChange={filterAlerts}
          onResolve={resolve}
        />
        <TeacherLearners
          learners={learners}
          selectedLearnerId={selectedLearner}
          onSelect={selectLearner}
        />
      </div>

      {activeLearner && (
        <div className="grid gap-6 xl:grid-cols-2">
          <TeacherEvidence
            learnerName={activeLearner.name}
            progress={progress}
            evidence={evidence}
          />
          <div className="space-y-6">
            <TeacherAssignmentForm
              learnerName={activeLearner.name}
              courses={courses}
              courseId={courseId}
              lessonId={lessonId}
              target={target}
              vocabularyId={vocabularyId}
              vocabularies={vocabularies}
              lessons={lessons}
              dueAt={dueAt}
              instructions={instructions}
              onChooseCourse={chooseCourse}
              onChooseLesson={chooseLesson}
              onSetTarget={setTarget}
              onSetVocabularyId={setVocabularyId}
              onSetDueAt={setDueAt}
              onSetInstructions={setInstructions}
              onSubmit={createAssignment}
            />
            <TeacherInterventionNoteForm
              note={note}
              onSetNote={setNote}
              onSubmit={saveNote}
            />
          </div>
        </div>
      )}

      <TeacherAssignmentsList
        assignments={filteredAssignments}
        assignmentStatusFilter={assignmentStatusFilter}
        onFilterChange={filterAssignments}
        onUpdateStatus={updateAssignment}
      />
    </div>
  );
}
