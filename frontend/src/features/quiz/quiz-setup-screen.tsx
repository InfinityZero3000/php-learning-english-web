"use client";

import { IconPlayerPlay, IconPuzzle } from "@tabler/icons-react";
import { useState } from "react";
import { Select } from "@/components/ui/select";
import type { CatalogTopic as Topic } from "@/lib/api";

export type QuizType = "en-vi" | "vi-en";

export interface QuizConfig {
  count: number;
  category: string;
  difficulty: string;
  topicId: number | null;
  cefr: string;
  quizType: QuizType;
}

interface QuizSetupScreenProps {
  categories: string[];
  topics: Topic[];
  starting: boolean;
  onStart: (config: QuizConfig) => void;
}

function QuizHero() {
  return (
    <div className="mb-6 flex items-center gap-6 rounded-xl bg-primary p-6">
      <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-accent">
        <IconPuzzle className="h-9 w-9 text-primary" />
      </div>
      <div>
        <h2 className="font-display text-[24px] font-bold text-primary-foreground">Vocabulary Quiz</h2>
        <p className="font-body text-[17px] text-accent">Test your knowledge with multiple choice</p>
      </div>
    </div>
  );
}

export function QuizSetupScreen({ categories, topics, starting, onStart }: QuizSetupScreenProps) {
  const [category, setCategory] = useState("");
  const [difficulty, setDifficulty] = useState("");
  const [topicId, setTopicId] = useState<number | null>(null);
  const [cefr, setCefr] = useState("");
  const [count, setCount] = useState(10);
  const [quizType, setQuizType] = useState<QuizType>("en-vi");

  return (
    <div className="mx-auto max-w-lg">
        <QuizHero />
        <div className="rounded-xl border-2 border-border bg-white p-6">
          <h3 className="mb-5 font-display text-xl font-bold text-foreground">Quiz Setup</h3>
          <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Category
              </label>
              <Select
                value={category}
                onChange={(e) => setCategory(e.target.value)}
              >
                <option value="">All Categories</option>
                {categories.map((cat) => (
                  <option key={cat} value={cat}>{cat}</option>
                ))}
              </Select>
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Difficulty
              </label>
              <Select
                value={difficulty}
                onChange={(e) => setDifficulty(e.target.value)}
              >
                <option value="">All Levels</option>
                <option value="BEGINNER">Beginner</option>
                <option value="INTERMEDIATE">Intermediate</option>
                <option value="ADVANCED">Advanced</option>
              </Select>
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Topic
              </label>
              <Select
                value={topicId ?? ""}
                onChange={(e) => setTopicId(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">All Topics</option>
                {topics.map((t) => (
                  <option key={t.id} value={t.id}>{t.name}</option>
                ))}
              </Select>
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                CEFR Level
              </label>
              <Select
                value={cefr}
                onChange={(e) => setCefr(e.target.value)}
              >
                <option value="">All CEFR</option>
                {["A1", "A2", "B1", "B2", "C1", "C2"].map((lvl) => (
                  <option key={lvl} value={lvl}>{lvl}</option>
                ))}
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                  Questions
                </label>
                <input
                  type="number"
                  min={5}
                  max={50}
                  value={count}
                  onChange={(e) =>
                    setCount(Math.max(5, Math.min(50, Number(e.target.value))))
                  }
                  className="rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[17px] text-foreground outline-none focus:border-primary"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                  Type
                </label>
                <Select
                  value={quizType}
                  onChange={(e) => setQuizType(e.target.value as QuizType)}
                >
                  <option value="en-vi">EN to VI</option>
                  <option value="vi-en">VI to EN</option>
                </Select>
              </div>
            </div>
            <button
              type="button"
              onClick={() => onStart({ count, category, difficulty, topicId, cefr, quizType })}
              disabled={starting}
              className="btn-press mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
            >
              <IconPlayerPlay className="h-5 w-5" />
              {starting ? "Starting..." : "Start Quiz"}
            </button>
          </div>
        </div>
    </div>
  );
}
