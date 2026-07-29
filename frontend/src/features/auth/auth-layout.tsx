"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useRef } from "react";

export function AuthLayout({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  const panel = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const node = panel.current;
    if (!node || window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) return;
    const move = (event: PointerEvent) => {
      const rect = node.getBoundingClientRect();
      node.style.setProperty("--auth-x", `${Math.max(-8, Math.min(8, ((event.clientX - rect.left) / rect.width - .5) * 16))}px`);
      node.style.setProperty("--auth-y", `${Math.max(-8, Math.min(8, ((event.clientY - rect.top) / rect.height - .5) * 16))}px`);
    };
    node.addEventListener("pointermove", move);
    return () => node.removeEventListener("pointermove", move);
  }, []);

  return (
    <main className="grid min-h-screen bg-[#f9f7f1] lg:grid-cols-2">
      <div ref={panel} className="auth-visual relative min-h-52 overflow-hidden lg:min-h-screen" aria-hidden="true">
        <Image src="/images/auth-language-study.webp" alt="" fill priority className="auth-photo object-cover" sizes="(min-width: 1024px) 50vw, 100vw" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#082f35]/95 via-[#0c5b66]/25 to-transparent" />
        <div className="absolute inset-x-0 bottom-0 p-7 text-white sm:p-10 lg:p-14">
          <p className="mb-3 text-xs font-bold uppercase tracking-[.28em] text-[#ffe08a]">Learn · Remember · Speak</p>
          <p className="max-w-xl font-display text-3xl font-bold leading-tight sm:text-4xl">A new language opens a new way to see the world.</p>
          <div className="mt-6 hidden gap-2 sm:flex"><Chip>curious</Chip><Chip>brave</Chip><Chip>fluent</Chip></div>
        </div>
      </div>
      <section className="flex items-center justify-center px-5 py-10 sm:px-10 lg:px-16" aria-labelledby="auth-title">
        <div className="w-full max-w-md">
          <Link href="/" className="font-display text-3xl font-bold text-primary">Linguist<span className="text-secondary">.</span></Link>
          <header className="mb-7 mt-8"><h1 id="auth-title" className="font-display text-3xl font-bold tracking-tight text-[#102f33]">{title}</h1><p className="mt-2 text-sm leading-6 text-muted-foreground">{description}</p></header>
          {children}
        </div>
      </section>
    </main>
  );
}

function Chip({ children }: { children: React.ReactNode }) {
  return <span className="rounded-full border border-white/35 bg-white/15 px-3 py-1.5 text-sm font-semibold backdrop-blur">{children}</span>;
}
