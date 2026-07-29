"use client";

import { useEffect, useState } from "react";
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

export default function TeacherPage() {
  const [learners, setLearners] = useState<TeacherLearner[]>([]);
  const [alerts, setAlerts] = useState<SupervisionAlert[]>([]);
  const [assignments, setAssignments] = useState<TeacherAssignment[]>([]);
  const [courses, setCourses] = useState<CourseCard[]>([]);
  const [selectedLearner, setSelectedLearner] = useState<number>();
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

  async function load() {
    setState("loading");
    setMessage("");
    try {
      const [nextLearners, nextAlerts, work, catalog] = await Promise.all([
        api.teacherLearners(), api.teacherAlerts(), api.teacherAssignments(), api.catalogCourses(),
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
  }

  useEffect(() => { void load(); }, []);

  async function inspectLearner(id: number) {
    setSelectedLearner(id);
    setMessage("");
    try {
      const [nextProgress, nextEvidence] = await Promise.all([
        api.teacherLearnerProgress(id), api.teacherLearnerEvidence(id),
      ]);
      setProgress(nextProgress);
      setEvidence(nextEvidence);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải bằng chứng học tập.");
    }
  }

  async function chooseCourse(value: string) {
    setCourseId(value);
    setLessonId("");
    setVocabularies([]);
    if (!value) return;
    const response = await api.catalogCourseLessons(Number(value));
    setLessons(response.data);
  }

  async function chooseLesson(value: string) {
    setLessonId(value);
    setVocabularyId("");
    if (!value) return;
    const lesson = await api.catalogLesson(Number(value));
    setVocabularies(lesson.vocabularies ?? []);
  }

  async function createAssignment(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedLearner || !lessonId || (target === "vocabulary" && !vocabularyId)) return;
    try {
      const created = await api.createTeacherAssignment({
        learner_id: selectedLearner,
        ...(target === "lesson" ? { lesson_id: Number(lessonId) } : { vocabulary_id: Number(vocabularyId) }),
        instructions: instructions || undefined,
        due_at: dueAt ? new Date(dueAt).toISOString() : undefined,
      });
      setAssignments((current) => [created, ...current]);
      setInstructions("");
      setMessage("Đã giao bài cho học viên.");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể giao bài.");
    }
  }

  async function saveNote(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedLearner || !note.trim()) return;
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
      await inspectLearner(alert.learner.id);
      setMessage("Hãy xem bằng chứng của học viên trước khi xác nhận xử lý.");
      return;
    }
    try {
      const updated = await api.resolveAlert(alert.id, "teacher_reviewed", "Đã xem bằng chứng và lập kế hoạch hỗ trợ.");
      setAlerts((items) => items.map((item) => item.id === updated.id ? { ...item, ...updated } : item));
      setMessage("Đã đóng cảnh báo sau khi xem bằng chứng.");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể xử lý cảnh báo.");
    }
  }

  async function updateAssignment(id: number, status: "pending" | "in_progress" | "cancelled") {
    try {
      const updated = await api.updateTeacherAssignment(id, { status });
      setAssignments((items) => items.map((item) => item.id === id ? updated : item));
      setMessage("Đã cập nhật assignment.");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể cập nhật assignment.");
    }
  }

  if (state === "loading") return <Status text="Đang tải teacher workspace…" />;
  if (state === "error") return <Status text={message} action={<Button onClick={load}><IconRefresh className="h-5 w-5" />Thử lại</Button>} />;

  const learner = learners.find((item) => item.id === selectedLearner);
  return <div className="mx-auto max-w-7xl space-y-7">
    <header><p className="font-bold uppercase tracking-[.14em] text-primary">Supervised learning</p><h2 className="font-display text-4xl font-bold text-balance">Teacher workspace</h2><p className="mt-2 text-muted-foreground">Bằng chứng trước, can thiệp sau — chỉ trong phạm vi học viên được phân công.</p></header>
    {message && <p role="status" aria-live="polite" className="rounded-xl border-2 border-primary/20 bg-accent p-4 font-semibold">{message}</p>}
    <div className="grid gap-4 md:grid-cols-3">
      <Metric icon={IconUsers} value={learners.length} label="Học viên" />
      <Metric icon={IconAlertTriangle} value={alerts.filter((item) => item.state === "open").length} label="Cảnh báo mở" />
      <Metric icon={IconChecklist} value={assignments.filter((item) => item.status !== "completed").length} label="Bài đang giao" />
    </div>

    <div className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
      <Card><CardHeader><CardTitle>Cảnh báo cần can thiệp</CardTitle></CardHeader><CardContent className="space-y-3">
        {alerts.length === 0 ? <Empty text="Chưa có cảnh báo." /> : alerts.map((alert) => <article key={alert.id} className="rounded-2xl border-2 border-border p-5">
          <div className="flex flex-wrap items-start justify-between gap-3"><div className="min-w-0"><span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${alert.severity === "critical" ? "bg-red-100 text-red-800" : "bg-amber-100 text-amber-800"}`}>{alert.severity}</span><h3 className="mt-3 break-words font-display text-xl font-bold">{alert.learner.name} · {alert.rule_key.replaceAll("_", " ")}</h3><p className="mt-1 text-sm text-muted-foreground">{alert.evidence.length} snapshot · {alert.state}</p></div>
            {alert.state === "open" && <Button size="sm" variant={selectedLearner === alert.learner.id ? "default" : "outline"} onClick={() => resolve(alert)}>{selectedLearner === alert.learner.id ? "Xác nhận đã xử lý" : "Xem bằng chứng"}</Button>}
          </div>
        </article>)}
      </CardContent></Card>

      <Card><CardHeader><CardTitle>Học viên được giao</CardTitle></CardHeader><CardContent className="space-y-3">
        {learners.map((item) => <button key={item.id} onClick={() => inspectLearner(item.id)} className={`w-full rounded-2xl border-2 p-4 text-left hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary ${selectedLearner === item.id ? "border-primary bg-accent" : "border-transparent bg-muted"}`}><span className="block font-bold">{item.name}</span><span className="block truncate text-sm text-muted-foreground">{item.email}</span></button>)}
        {learners.length === 0 && <Empty text="Chưa có học viên được phân công." />}
      </CardContent></Card>
    </div>

    {learner && <div className="grid gap-6 xl:grid-cols-2">
      <Card><CardHeader><CardTitle>{learner.name} · bằng chứng gần đây</CardTitle></CardHeader><CardContent>
        <div className="mb-4 grid grid-cols-2 gap-3"><MetricSmall label="Bài đã xong" value={progress?.completed_lessons ?? 0} /><MetricSmall label="Events · 7 ngày" value={progress?.recent_events ?? 0} /></div>
        <div className="max-h-80 space-y-2 overflow-y-auto">
          {evidence.map((item) => <div key={item.id} className="rounded-xl border border-border p-3"><div className="flex justify-between gap-3"><strong>{item.event_type}</strong><time dateTime={item.occurred_at} className="text-xs text-muted-foreground">{new Intl.DateTimeFormat("vi-VN", { dateStyle: "short", timeStyle: "short" }).format(new Date(item.occurred_at))}</time></div><p className="mt-1 text-sm text-muted-foreground">Correct: {item.is_correct == null ? "—" : item.is_correct ? "Có" : "Không"} · Hint: {item.hint_level ?? 0} · {item.duration_ms ?? 0} ms</p></div>)}
          {evidence.length === 0 && <Empty text="Chưa có learning evidence." />}
        </div>
      </CardContent></Card>

      <div className="space-y-6">
        <Card><CardHeader><CardTitle>Giao bài mới</CardTitle></CardHeader><CardContent><form onSubmit={createAssignment} className="space-y-3">
          <label className="block font-bold" htmlFor="assignment-course">Khóa học</label><Select id="assignment-course" value={courseId} onChange={(event) => chooseCourse(event.target.value)} required><option value="">Chọn khóa học</option>{courses.map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</Select>
          <label className="block font-bold" htmlFor="assignment-lesson">Lesson</label><Select id="assignment-lesson" value={lessonId} onChange={(event) => chooseLesson(event.target.value)} required><option value="">Chọn lesson</option>{lessons.map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</Select>
          <fieldset className="flex gap-4"><legend className="mb-2 font-bold">Mục tiêu</legend><label><input type="radio" name="target" checked={target === "lesson"} onChange={() => setTarget("lesson")} /> Lesson</label><label><input type="radio" name="target" checked={target === "vocabulary"} onChange={() => setTarget("vocabulary")} /> Vocabulary</label></fieldset>
          {target === "vocabulary" && <><label className="block font-bold" htmlFor="assignment-word">Từ vựng</label><Select id="assignment-word" value={vocabularyId} onChange={(event) => setVocabularyId(event.target.value)} required><option value="">Chọn từ</option>{vocabularies.map((item) => <option key={item.id} value={item.id}>{item.word}</option>)}</Select></>}
          <label className="block font-bold" htmlFor="assignment-due">Hạn hoàn thành</label><Input id="assignment-due" name="due_at" type="datetime-local" value={dueAt} onChange={(event) => setDueAt(event.target.value)} />
          <label className="block font-bold" htmlFor="assignment-instructions">Hướng dẫn</label><Textarea id="assignment-instructions" name="instructions" value={instructions} onChange={(event) => setInstructions(event.target.value)} />
          <Button className="w-full">Giao cho {learner.name}</Button>
        </form></CardContent></Card>
        <Card><CardHeader><CardTitle>Intervention note</CardTitle></CardHeader><CardContent><form onSubmit={saveNote} className="space-y-3"><label className="block font-bold" htmlFor="intervention-note">Ghi chú hỗ trợ</label><Textarea id="intervention-note" name="note" value={note} onChange={(event) => setNote(event.target.value)} required /><Button variant="outline">Lưu ghi chú</Button></form></CardContent></Card>
      </div>
    </div>}

    <Card><CardHeader><CardTitle>Assignments</CardTitle></CardHeader><CardContent className="grid gap-3 md:grid-cols-2">
      {assignments.map((item) => <article key={item.id} className="rounded-2xl border-2 border-border p-4"><p className="font-bold">{item.learner.name}</p><p className="mt-1 text-sm">{item.lesson?.title ?? item.vocabulary?.word ?? "Không có target"}</p><span className="mt-3 inline-block rounded-full bg-muted px-3 py-1 text-xs font-bold uppercase">{item.status}</span>
        {(item.status === "pending" || item.status === "in_progress" || item.status === "cancelled") && <div className="mt-4 flex flex-wrap gap-2">
          {item.status !== "in_progress" && <Button size="sm" variant="outline" onClick={() => updateAssignment(item.id, "in_progress")}>Đang hỗ trợ</Button>}
          {item.status !== "cancelled" && <Button size="sm" variant="ghost" onClick={() => updateAssignment(item.id, "cancelled")}>Hủy</Button>}
          {item.status === "cancelled" && <Button size="sm" variant="outline" onClick={() => updateAssignment(item.id, "pending")}>Mở lại</Button>}
        </div>}
      </article>)}
      {assignments.length === 0 && <Empty text="Chưa có assignment." />}
    </CardContent></Card>
  </div>;
}

function Status({ text, action }: { text: string; action?: React.ReactNode }) { return <Card className="mx-auto max-w-xl p-10 text-center"><p role="status" className="font-bold">{text}</p>{action && <div className="mt-5">{action}</div>}</Card>; }
function Metric({ icon: Icon, value, label }: { icon: typeof IconUsers; value: number; label: string }) { return <Card className="flex items-center gap-4 p-5"><span aria-hidden="true" className="rounded-2xl bg-accent p-3 text-primary"><Icon /></span><div><p className="text-3xl font-bold tabular-nums">{value}</p><p className="text-sm text-muted-foreground">{label}</p></div></Card>; }
function MetricSmall({ label, value }: { label: string; value: number }) { return <div className="rounded-xl bg-muted p-4"><p className="text-2xl font-bold tabular-nums">{value}</p><p className="text-xs text-muted-foreground">{label}</p></div>; }
function Empty({ text }: { text: string }) { return <p className="rounded-xl bg-muted p-5 text-center text-muted-foreground">{text}</p>; }
