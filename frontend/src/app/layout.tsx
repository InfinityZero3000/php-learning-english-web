import type { Metadata } from "next";
import "./globals.css";
import { AppShell } from "@/components/layout/app-shell";
import { PersistentAppBackground } from "@/components/layout/persistent-app-background";
import { ToastProvider } from "@/components/ui/toast";

export const metadata: Metadata = {
  title: "FSRSpring Vocabulary",
  description: "Smart vocabulary learning with FSRS scheduling"
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="vi" data-scroll-behavior="smooth" suppressHydrationWarning>
      <body suppressHydrationWarning>
        <PersistentAppBackground />
        <ToastProvider>
          <AppShell>
            {children}
          </AppShell>
        </ToastProvider>
      </body>
    </html>
  );
}
