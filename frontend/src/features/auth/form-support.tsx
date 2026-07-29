import Link from "next/link";
import { ApiError } from "@/lib/api";

export function messageFor(error: unknown, fallback = "Could not reach the server. Please try again.") {
  if (!(error instanceof ApiError)) return fallback;
  return Object.values(error.errors ?? {}).flat()[0] ?? error.message;
}

export function Notice({ children, error = false }: { children?: React.ReactNode; error?: boolean }) {
  if (!children) return null;
  return <p role={error ? "alert" : "status"} aria-live="polite" className={`mb-4 rounded-xl border p-3 text-sm font-semibold ${error ? "border-destructive/30 bg-destructive/10 text-destructive" : "border-emerald-300 bg-emerald-50 text-emerald-800"}`}>{children}</p>;
}

export function Field({ id, label, type = "text", ...props }: React.InputHTMLAttributes<HTMLInputElement> & { id: string; label: string }) {
  return <label htmlFor={id} className="block text-sm font-bold text-[#243b3e]">{label}<input id={id} type={type} className="mt-2 h-12 w-full rounded-xl border-2 border-input bg-white px-3 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10" {...props} /></label>;
}

export function AuthLink({ href, children }: { href: string; children: React.ReactNode }) {
  return <Link href={href} className="font-bold text-primary underline-offset-4 hover:underline">{children}</Link>;
}
