import * as React from "react";
import { cn } from "@/lib/utils";

const variants = {
  default: "bg-primary/10 text-primary border-primary/30",
  secondary: "bg-secondary/30 text-secondary-foreground border-secondary",
  muted: "bg-muted text-muted-foreground border-border",
  danger: "bg-destructive/10 text-destructive border-destructive/30",
  success: "bg-emerald-100 text-emerald-700 border-emerald-200"
};

export function Badge({
  className,
  variant = "default",
  ...props
}: React.HTMLAttributes<HTMLSpanElement> & { variant?: keyof typeof variants }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold uppercase tracking-[0.04em]",
        variants[variant],
        className
      )}
      {...props}
    />
  );
}
