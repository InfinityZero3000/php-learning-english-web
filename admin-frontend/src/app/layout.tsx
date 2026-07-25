import type { Metadata } from "next";
import Script from "next/script";
import "./globals.css";

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
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        />
        <link
          rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"
        />
      </head>
      <body className="h-full overflow-x-hidden" suppressHydrationWarning>
        <Script
          id="remove-extension-hydration-attrs"
          strategy="beforeInteractive"
          dangerouslySetInnerHTML={{
            __html: `
              (function () {
                var extensionAttrs = [
                  'bis_skin_checked',
                  'data-new-gr-c-s-check-loaded',
                  'data-gr-ext-installed'
                ];
                function clean() {
                  document.querySelectorAll('[bis_skin_checked], [data-new-gr-c-s-check-loaded], [data-gr-ext-installed]').forEach(function (node) {
                    extensionAttrs.forEach(function (attr) {
                      node.removeAttribute(attr);
                    });
                  });
                }
                clean();
                new MutationObserver(clean).observe(document.documentElement, {
                  attributes: true,
                  subtree: true,
                  attributeFilter: extensionAttrs
                });
              })();
            `
          }}
        />
        {children}
      </body>
    </html>
  );
}
