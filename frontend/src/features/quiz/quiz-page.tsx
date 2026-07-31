"use client";

import { useEffect, useRef, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { useToast } from "@/components/ui/toast";
import { api } from "@/lib/api";
import type { CatalogTopic as Topic, QuizAnswerFeedback, QuizQuestion } from "@/lib/api";
import { QuizSetupScreen, type QuizConfig, type QuizType } from "./quiz-setup-screen";
import { QuizPlayingScreen } from "./quiz-playing-screen";
import { QuizDoneScreen } from "./quiz-done-screen";

const AUTO_ADVANCE_MS = 1600;

type Screen = "setup" | "playing" | "done";

export function QuizPage() {
  const [categories, setCategories] = useState<string[]>([]);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [screen, setScreen] = useState<Screen>("setup");
  const [sessionId, setSessionId] = useState<number | null>(null);
  const [questions, setQuestions] = useState<QuizQuestion[]>([]);
  const [index, setIndex] = useState(0);
  const [quizType, setQuizType] = useState<QuizType>("en-vi");
  const [lastConfig, setLastConfig] = useState<QuizConfig | null>(null);
  const [selected, setSelected] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<QuizAnswerFeedback | null>(null);
  const [resultScore, setResultScore] = useState<number | null>(null);
  const [correctCount, setCorrectCount] = useState(0);
  const [incorrectCount, setIncorrectCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [starting, setStarting] = useState(false);
  const [showExitConfirm, setShowExitConfirm] = useState(false);
  const { toast } = useToast();
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(() => {
    Promise.all([
      api.vocabularyFilters().catch(() => [] as string[]),
      api.catalogTopics().catch(() => [] as Topic[]),
    ]).then(([cats, tops]) => {
      setCategories(cats);
      setTopics(tops);
      setLoading(false);
    }).catch(() => setLoading(false));
    return () => clearTimeout(timerRef.current);
  }, []);

  const current = questions[index];

  async function start(config: QuizConfig) {
    if (starting) return;
    setStarting(true);
    try {
      const data = await api.startVocabularyQuiz({
        count: config.count,
        category: config.category,
        difficulty: config.difficulty,
        topicId: config.topicId ?? undefined,
        cefrLevel: config.cefr || undefined,
        type: config.quizType,
      });
      // The backend already filtered for valid words matching the type
      const validWords = data.questions;

      if (!validWords.length) {
        toast("Không có từ phù hợp với bộ lọc quiz này.", "warning");
        return;
      }

      setSessionId(data.sessionId);
      setQuestions(validWords);
      setQuizType(config.quizType);
      setLastConfig(config);
      setIndex(0);
      setSelected(null);
      setFeedback(null);
      setResultScore(null);
      setCorrectCount(0);
      setIncorrectCount(0);
      setScreen("playing");
    } catch {
      toast("Không thể bắt đầu quiz. Vui lòng thử lại.", "error");
    } finally {
      setStarting(false);
    }
  }

  async function answer(choice: string) {
    if (!current || !sessionId || selected !== null) return;
    setSelected(choice);
    try {
      const response = await api.answerVocabularyQuiz(sessionId, current.id, choice);
      setFeedback(response);
      if (response.isCorrect) setCorrectCount((v) => v + 1);
      else setIncorrectCount((v) => v + 1);
    } catch {
      setSelected(null);
      toast("Không thể lưu câu trả lời. Vui lòng thử lại.", "error");
      return;
    }

    const nextIndex = index + 1;
    timerRef.current = setTimeout(async () => {
      if (nextIndex >= questions.length) {
        await finish(sessionId);
      } else {
        setIndex(nextIndex);
        setSelected(null);
        setFeedback(null);
      }
    }, AUTO_ADVANCE_MS);
  }

  async function advance() {
    clearTimeout(timerRef.current);
    const nextIndex = index + 1;
    if (nextIndex >= questions.length) {
      if (sessionId) await finish(sessionId);
      return;
    }
    setIndex(nextIndex);
    setSelected(null);
    setFeedback(null);
  }

  async function finish(id: number) {
    try {
      const result = await api.completeVocabularyQuiz(id);
      setCorrectCount(result.correctCount);
      setIncorrectCount(result.incorrectCount);
      setResultScore(result.score);
      setScreen("done");
    } catch {
      setSelected(null);
      setFeedback(null);
      toast("Không thể hoàn tất quiz. Hãy thử lại.", "error");
    }
  }

  if (screen === "setup") {
    return <QuizSetupScreen categories={categories} topics={topics} starting={starting} onStart={start} />;
  }

  if (screen === "done") {
    return (
      <QuizDoneScreen
        correctCount={correctCount}
        incorrectCount={incorrectCount}
        resultScore={resultScore}
        onPlayAgain={() => lastConfig && start(lastConfig)}
        onSettings={() => setScreen("setup")}
      />
    );
  }

  if (!current) return null;
  if (loading) return <AppShellLoading label="Loading quiz..." />;

  return (
    <QuizPlayingScreen
      current={current}
      index={index}
      totalQuestions={questions.length}
      selected={selected}
      feedback={feedback}
      correctCount={correctCount}
      incorrectCount={incorrectCount}
      quizType={quizType}
      autoAdvanceMs={AUTO_ADVANCE_MS}
      showExitConfirm={showExitConfirm}
      onAnswer={answer}
      onAdvance={advance}
      onRequestExit={() => setShowExitConfirm(true)}
      onCancelExit={() => setShowExitConfirm(false)}
      onConfirmExit={() => { clearTimeout(timerRef.current); setShowExitConfirm(false); setScreen("setup"); }}
    />
  );
}
