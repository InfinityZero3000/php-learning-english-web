'use client';
import { useEffect } from 'react';
import { useRouter } from 'next/navigation';

export default function VocabularyRedirect() {
  const router = useRouter();
  useEffect(() => { router.replace('/flashcards'); }, [router]);
  return null;
}
