"use client";

import type { CourseCard, LearningEvidence, LessonCard, TeacherLearnerProgress } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Empty, MetricSmall } from "./teacher-workspace-lists";

export function TeacherEvidence({
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

export function TeacherAssignmentForm({
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

export function TeacherInterventionNoteForm({
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
