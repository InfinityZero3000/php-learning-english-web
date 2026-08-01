'use client';

import Link from 'next/link';
import type { ComponentPropsWithoutRef } from 'react';

const styles = {
  primary: 'border-[#006590] text-[#006590] hover:bg-[#e8f4ff]',
  accent: 'border-[#843ab4] text-[#843ab4] hover:bg-[#f8effe]',
  success: 'border-[#1a7f4b] text-[#1a7f4b] hover:bg-[#e5f6ec]',
  neutral: 'border-[#bdc8d2] text-[#3e4850] hover:bg-[#f5f3f3]',
  danger: 'border-[#ba1a1a] text-[#ba1a1a] hover:bg-[#ffdad6]',
} as const;

export type ActionVariant = keyof typeof styles;

const base = 'inline-flex items-center justify-center whitespace-nowrap rounded-lg border-2 bg-white px-3 py-1.5 text-xs font-black uppercase tracking-wide transition-colors disabled:cursor-not-allowed disabled:opacity-40';

export function ActionButton({ variant = 'neutral', className = '', ...props }: ComponentPropsWithoutRef<'button'> & { variant?: ActionVariant }) {
  return <button {...props} className={`${base} ${styles[variant]} ${className}`} />;
}

export function ActionLink({ variant = 'neutral', className = '', ...props }: ComponentPropsWithoutRef<typeof Link> & { variant?: ActionVariant }) {
  return <Link {...props} className={`${base} ${styles[variant]} ${className}`} />;
}
