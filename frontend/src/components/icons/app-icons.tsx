import type { SVGProps } from "react";
import {
  IconBook2,
  IconCards,
  IconChartBar,
  IconCirclePlus,
  IconHome,
  IconPuzzle
} from "@tabler/icons-react";

export const navigationIcons = {
  home: IconHome,
  words: IconBook2,
  flashcards: IconCards,
  quiz: IconPuzzle,
  progress: IconChartBar,
  import: IconCirclePlus
};

export function AppFlameIcon(props: SVGProps<SVGSVGElement>) {
  const { className, ...rest } = props;

  return (
    <svg
      aria-hidden="true"
      viewBox="0 0 24 24"
      width="1em"
      height="1em"
      className={className}
      fill="currentColor"
      {...rest}
    >
      <path d="M12 22c-3.86 0-7-3.04-7-6.78 0-2.03.85-3.86 2.28-5.31 1.26-1.28 2.07-2.74 2.22-4.5.03-.38.48-.57.77-.33 1.7 1.39 2.73 3.28 2.73 5.04 0 .75.55 1.36 1.25 1.36.73 0 1.25-.61 1.25-1.36 0-.69-.14-1.35-.41-1.98-.16-.38.23-.76.6-.58C17.7 8.55 19 10.85 19 15.22 19 18.96 15.86 22 12 22Zm0-3.25c1.24 0 2.25-.97 2.25-2.17 0-.45-.14-.89-.42-1.27L12 12.85l-1.83 2.46c-.28.38-.42.82-.42 1.27 0 1.2 1.01 2.17 2.25 2.17Z" />
    </svg>
  );
}

export function AppDiamondIcon(props: SVGProps<SVGSVGElement>) {
  const { className, ...rest } = props;

  return (
    <svg
      aria-hidden="true"
      viewBox="0 0 24 24"
      width="1em"
      height="1em"
      className={className}
      fill="currentColor"
      {...rest}
    >
      <path d="M6.15 3h11.7c.55 0 1.06.3 1.32.79l2.35 4.4c.29.54.22 1.2-.18 1.67L13.15 21.2a1.45 1.45 0 0 1-2.3 0L2.66 9.86c-.4-.47-.47-1.13-.18-1.67l2.35-4.4C5.09 3.3 5.6 3 6.15 3Zm.15 2.4L4.55 8.68 9.6 8.68 11.02 5.4H6.3Zm7.1 0 1.42 3.28h5.05L18.12 5.4H13.4Zm-2.18 3.28h1.56L12 6.85l-.78 1.83Zm-5.86 2.4 5.04 6.97-1.03-6.97H5.36Zm8.24 6.97 5.04-6.97h-4.01l-1.03 6.97Zm-1.6-.75.92-6.22h-1.84L12 17.3Z" />
    </svg>
  );
}
