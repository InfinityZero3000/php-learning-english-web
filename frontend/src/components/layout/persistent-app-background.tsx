"use client";

import { useEffect, useState } from "react";
import {
  BACKGROUND_CHANGE_EVENT,
  getCachedBackgroundObjectUrl,
  readSelectedBackground,
  type BackgroundOption
} from "@/lib/background-settings";

export function PersistentAppBackground() {
  const [background, setBackground] = useState<BackgroundOption | null>(null);
  const [backgroundUrl, setBackgroundUrl] = useState<string | null>(null);

  useEffect(() => {
    function syncBackground() {
      setBackground(readSelectedBackground());
    }

    syncBackground();
    window.addEventListener(BACKGROUND_CHANGE_EVENT, syncBackground);
    return () => window.removeEventListener(BACKGROUND_CHANGE_EVENT, syncBackground);
  }, []);

  useEffect(() => {
    let objectUrlToRevoke: string | null = null;
    let cancelled = false;

    async function resolveBackground() {
      if (!background) {
        setBackgroundUrl(null);
        return;
      }

      setBackgroundUrl(background.url);
      const cachedUrl = await getCachedBackgroundObjectUrl(background);

      if (cancelled) {
        if (cachedUrl) URL.revokeObjectURL(cachedUrl);
        return;
      }

      objectUrlToRevoke = cachedUrl;
      if (cachedUrl) setBackgroundUrl(cachedUrl);
    }

    resolveBackground();

    return () => {
      cancelled = true;
      if (objectUrlToRevoke) URL.revokeObjectURL(objectUrlToRevoke);
    };
  }, [background]);

  const style = backgroundUrl
    ? ({
        "--app-background-image": `url("${backgroundUrl.replaceAll('"', '\\"')}")`,
        "--app-background-overlay": "linear-gradient(rgba(255, 255, 255, 0.42), rgba(255, 255, 255, 0.58))"
      } as React.CSSProperties)
    : undefined;

  return <div className="app-background-layer" aria-hidden="true" style={style} />;
}
