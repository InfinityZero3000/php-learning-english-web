'use client';

import { useEffect, useId, useRef, type KeyboardEvent, type ReactNode } from 'react';

export default function AccessibleDialog({
  title,
  description,
  onClose,
  children,
  className = 'max-w-2xl',
}: {
  title: string;
  description?: string;
  onClose: () => void;
  children: ReactNode;
  className?: string;
}) {
  const ref = useRef<HTMLElement>(null);
  const trigger = useRef<HTMLElement | null>(null);
  const titleId = useId();
  const descriptionId = useId();

  // Lưu trigger element và focus vào dialog khi mở
  useEffect(() => {
    trigger.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const frame = requestAnimationFrame(() =>
      ref.current
        ?.querySelector<HTMLElement>(
          'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
        )
        ?.focus(),
    );
    return () => {
      cancelAnimationFrame(frame);
      trigger.current?.focus();
    };
  }, []);

  // Bắt Escape ở document level để đảm bảo hoạt động ngay cả khi focus
  // chưa kịp vào dialog (e.g. rAF chưa flush trong jsdom/test).
  // Đây là pattern chuẩn cho modal: bất kể focus ở đâu, Escape luôn đóng modal.
  useEffect(() => {
    const onKey = (e: KeyboardEvent | globalThis.KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  // Tab-trap: giữ focus bên trong dialog
  function keyDown(event: KeyboardEvent<HTMLElement>) {
    if (event.key !== 'Tab') return;
    const items = ref.current?.querySelectorAll<HTMLElement>(
      'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
    );
    if (!items?.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/40 p-4"
      onMouseDown={event => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <section
        ref={ref}
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        onKeyDown={keyDown}
        className={`max-h-[90vh] w-full overflow-y-auto rounded-3xl bg-white p-7 shadow-xl outline-none ${className}`}
      >
        <h2 id={titleId} className="text-2xl font-black">
          {title}
        </h2>
        {description && (
          <p id={descriptionId} className="mt-2 text-[#3e4850]">
            {description}
          </p>
        )}
        {children}
      </section>
    </div>
  );
}
