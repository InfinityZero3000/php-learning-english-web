"use client";

import { useCallback, useEffect, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { IconAlertTriangle, IconChecklist, IconRefresh, IconUsers } from "@tabler/icons-react";
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

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
      setMessage(reason instanceof Error ? reason.message : "Không thể tải bằng chứng học tập.");
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
      setMessage("Không thể tải danh sách bài học.");
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
      setMessage("Không thể tải từ vựng bài học.");
    }
  }

  async function createAssignment(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedLearner) {
      setMessage("Vui lòng chọn học viên trước khi giao bài.");
      return;
    }
    if (!lessonId || (target === "vocabulary" && !vocabularyId)) {
      setMessage("Vui lòng chọn lesson và từ vựng hợp lệ.");
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
      setMessage("Đã giao bài cho học viên.");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể giao bài.");
    }
  }

  async function saveNote(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedLearner) {
      setMessage("Vui lòng chọn học viên trước khi lưu ghi chú.");
      return;
    }
    if (!note.trim()) {
      setMessage("Vui lòng nhập nội dung ghi chú hỗ trợ.");
      return;
    }
    try {
      await api.createInterventionNote({ learner_id: selectedLearner, note: note.trim() });
      setNote("");
      setMessage("Đã lưu intervention note.");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể lưu ghi chú.");
    }
  }

  async function resolve(alert: SupervisionAlert) {
    if (selectedLearner !== alert.learner.id) {
      selectLearner(alert.learner.id);
      setMessage("Hãy xem bằng chứng của học viên trước khi xác nhận xử lý.");
      return;
    }
    try {
      const updated = await api.resolveAlert(alert.id, "teacher_reviewed", "Đã xem bằng chứng và lập kế hoạch hỗ trợ.");
      setAlerts((items) => items.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)));
      setMessage("Đã đóng cảnh báo sau khi xem bằng chứng.");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể xử lý cảnh báo.");
    }
  }

  async function updateAssignment(id: number, status: "pending" | "in_progress" | "cancelled") {
    try {
      const updated = await api.updateTeacherAssignment(id, { status });
      setAssignments((items) => items.map((item) => (item.id === id ? updated : item)));
      setMessage("Đã cập nhật assignment.");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể cập nhật assignment.");
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
        <p role="status" aria-live="polite" className="rounded-xl border-2 border-primary/20 bg-accent p-4 font-semibold">
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

function Status({ text, action }: { text: string; action?: React.ReactNode }) {
  return (
    <Card className="mx-auto max-w-xl p-10 text-center">
      <p role="status" className="font-bold">
        {text}
      </p>
      {action && <div className="mt-5">{action}</div>}
    </Card>
  );
}

function TeacherMetrics({
  totalLearners,
  openAlerts,
  pendingAssignments,
}: {
  totalLearners: number;
  openAlerts: number;
  pendingAssignments: number;
}) {
  return (
    <div className="grid gap-4 md:grid-cols-3">
      <Metric icon={IconUsers} value={totalLearners} label="Học viên" />
      <Metric icon={IconAlertTriangle} value={openAlerts} label="Cảnh báo mở" />
      <Metric icon={IconChecklist} value={pendingAssignments} label="Bài đang giao" />
    </div>
  );
}

function Metric({ icon: Icon, value, label }: { icon: typeof IconUsers; value: number; label: string }) {
  return (
    <Card className="flex items-center gap-4 p-5">
      <span aria-hidden="true" className="rounded-2xl bg-accent p-3 text-primary">
        <Icon />
      </span>
      <div>
        <p className="text-3xl font-bold tabular-nums">{value}</p>
        <p className="text-sm text-muted-foreground">{label}</p>
      </div>
    </Card>
  );
}

function MetricSmall({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-xl bg-muted p-4">
      <p className="text-2xl font-bold tabular-nums">{value}</p>
      <p className="text-xs text-muted-foreground">{label}</p>
    </div>
  );
}

function Empty({ text }: { text: string }) {
  return <p className="rounded-xl bg-muted p-5 text-center text-muted-foreground">{text}</p>;
}

function TeacherAlerts({
  alerts,
  selectedLearnerId,
  alertStateFilter,
  onFilterChange,
  onResolve,
}: {
  alerts: SupervisionAlert[];
  selectedLearnerId?: number;
  alertStateFilter: string;
  onFilterChange: (state: string) => void;
  onResolve: (alert: SupervisionAlert) => void;
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
        <CardTitle>Cảnh báo cần can thiệp</CardTitle>
        <div className="w-40">
          <Select
            aria-label="Filter alerts"
            value={alertStateFilter}
            onChange={(e) => onFilterChange(e.target.value)}
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="open">Đang mở (open)</option>
            <option value="resolved">Đã xử lý (resolved)</option>
          </Select>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {alerts.length === 0 ? (
          <Empty text="Chưa có cảnh báo." />
        ) : (
          alerts.map((alert) => (
            <article key={alert.id} className="rounded-2xl border-2 border-border p-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <span
                    className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${
                      alert.severity === "critical" ? "bg-red-100 text-red-800" : "bg-amber-100 text-amber-800"
                    }`}
                  >
                    {alert.severity}
                  </span>
                  <h3 className="mt-3 break-words font-display text-xl font-bold">
                    {alert.learner.name} · {alert.rule_key.replaceAll("_", " ")}
                  </h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {alert.evidence.length} snapshot · Trạng thái: {alert.state}
                  </p>
                </div>
                {alert.state === "open" && (
                  <Button
                    size="sm"
                    variant={selectedLearnerId === alert.learner.id ? "default" : "outline"}
                    onClick={() => onResolve(alert)}
                  >
                    {selectedLearnerId === alert.learner.id ? "Xác nhận đã xử lý" : "Xem bằng chứng"}
                  </Button>
                )}
              </div>
            </article>
          ))
        )}
      </CardContent>
    </Card>
  );
}

function TeacherLearners({
  learners,
  selectedLearnerId,
  onSelect,
}: {
  learners: TeacherLearner[];
  selectedLearnerId?: number;
  onSelect: (id: number) => void;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Học viên được giao</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {learners.map((item) => (
          <button
            key={item.id}
            onClick={() => onSelect(item.id)}
            className={`w-full rounded-2xl border-2 p-4 text-left hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary ${
              selectedLearnerId === item.id ? "border-primary bg-accent" : "border-transparent bg-muted"
            }`}
          >
            <span className="block font-bold">{item.name}</span>
            <span className="block truncate text-sm text-muted-foreground">{item.email}</span>
          </button>
        ))}
        {learners.length === 0 && <Empty text="Chưa có học viên được phân công." />}
      </CardContent>
    </Card>
  );
}

function TeacherEvidence({
  learnerName,
  progress,
  evidence,
}: {
  learnerName: string;
  progress?: TeacherLearnerProgress;
  evidence: LearningEvidence[];
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{learnerName} · bằng chứng gần đây</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="mb-4 grid grid-cols-2 gap-3">
          <MetricSmall label="Bài đã xong" value={progress?.completed_lessons ?? 0} />
          <MetricSmall label="Events · 7 ngày" value={progress?.recent_events ?? 0} />
        </div>
        <div className="max-h-80 space-y-2 overflow-y-auto">
          {evidence.map((item) => (
            <div key={item.id} className="rounded-xl border border-border p-3">
              <div className="flex justify-between gap-3">
                <strong>{item.event_type}</strong>
                <time dateTime={item.occurred_at} className="text-xs text-muted-foreground">
                  {new Intl.DateTimeFormat("vi-VN", { dateStyle: "short", timeStyle: "short" }).format(
                    new Date(item.occurred_at)
                  )}
                </time>
              </div>
              <p className="mt-1 text-sm text-muted-foreground">
                Correct: {item.is_correct == null ? "—" : item.is_correct ? "Có" : "Không"} · Hint: {item.hint_level ?? 0} ·{" "}
                {item.duration_ms ?? 0} ms
              </p>
            </div>
          ))}
          {evidence.length === 0 && <Empty text="Chưa có learning evidence." />}
        </div>
      </CardContent>
    </Card>
  );
}

function TeacherAssignmentForm({
  learnerName,
  courses,
  courseId,
  lessonId,
  target,
  vocabularyId,
  vocabularies,
  lessons,
  dueAt,
  instructions,
  onChooseCourse,
  onChooseLesson,
  onSetTarget,
  onSetVocabularyId,
  onSetDueAt,
  onSetInstructions,
  onSubmit,
}: {
  learnerName: string;
  courses: CourseCard[];
  courseId: string;
  lessonId: string;
  target: "lesson" | "vocabulary";
  vocabularyId: string;
  vocabularies: Array<{ id: number; word: string }>;
  lessons: LessonCard[];
  dueAt: string;
  instructions: string;
  onChooseCourse: (id: string) => void;
  onChooseLesson: (id: string) => void;
  onSetTarget: (target: "lesson" | "vocabulary") => void;
  onSetVocabularyId: (id: string) => void;
  onSetDueAt: (dueAt: string) => void;
  onSetInstructions: (instructions: string) => void;
  onSubmit: (e: React.FormEvent) => void;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Giao bài mới</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={onSubmit} className="space-y-3">
          <label className="block font-bold" htmlFor="assignment-course">
            Khóa học
          </label>
          <Select id="assignment-course" value={courseId} onChange={(e) => onChooseCourse(e.target.value)}>
            <option value="">Chọn khóa học</option>
            {courses.map((item) => (
              <option key={item.id} value={item.id}>
                {item.title}
              </option>
            ))}
          </Select>

          <label className="block font-bold" htmlFor="assignment-lesson">
            Lesson
          </label>
          <Select id="assignment-lesson" value={lessonId} onChange={(e) => onChooseLesson(e.target.value)}>
            <option value="">Chọn lesson</option>
            {lessons.map((item) => (
              <option key={item.id} value={item.id}>
                {item.title}
              </option>
            ))}
          </Select>

          <fieldset className="flex gap-4">
            <legend className="mb-2 font-bold">Mục tiêu</legend>
            <label className="flex items-center gap-1 cursor-pointer">
              <input
                type="radio"
                name="target"
                checked={target === "lesson"}
                onChange={() => onSetTarget("lesson")}
              />{" "}
              Lesson
            </label>
            <label className="flex items-center gap-1 cursor-pointer">
              <input
                type="radio"
                name="target"
                checked={target === "vocabulary"}
                onChange={() => onSetTarget("vocabulary")}
              />{" "}
              Vocabulary
            </label>
          </fieldset>

          {target === "vocabulary" && (
            <>
              <label className="block font-bold" htmlFor="assignment-word">
                Từ vựng
              </label>
              <Select id="assignment-word" value={vocabularyId} onChange={(e) => onSetVocabularyId(e.target.value)}>
                <option value="">Chọn từ</option>
                {vocabularies.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.word}
                  </option>
                ))}
              </Select>
            </>
          )}

          <label className="block font-bold" htmlFor="assignment-due">
            Hạn hoàn thành
          </label>
          <Input
            id="assignment-due"
            name="due_at"
            type="datetime-local"
            value={dueAt}
            onChange={(e) => onSetDueAt(e.target.value)}
          />

          <label className="block font-bold" htmlFor="assignment-instructions">
            Hướng dẫn
          </label>
          <Textarea
            id="assignment-instructions"
            name="instructions"
            value={instructions}
            onChange={(e) => onSetInstructions(e.target.value)}
          />

          <Button type="submit" className="w-full">
            Giao cho {learnerName}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function TeacherInterventionNoteForm({
  note,
  onSetNote,
  onSubmit,
}: {
  note: string;
  onSetNote: (note: string) => void;
  onSubmit: (e: React.FormEvent) => void;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Intervention note</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={onSubmit} className="space-y-3">
          <label className="block font-bold" htmlFor="intervention-note">
            Ghi chú hỗ trợ
          </label>
          <Textarea
            id="intervention-note"
            name="note"
            value={note}
            onChange={(e) => onSetNote(e.target.value)}
          />
          <Button type="submit" variant="outline">
            Lưu ghi chú
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function TeacherAssignmentsList({
  assignments,
  assignmentStatusFilter,
  onFilterChange,
  onUpdateStatus,
}: {
  assignments: TeacherAssignment[];
  assignmentStatusFilter: string;
  onFilterChange: (status: string) => void;
  onUpdateStatus: (id: number, status: "pending" | "in_progress" | "cancelled") => void;
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
        <CardTitle>Assignments</CardTitle>
        <div className="w-48">
          <Select
            aria-label="Filter assignments"
            value={assignmentStatusFilter}
            onChange={(e) => onFilterChange(e.target.value)}
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="pending">Đang chờ (pending)</option>
            <option value="in_progress">Đang hỗ trợ (in_progress)</option>
            <option value="completed">Đã hoàn thành (completed)</option>
            <option value="cancelled">Đã hủy (cancelled)</option>
          </Select>
        </div>
      </CardHeader>
      <CardContent className="grid gap-3 md:grid-cols-2">
        {assignments.map((item) => (
          <article key={item.id} className="rounded-2xl border-2 border-border p-4">
            <p className="font-bold">{item.learner.name}</p>
            <p className="mt-1 text-sm">{item.lesson?.title ?? item.vocabulary?.word ?? "Không có target"}</p>
            <span
              className={`mt-3 inline-block rounded-full px-3 py-1 text-xs font-bold uppercase ${
                item.status === "completed"
                  ? "bg-green-100 text-green-800"
                  : item.status === "in_progress"
                  ? "bg-blue-100 text-blue-800"
                  : item.status === "cancelled"
                  ? "bg-gray-100 text-gray-800"
                  : "bg-amber-100 text-amber-800"
              }`}
            >
              {item.status}
            </span>
            {(item.status === "pending" || item.status === "in_progress" || item.status === "cancelled") && (
              <div className="mt-4 flex flex-wrap gap-2">
                {item.status !== "in_progress" && (
                  <Button size="sm" variant="outline" onClick={() => onUpdateStatus(item.id, "in_progress")}>
                    Đang hỗ trợ
                  </Button>
                )}
                {item.status !== "cancelled" && (
                  <Button size="sm" variant="ghost" onClick={() => onUpdateStatus(item.id, "cancelled")}>
                    Hủy
                  </Button>
                )}
                {item.status === "cancelled" && (
                  <Button size="sm" variant="outline" onClick={() => onUpdateStatus(item.id, "pending")}>
                    Mở lại
                  </Button>
                )}
              </div>
            )}
          </article>
        ))}
        {assignments.length === 0 && <Empty text="Chưa có assignment." />}
      </CardContent>
    </Card>
  );
}
