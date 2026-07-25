"use client";

import * as React from "react";
import { IconX } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function Dialog({
  open,
  title,
  children,
  onClose,
  className
}: {
  open: boolean;
  title: string;
  children: React.ReactNode;
  onClose: () => void;
  className?: string;
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
      <section className={cn("max-h-[90vh] w-full max-w-2xl overflow-auto rounded-xl border-2 bg-card shadow-lifted", className)}>
        <header className="flex items-center justify-between border-b-2 p-5">
          <h2 className="font-display text-xl font-bold">{title}</h2>
          <Button aria-label="Close dialog" variant="ghost" size="icon" onClick={onClose}>
            <IconX className="h-5 w-5" />
          </Button>
        </header>
        <div className="p-5">{children}</div>
      </section>
    </div>
  );
}
