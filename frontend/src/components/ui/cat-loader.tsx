"use client";

import dynamic from "next/dynamic";
import animationData from "../../../public/running-cat.json";
import { cn } from "@/lib/utils";

// Dynamic import keeps lottie-web (~300 KB) out of the main bundle.
// It is only loaded when a loading overlay is actually shown.
const Lottie = dynamic(() => import("lottie-react"), { ssr: false });

interface CatLoaderProps {
  /** Size of the animation container in px. Default: 200 */
  size?: number;
  /** Optional label shown below the cat. Default: "Loading…" */
  label?: string;
  className?: string;
  labelClassName?: string;
}

export function CatLoader({ size = 200, label = "Loading…", className, labelClassName }: CatLoaderProps) {
  return (
    <div
      className={cn("flex flex-col items-center justify-center gap-3 py-16", className)}
      role="status"
      aria-label={label}
    >
      <Lottie
        animationData={animationData}
        loop
        autoplay
        style={{ width: size, height: Math.round(size * (391 / 681)) }}
      />
      <p className={cn("animate-pulse text-sm font-medium text-muted-foreground", labelClassName)}>
        {label}
      </p>
    </div>
  );
}
