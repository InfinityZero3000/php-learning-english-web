export interface BackgroundOption {
  id: string;
  name: string;
  url: string;
  previewUrl: string;
  originalUrl: string;
}

const BACKGROUND_STORAGE_KEY = "fsrspring.background";
const BACKGROUND_CACHE_NAME = "fsrspring-backgrounds-v2";
const BACKGROUND_BASE_URL = "/screen-background";

export const BACKGROUND_CHANGE_EVENT = "fsrspring:background-change";

export const backgroundOptions: BackgroundOption[] = Array.from({ length: 15 }, (_, index) => {
  const id = String(index + 1);
  const optimizedExtension = id === "15" ? "png" : "jpg";
  return {
    id,
    name: `Landscape ${id}`,
    url: `${BACKGROUND_BASE_URL}/optimized/${id}.${optimizedExtension}`,
    previewUrl: `${BACKGROUND_BASE_URL}/thumbs/${id}.jpg`,
    originalUrl: `${BACKGROUND_BASE_URL}/${id}.png`
  };
});

function isBrowser() {
  return typeof window !== "undefined";
}

export function getBackgroundOption(id: string | null | undefined) {
  if (!id) return null;
  return backgroundOptions.find((option) => option.id === id) ?? null;
}

export function readSelectedBackground() {
  if (!isBrowser()) return null;

  try {
    const raw = window.localStorage.getItem(BACKGROUND_STORAGE_KEY);
    if (!raw) return null;

    const parsed = JSON.parse(raw) as { id?: string };
    return getBackgroundOption(parsed.id);
  } catch {
    return null;
  }
}

export function saveSelectedBackground(option: BackgroundOption) {
  if (!isBrowser()) return;
  window.localStorage.setItem(BACKGROUND_STORAGE_KEY, JSON.stringify({ id: option.id }));
  notifyBackgroundChange();
}

export function clearSelectedBackground() {
  if (!isBrowser()) return;
  window.localStorage.removeItem(BACKGROUND_STORAGE_KEY);
  notifyBackgroundChange();
}

export function notifyBackgroundChange() {
  if (!isBrowser()) return;
  window.dispatchEvent(new Event(BACKGROUND_CHANGE_EVENT));
}

export async function cacheBackgroundImage(option: BackgroundOption) {
  if (!isBrowser() || !("caches" in window)) return false;

  try {
    const cache = await window.caches.open(BACKGROUND_CACHE_NAME);
    const cached = await cache.match(option.url);
    if (cached) return true;

    const response = await fetch(option.url, { cache: "force-cache", mode: "cors" });
    if (!response.ok) return false;

    await cache.put(option.url, response.clone());
    return true;
  } catch {
    return false;
  }
}

export async function isBackgroundImageCached(option: BackgroundOption) {
  if (!isBrowser() || !("caches" in window)) return false;

  try {
    const cache = await window.caches.open(BACKGROUND_CACHE_NAME);
    return Boolean(await cache.match(option.url));
  } catch {
    return false;
  }
}

export async function getCachedBackgroundObjectUrl(option: BackgroundOption) {
  if (!isBrowser() || !("caches" in window)) return null;

  try {
    const cache = await window.caches.open(BACKGROUND_CACHE_NAME);
    const response = await cache.match(option.url);
    if (!response) return null;

    const blob = await response.blob();
    return URL.createObjectURL(blob);
  } catch {
    return null;
  }
}
