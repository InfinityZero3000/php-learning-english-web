import * as React from "react";
import { cn } from "@/lib/utils";

export function Alert({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("rounded-xl border-2 border-border bg-muted p-4 text-sm font-semibold", className)} {...props} />;
}
