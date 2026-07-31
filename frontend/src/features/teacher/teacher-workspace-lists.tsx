"use client";

import { IconAlertTriangle, IconChecklist, IconUsers } from "@tabler/icons-react";
import type { SupervisionAlert, TeacherAssignment, TeacherLearner } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select } from "@/components/ui/select";

export function Status({ text, action }: { text: string; action?: React.ReactNode }) {
  return (
    <Card className="mx-auto max-w-xl p-10 text-center">
      <p role="status" className="font-bold">
        {text}
      </p>
      {action && <div className="mt-5">{action}</div>}
    </Card>
  );
}

export function TeacherMetrics({
  totalLearners,
  openAlerts,
  pendingAssignments,
}: {
  totalLearners: number;
  openAlerts: number;
  pendingAssignments: number;
}) {
  return (
    <div className="grid gap-4 md:grid-cols-3">
      <Metric icon={IconUsers} value={totalLearners} label="Học viên" />
      <Metric icon={IconAlertTriangle} value={openAlerts} label="Cảnh báo mở" />
      <Metric icon={IconChecklist} value={pendingAssignments} label="Bài đang giao" />
    </div>
  );
}

function Metric({ icon: Icon, value, label }: { icon: typeof IconUsers; value: number; label: string }) {
  return (
    <Card className="flex items-center gap-4 p-5">
      <span aria-hidden="true" className="rounded-2xl bg-accent p-3 text-primary">
        <Icon />
      </span>
      <div>
        <p className="text-3xl font-bold tabular-nums">{value}</p>
        <p className="text-sm text-muted-foreground">{label}</p>
      </div>
    </Card>
  );
}

export function MetricSmall({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-xl bg-muted p-4">
      <p className="text-2xl font-bold tabular-nums">{value}</p>
      <p className="text-xs text-muted-foreground">{label}</p>
    </div>
  );
}

export function Empty({ text }: { text: string }) {
  return <p className="rounded-xl bg-muted p-5 text-center text-muted-foreground">{text}</p>;
}

export function TeacherAlerts({
  alerts,
  selectedLearnerId,
  alertStateFilter,
  onFilterChange,
  onResolve,
}: {
  alerts: SupervisionAlert[];
  selectedLearnerId?: number;
  alertStateFilter: string;
  onFilterChange: (state: string) => void;
  onResolve: (alert: SupervisionAlert) => void;
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
        <CardTitle>Cảnh báo cần can thiệp</CardTitle>
        <div className="w-40">
          <Select
            aria-label="Filter alerts"
            value={alertStateFilter}
            onChange={(e) => onFilterChange(e.target.value)}
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="open">Đang mở (open)</option>
            <option value="resolved">Đã xử lý (resolved)</option>
          </Select>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {alerts.length === 0 ? (
          <Empty text="Chưa có cảnh báo." />
        ) : (
          alerts.map((alert) => (
            <article key={alert.id} className="rounded-2xl border-2 border-border p-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <span
                    className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${
                      alert.severity === "critical" ? "bg-red-100 text-red-800" : "bg-amber-100 text-amber-800"
                    }`}
                  >
                    {alert.severity}
                  </span>
                  <h3 className="mt-3 break-words font-display text-xl font-bold">
                    {alert.learner.name} · {alert.rule_key.replaceAll("_", " ")}
                  </h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {alert.evidence.length} snapshot · Trạng thái: {alert.state}
                  </p>
                </div>
                {alert.state === "open" && (
                  <Button
                    size="sm"
                    variant={selectedLearnerId === alert.learner.id ? "default" : "outline"}
                    onClick={() => onResolve(alert)}
                  >
                    {selectedLearnerId === alert.learner.id ? "Xác nhận đã xử lý" : "Xem bằng chứng"}
                  </Button>
                )}
              </div>
            </article>
          ))
        )}
      </CardContent>
    </Card>
  );
}

export function TeacherLearners({
  learners,
  selectedLearnerId,
  onSelect,
}: {
  learners: TeacherLearner[];
  selectedLearnerId?: number;
  onSelect: (id: number) => void;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Học viên được giao</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {learners.map((item) => (
          <button
            key={item.id}
            onClick={() => onSelect(item.id)}
            className={`w-full rounded-2xl border-2 p-4 text-left hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary ${
              selectedLearnerId === item.id ? "border-primary bg-accent" : "border-transparent bg-muted"
            }`}
          >
            <span className="block font-bold">{item.name}</span>
            <span className="block truncate text-sm text-muted-foreground">{item.email}</span>
          </button>
        ))}
        {learners.length === 0 && <Empty text="Chưa có học viên được phân công." />}
      </CardContent>
    </Card>
  );
}

export function TeacherAssignmentsList({
  assignments,
  assignmentStatusFilter,
  onFilterChange,
  onUpdateStatus,
}: {
  assignments: TeacherAssignment[];
  assignmentStatusFilter: string;
  onFilterChange: (status: string) => void;
  onUpdateStatus: (id: number, status: "pending" | "in_progress" | "cancelled") => void;
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
        <CardTitle>Assignments</CardTitle>
        <div className="w-48">
          <Select
            aria-label="Filter assignments"
            value={assignmentStatusFilter}
            onChange={(e) => onFilterChange(e.target.value)}
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="pending">Đang chờ (pending)</option>
            <option value="in_progress">Đang hỗ trợ (in_progress)</option>
            <option value="completed">Đã hoàn thành (completed)</option>
            <option value="cancelled">Đã hủy (cancelled)</option>
          </Select>
        </div>
      </CardHeader>
      <CardContent className="grid gap-3 md:grid-cols-2">
        {assignments.map((item) => (
          <article key={item.id} className="rounded-2xl border-2 border-border p-4">
            <p className="font-bold">{item.learner.name}</p>
            <p className="mt-1 text-sm">{item.lesson?.title ?? item.vocabulary?.word ?? "Không có target"}</p>
            <span
              className={`mt-3 inline-block rounded-full px-3 py-1 text-xs font-bold uppercase ${
                item.status === "completed"
                  ? "bg-green-100 text-green-800"
                  : item.status === "in_progress"
                  ? "bg-blue-100 text-blue-800"
                  : item.status === "cancelled"
                  ? "bg-gray-100 text-gray-800"
                  : "bg-amber-100 text-amber-800"
              }`}
            >
              {item.status}
            </span>
            {(item.status === "pending" || item.status === "in_progress" || item.status === "cancelled") && (
              <div className="mt-4 flex flex-wrap gap-2">
                {item.status !== "in_progress" && (
                  <Button size="sm" variant="outline" onClick={() => onUpdateStatus(item.id, "in_progress")}>
                    Đang hỗ trợ
                  </Button>
                )}
                {item.status !== "cancelled" && (
                  <Button size="sm" variant="ghost" onClick={() => onUpdateStatus(item.id, "cancelled")}>
                    Hủy
                  </Button>
                )}
                {item.status === "cancelled" && (
                  <Button size="sm" variant="outline" onClick={() => onUpdateStatus(item.id, "pending")}>
                    Mở lại
                  </Button>
                )}
              </div>
            )}
          </article>
        ))}
        {assignments.length === 0 && <Empty text="Chưa có assignment." />}
      </CardContent>
    </Card>
  );
}
