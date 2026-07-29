"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { IconCircleCheck, IconCircleX, IconPlayerPlay } from "@tabler/icons-react";
import { ApiError, lessonQuizzes, type LessonQuizAttempt, type LessonQuizQuestion, type SubmittedLessonQuizAnswer } from "@/lib/api";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { QuizPage as VocabularyQuiz } from "./quiz-page";

type State = "loading" | "question" | "feedback" | "summary" | "error";

function message(error: unknown) {
  if (error instanceof ApiError) {
    if (error.status === 401) return "Hãy đăng nhập để làm quiz.";
    if (error.status === 403) return "Bạn không có quyền làm quiz này.";
    if (error.status === 409) return error.message || "Quiz chưa sẵn sàng.";
    if (error.status === 422) return "Câu trả lời không hợp lệ.";
    return error.message;
  }
  return "Không thể kết nối. Hãy thử lại.";
}

export function LessonQuiz({ quizId, attemptId }: { quizId?: number; attemptId?: number }) {
  const params = useSearchParams();
  const [attempt, setAttempt] = useState<LessonQuizAttempt>(); const [state, setState] = useState<State>("loading"); const [error, setError] = useState(""); const [submitting, setSubmitting] = useState(false);
  const [activeQuestionId, setActiveQuestionId] = useState<number>();
  const origin = params.get("course") ? `/courses/${params.get("course")}` : "/";

  async function load() {
    setState("loading"); setError("");
    try {
      const value = attemptId ? await lessonQuizzes.get(attemptId) : quizId ? await lessonQuizzes.start(quizId) : undefined;
      if (!value) throw new Error("Thiếu quiz cần mở.");
      const restored = value.submitted_answers?.at(-1);
      setAttempt(value); setActiveQuestionId(restored?.question_id ?? value.questions?.find((item) => !(value.submitted_answers ?? []).some((answer) => answer.question_id === item.id))?.id); setState(value.status === "completed" ? "summary" : restored ? "feedback" : "question");
    } catch (cause) { setError(message(cause)); setState("error"); }
  }
  useEffect(() => { void load(); }, [attemptId, quizId]); // eslint-disable-line react-hooks/exhaustive-deps

  const submitted = useMemo(() => new Map((attempt?.submitted_answers ?? []).map((answer) => [answer.question_id, answer])), [attempt]);
  const question = useMemo(() => attempt?.questions?.find((item) => item.id === activeQuestionId) ?? attempt?.questions?.find((item) => !submitted.has(item.id)), [activeQuestionId, attempt, submitted]);
  const feedback = question ? submitted.get(question.id) : undefined;

  async function answer(current: LessonQuizQuestion, answerId: number) {
    if (!attempt || submitting) return;
    setSubmitting(true); setError("");
    try {
      const result = await lessonQuizzes.answer(attempt.id, current.id, answerId);
      const next: SubmittedLessonQuizAnswer = { question_id: result.question_id, answer_id: result.answer_id, answer_content: current.answers.find((answer) => answer.id === result.answer_id)?.content ?? "", is_correct: result.is_correct, explanation: result.explanation };
      setAttempt({ ...attempt, submitted_answers: [...(attempt.submitted_answers ?? []).filter((item) => item.question_id !== current.id), next] }); setActiveQuestionId(current.id); setState("feedback");
    } catch (cause) { setError(message(cause)); } finally { setSubmitting(false); }
  }

  async function complete() {
    if (!attempt || submitting) return;
    setSubmitting(true); setError("");
    try { const result = await lessonQuizzes.complete(attempt.id); setAttempt({ ...attempt, ...result }); setState("summary"); } catch (cause) { setError(message(cause)); } finally { setSubmitting(false); }
  }

  if (state === "loading") return <AppShellLoading label="Đang tải lesson quiz..." />;
  if (state === "error") return <Card className="mx-auto max-w-xl p-8 text-center"><p role="alert" className="font-bold text-destructive">{error}</p><Button className="mt-5" onClick={() => void load()}>Thử lại</Button></Card>;
  if (!attempt) return null;
  if (state === "summary") return <section className="mx-auto max-w-xl space-y-5"><Card className="p-8 text-center"><p className="font-display text-sm font-bold uppercase tracking-widest text-primary">Hoàn thành quiz</p><p className="mt-4 font-display text-5xl font-bold">{attempt.score ?? 0}%</p><p className="mt-2 text-muted-foreground">{attempt.correct_answers ?? 0}/{attempt.total_questions ?? 0} câu đúng · {attempt.passed ? "Đạt" : "Chưa đạt"}</p><div className="mt-7 grid gap-3 sm:grid-cols-2">{quizId ? <Button type="button" onClick={() => { setAttempt(undefined); void load(); }}><IconPlayerPlay className="h-5 w-5" />Làm lại</Button> : null}<Button asChildCompat="a" variant="outline"><Link href={origin}>Bước học tiếp theo</Link></Button></div></Card></section>;
  if (!question) return <section className="mx-auto max-w-xl"><Card className="p-8 text-center"><p className="font-bold">Bạn đã trả lời tất cả câu hỏi.</p><Button className="mt-5" onClick={() => void complete()} disabled={submitting}>{submitting ? "Đang hoàn tất..." : "Xem kết quả"}</Button></Card></section>;
  const currentFeedback = submitted.get(question.id);
  const total = attempt.questions?.length ?? 0; const completed = submitted.size;
  return <section className="mx-auto max-w-2xl space-y-5"><header className="rounded-xl bg-primary p-6 text-primary-foreground"><p className="text-sm font-bold uppercase tracking-widest text-accent">Lesson quiz</p><h2 className="mt-2 font-display text-3xl font-bold">{attempt.quiz?.title}</h2><p className="mt-3 text-sm">Câu {Math.min(completed + (currentFeedback ? 0 : 1), total)} / {total}</p><div className="mt-3 h-2 overflow-hidden rounded-full bg-white/20"><div className="h-full bg-accent transition-[width]" style={{ width: `${total ? completed / total * 100 : 0}%` }} /></div></header><Card className="p-6"><p className="font-display text-2xl font-bold">{question.content}</p><div className="mt-6 grid gap-3">{question.answers.map((option) => <button key={option.id} type="button" disabled={submitting || Boolean(currentFeedback)} onClick={() => void answer(question, option.id)} className="rounded-xl border-2 border-border bg-card p-4 text-left font-semibold transition hover:border-primary disabled:cursor-default disabled:opacity-70">{option.content}</button>)}</div>{currentFeedback ? <div className={`mt-5 rounded-xl border-2 p-4 ${currentFeedback.is_correct ? "border-primary bg-accent" : "border-destructive bg-destructive/10"}`}><p className="flex items-center gap-2 font-bold">{currentFeedback.is_correct ? <IconCircleCheck className="h-5 w-5 text-primary" /> : <IconCircleX className="h-5 w-5 text-destructive" />}{currentFeedback.is_correct ? "Chính xác" : "Chưa đúng"}</p>{currentFeedback.explanation ? <p className="mt-2 text-sm">{currentFeedback.explanation}</p> : null}</div> : null}{error ? <p role="alert" className="mt-4 text-sm font-bold text-destructive">{error}</p> : null}</Card>{state === "feedback" ? <Button className="w-full" onClick={() => { setActiveQuestionId(undefined); setState("question"); }}>Câu tiếp theo</Button> : null}</section>;
}

export function QuizRoute() {
  const params = useSearchParams(); const quizId = Number(params.get("lesson_quiz")); const attemptId = Number(params.get("attempt"));
  return Number.isInteger(attemptId) && attemptId > 0 ? <LessonQuiz attemptId={attemptId} /> : Number.isInteger(quizId) && quizId > 0 ? <LessonQuiz quizId={quizId} /> : <VocabularyQuiz />;
}
