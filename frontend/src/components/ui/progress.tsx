import { cn } from "@/lib/utils";

export function Progress({ value, className }: { value: number; className?: string }) {
  const width = Math.max(0, Math.min(100, value));
  return (
    <div className={cn("h-3 overflow-hidden rounded-full bg-muted", className)} role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={width}>
      <div className="h-full rounded-full bg-gradient-to-r from-primary to-emerald-500 transition-all duration-500" style={{ width: `${width}%` }} />
    </div>
  );
}
