"use client";

import Link from "next/link";
import { IconCircleCheck, IconConfetti } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export default function SessionSummaryPage() {
  return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><IconConfetti className="mx-auto h-16 w-16 text-secondary" /><IconCircleCheck className="mx-auto mt-4 h-12 w-12 text-emerald-600" /><h2 className="mt-5 font-display text-4xl font-bold">Hoàn thành phiên học</h2><p className="mt-3 text-muted-foreground">Kết quả đã được lưu. Từ mới sẽ xuất hiện trong hàng đợi FSRS đúng thời điểm.</p><div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row"><Button asChildCompat="a"><Link href="/review">Ôn từ đến hạn</Link></Button><Button variant="outline" asChildCompat="a"><Link href="/">Về Today</Link></Button></div></Card></div>;
}
