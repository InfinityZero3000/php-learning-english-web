"use client";

import {
  IconAlertCircle,
  IconAlertTriangle,
  IconCheck,
  IconInfoCircle,
  IconX
} from "@tabler/icons-react";
import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ComponentPropsWithoutRef,
  type ReactElement,
  type ReactNode
} from "react";
import { cn } from "@/lib/utils";

export type ToastType = "success" | "error" | "info" | "warning";

export type ToastProps = ComponentPropsWithoutRef<"div"> & {
  variant?: "default" | "destructive";
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
};

export type ToastActionElement = ReactElement;

interface ToastMessage {
  id: string;
  message: string;
  type: ToastType;
}

interface ToastContextType {
  toast: (message: string, type?: ToastType) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const removeToast = useCallback((id: string) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const toast = useCallback((message: string, type: ToastType = "info") => {
    const id = crypto.randomUUID?.() || Math.random().toString(36).slice(2);
    setToasts((current) => [...current, { id, message, type }].slice(-3));
    window.setTimeout(() => removeToast(id), 3600);
  }, [removeToast]);

  const value = useMemo(() => ({ toast }), [toast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="pointer-events-none fixed bottom-5 right-5 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 sm:bottom-6 sm:right-6">
        {toasts.map((item) => (
          <ToastCard key={item.id} toast={item} onRemove={() => removeToast(item.id)} />
        ))}
      </div>
    </ToastContext.Provider>
  );
}

function ToastCard({ toast, onRemove }: { toast: ToastMessage; onRemove: () => void }) {
  const icons = {
    success: <IconCheck className="h-5 w-5" stroke={2.2} />,
    error: <IconAlertCircle className="h-5 w-5" stroke={2.2} />,
    info: <IconInfoCircle className="h-5 w-5" stroke={2.2} />,
    warning: <IconAlertTriangle className="h-5 w-5" stroke={2.2} />
  };

  const variants = {
    success: "border-emerald-200 bg-emerald-50 text-emerald-900 [&_.toast-icon]:bg-emerald-100 [&_.toast-icon]:text-emerald-700",
    error: "border-red-200 bg-red-50 text-red-900 [&_.toast-icon]:bg-red-100 [&_.toast-icon]:text-red-700",
    info: "border-sky-200 bg-sky-50 text-sky-900 [&_.toast-icon]:bg-sky-100 [&_.toast-icon]:text-primary",
    warning: "border-amber-200 bg-amber-50 text-amber-900 [&_.toast-icon]:bg-amber-100 [&_.toast-icon]:text-amber-700"
  };

  return (
    <div
      className={cn(
        "pointer-events-auto flex items-start gap-3 rounded-xl border-2 px-3.5 py-3 shadow-lifted",
        "animate-in fade-in slide-in-from-bottom-2 duration-200",
        variants[toast.type]
      )}
      role="status"
    >
      <div className="toast-icon mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
        {icons[toast.type]}
      </div>
      <p className="min-w-0 flex-1 py-1 text-sm font-semibold leading-5">{toast.message}</p>
      <button
        onClick={onRemove}
        className="rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100"
        aria-label="Dismiss notification"
      >
        <IconX className="h-4 w-4" stroke={2.2} />
      </button>
    </div>
  );
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error("useToast must be used within a ToastProvider");
  }
  return context;
}
