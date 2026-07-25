import type { Metadata } from "next";
import "./globals.css";

/* Material Symbols has no system-font fallback and is loaded at runtime. */
/* eslint-disable @next/next/no-page-custom-font, @next/next/google-font-display */

export const metadata: Metadata = {
  title: "Linguist Admin",
  description: "Admin Dashboard for Linguist vocabulary learning system",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="vi" className="h-full">
      <head>
        <link
          rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"
        />
      </head>
      <body className="h-full overflow-x-hidden" suppressHydrationWarning>
        {children}
      </body>
    </html>
  );
}
