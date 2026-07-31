"use client";

import { useState } from "react";
import { IconBookmark, IconBookmarkFilled, IconInfoCircle, IconPlayerPlay } from "@tabler/icons-react";
import type { VocabularyItem } from "@/lib/api";
import { Card } from "@/components/ui/card";

interface VocabularyCardProps {
  item: VocabularyItem;
  isPlaying: boolean;
  isSaving: boolean;
  onBookmark: (item: VocabularyItem) => void;
  onSpeak: (item: VocabularyItem) => void;
  onOpenDetail: (item: VocabularyItem) => void;
}

export function VocabularyCard({ item, isPlaying, isSaving, onBookmark, onSpeak, onOpenDetail }: VocabularyCardProps) {
  const [flipped, setFlipped] = useState(false);

  return (
    <Card className="min-h-56 p-0">
      <button
        type="button"
        onClick={() => setFlipped((value) => !value)}
        className="group block w-full [perspective:1200px]"
        aria-expanded={flipped}
        aria-label={`Lật thẻ ${item.word}`}
      >
        <span className={`relative block min-h-56 transition-transform duration-500 [transform-style:preserve-3d] ${flipped ? "[transform:rotateY(180deg)]" : ""}`}>
          {/* Front: word */}
          <div className="absolute inset-0 flex flex-col justify-between rounded-xl p-5 text-left [backface-visibility:hidden]">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="font-display text-2xl font-bold text-primary">{item.word}</h3>
                {item.pronunciation ? <p className="mt-1 font-mono text-sm text-muted-foreground">{item.pronunciation}</p> : null}
              </div>
              <div className="flex gap-2">
                <span
                  role="button"
                  tabIndex={0}
                  onClick={(event) => { event.stopPropagation(); onBookmark(item); }}
                  onKeyDown={(event) => { if (event.key === "Enter" || event.key === " ") { event.stopPropagation(); onBookmark(item); } }}
                  aria-disabled={isSaving}
                  aria-label={item.is_bookmarked ? `Bỏ lưu ${item.word}` : `Lưu ${item.word}`}
                  className={`rounded-xl bg-muted p-3 text-primary ${isSaving ? "opacity-50" : ""}`}
                >
                  {item.is_bookmarked ? <IconBookmarkFilled className="h-5 w-5" /> : <IconBookmark className="h-5 w-5" />}
                </span>
                <span
                  role="button"
                  tabIndex={0}
                  onClick={(event) => { event.stopPropagation(); onSpeak(item); }}
                  onKeyDown={(event) => { if (event.key === "Enter" || event.key === " ") { event.stopPropagation(); onSpeak(item); } }}
                  aria-disabled={isPlaying}
                  aria-label={`Phát âm ${item.word}`}
                  className={`rounded-xl bg-accent p-3 text-primary ${isPlaying ? "opacity-50" : ""}`}
                >
                  <IconPlayerPlay className="h-5 w-5" />
                </span>
              </div>
            </div>
            <div className="flex flex-wrap gap-2 text-xs font-bold uppercase text-muted-foreground">
              {item.topic ? <span className="rounded-full bg-muted px-3 py-1">{item.topic.name}</span> : null}
              {item.part_of_speech ? <span className="rounded-full bg-muted px-3 py-1">{item.part_of_speech}</span> : null}
              <span className="ml-auto normal-case text-muted-foreground">Bấm để xem nghĩa</span>
            </div>
          </div>

          {/* Back: meaning */}
          <div className="absolute inset-0 flex flex-col justify-between rounded-xl border-2 border-primary bg-card p-5 text-left [backface-visibility:hidden] [transform:rotateY(180deg)]">
            <div>
              <p className="text-lg font-bold">{item.meaning}</p>
              {item.definition ? <p className="mt-2 text-sm text-muted-foreground">{item.definition}</p> : null}
              {item.example ? <p className="mt-2 rounded-xl bg-muted p-2 text-sm italic">{item.example}</p> : null}
            </div>
            <span
              role="button"
              tabIndex={0}
              onClick={(event) => { event.stopPropagation(); onOpenDetail(item); }}
              onKeyDown={(event) => { if (event.key === "Enter" || event.key === " ") { event.stopPropagation(); onOpenDetail(item); } }}
              className="inline-flex items-center gap-1 self-start text-sm font-bold text-primary hover:underline"
            >
              <IconInfoCircle className="h-4 w-4" /> Xem chi tiết
            </span>
          </div>
        </span>
      </button>
    </Card>
  );
}
