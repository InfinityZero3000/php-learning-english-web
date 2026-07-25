"use client";

import { IconDeviceFloppy, IconFileUpload, IconLanguage, IconSparkles } from "@tabler/icons-react";
import { ChangeEvent, useEffect, useMemo, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Table, Td, Th } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/components/ui/toast";
import { api } from "@/lib/api";
import { formatDateTime } from "@/lib/utils";
import type { CefrLevel, DictionaryLookup, DifficultyLevel, ImportJob, ImportRow, Topic } from "@/types/api";

type Source = "paste" | "file";

export function ImportPage() {
  const [source, setSource] = useState<Source>("paste");
  const [text, setText] = useState("");
  const [rows, setRows] = useState<ImportRow[]>([]);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [jobs, setJobs] = useState<ImportJob[]>([]);
  const [defaults, setDefaults] = useState({ difficulty: "INTERMEDIATE" as DifficultyLevel, cefrLevel: "" as CefrLevel | "", category: "", topicId: "" });
  const [targetSetName, setTargetSetName] = useState("");
  const [loading, setLoading] = useState(true);
  const { toast } = useToast();

  useEffect(() => {
    Promise.all([
      api.topics().catch(() => [] as Topic[]),
      api.importJobs().catch(() => [] as ImportJob[]),
    ]).then(([tops, importJobs]) => {
      setTopics(tops);
      setJobs(importJobs);
      setLoading(false);
    }).catch(() => setLoading(false));
    const saved = window.localStorage.getItem("fsrspring-import-draft");
    if (saved) {
      try {
        const draft = JSON.parse(saved);
        setText(draft.text || "");
        setRows(draft.rows || []);
      } catch {
        // Ignore invalid draft.
      }
    }
  }, []);

  useEffect(() => {
    window.localStorage.setItem("fsrspring-import-draft", JSON.stringify({ text, rows }));
  }, [text, rows]);

  const readyCount = useMemo(() => rows.filter((row) => row.word && row.translation).length, [rows]);
  const missingCount = rows.length - readyCount;

  function parseText(input: string) {
    const parsed = input
      .split(/\r?\n/)
      .map((line, index) => {
        const trimmed = line.trim();
        if (!trimmed) return null;
        const parts = trimmed.split(/\t|,|;/).map((part) => part.trim());
        return {
          clientRowId: `row-${Date.now()}-${index}`,
          word: parts[0] || "",
          translation: parts[1] || "",
          pronunciation: parts[2] || "",
          category: defaults.category,
          difficulty: defaults.difficulty,
          cefrLevel: defaults.cefrLevel,
          topicId: defaults.topicId ? Number(defaults.topicId) : null
        } satisfies ImportRow;
      })
      .filter(Boolean) as ImportRow[];
    setRows(parsed);
    toast(`Parsed ${parsed.length} rows.`, "success");
  }

  async function handleFile(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;
    const content = await file.text();
    setText(content);
    parseText(content);
  }

  async function translateMissing() {
    try {
      const result = await api.translateRows(rows);
      setRows(result.rows || rows);
      toast("Missing translations filled.", "success");
    } catch {
      toast("Backend translation unavailable. You can edit rows manually.", "warning");
    }
  }

  async function enrichDictionary() {
    const enriched = await Promise.all(rows.map(async (row) => {
      if (!row.word) return row;
      try {
        const data = await api.lookupDictionary(row.word);
        const lookup = data as DictionaryLookup;
        return {
          ...row,
          translation: row.translation || lookup.firstDefinition || "",
          example: row.example || lookup.firstExample || "",
          pronunciation: row.pronunciation || lookup.bestPhonetic || "",
          partOfSpeech: row.partOfSpeech || lookup.primaryPartOfSpeech || "",
          audioUrl: row.audioUrl || normalizeAudioUrl(lookup.bestAudioUrl)
        };
      } catch {
        return row;
      }
    }));
    setRows(enriched);
    toast("Dictionary lookup completed.", "success");
  }

  async function commit() {
    const payload = {
      sourceType: source.toUpperCase(),
      targetSet: targetSetName ? {
        name: targetSetName,
        topicId: defaults.topicId ? Number(defaults.topicId) : null,
        cefrLevel: defaults.cefrLevel || null
      } : null,
      options: { autoEnrich: true },
      rows: rows.filter((row) => row.word)
    };
    try {
      await api.commitImport(payload);
      toast("Import committed.", "success");
      setRows([]);
      setText("");
      setJobs(await api.importJobs().catch(() => []));
    } catch {
      toast("Import failed. Please check backend connection.", "error");
    }
  }

  function updateRow(id: string, patch: Partial<ImportRow>) {
    setRows((current) => current.map((row) => row.clientRowId === id ? { ...row, ...patch } : row));
  }

  if (loading) return <AppShellLoading label="Loading import..." />;

  return (
    <div className="space-y-5">
        <section className="grid gap-5 xl:grid-cols-[1fr_0.7fr]">
          <Card>
            <CardHeader>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <CardTitle>Import Words</CardTitle>
                <div className="flex gap-2">
                  <Button variant={source === "paste" ? "default" : "outline"} onClick={() => setSource("paste")}>Paste</Button>
                  <Button variant={source === "file" ? "default" : "outline"} onClick={() => setSource("file")}><IconFileUpload className="h-4 w-4" /> File</Button>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 md:grid-cols-4">
                <Select value={defaults.difficulty} onChange={(e) => setDefaults({ ...defaults, difficulty: e.target.value as DifficultyLevel })}>
                  <option value="BEGINNER">Beginner</option>
                  <option value="INTERMEDIATE">Intermediate</option>
                  <option value="ADVANCED">Advanced</option>
                </Select>
                <Select value={defaults.cefrLevel} onChange={(e) => setDefaults({ ...defaults, cefrLevel: e.target.value as CefrLevel | "" })}>
                  <option value="">Auto CEFR</option>
                  {["A1", "A2", "B1", "B2", "C1", "C2"].map((level) => <option key={level}>{level}</option>)}
                </Select>
                <Input placeholder="Default category" value={defaults.category} onChange={(e) => setDefaults({ ...defaults, category: e.target.value })} />
                <Select value={defaults.topicId} onChange={(e) => setDefaults({ ...defaults, topicId: e.target.value })}>
                  <option value="">Auto topic</option>
                  {topics.map((topic) => <option key={topic.id} value={topic.id}>{topic.name}</option>)}
                </Select>
              </div>
              <Input placeholder="Target set name" value={targetSetName} onChange={(e) => setTargetSetName(e.target.value)} />
              {source === "paste" ? (
                <Textarea rows={8} placeholder="word, translation, pronunciation" value={text} onChange={(e) => setText(e.target.value)} />
              ) : (
                <Input type="file" accept=".txt,.csv,.tsv" onChange={handleFile} />
              )}
              <div className="flex flex-wrap gap-2">
                <Button onClick={() => parseText(text)}><IconSparkles className="h-4 w-4" /> Parse</Button>
                <Button variant="outline" onClick={translateMissing}><IconLanguage className="h-4 w-4" /> Translate Missing</Button>
                <Button variant="outline" onClick={enrichDictionary}>Dictionary</Button>
                <Button variant="secondary" disabled={!rows.length} onClick={commit}><IconDeviceFloppy className="h-4 w-4" /> Commit</Button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Import Status</CardTitle></CardHeader>
            <CardContent className="grid gap-3">
              <Status label="Rows" value={rows.length} />
              <Status label="Ready" value={readyCount} />
              <Status label="Missing" value={missingCount} />
            </CardContent>
          </Card>
        </section>

        <Card>
          <CardHeader><CardTitle>Rows</CardTitle></CardHeader>
          <CardContent className="overflow-x-auto">
            <Table>
              <thead><tr><Th>Word</Th><Th>Translation</Th><Th>Category</Th><Th>Difficulty</Th><Th>Status</Th></tr></thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.clientRowId}>
                    <Td><Input value={row.word} onChange={(e) => updateRow(row.clientRowId, { word: e.target.value })} /></Td>
                    <Td><Input value={row.translation || ""} onChange={(e) => updateRow(row.clientRowId, { translation: e.target.value })} /></Td>
                    <Td><Input value={row.category || ""} onChange={(e) => updateRow(row.clientRowId, { category: e.target.value })} /></Td>
                    <Td>{row.difficulty}</Td>
                    <Td><Badge variant={row.word && row.translation ? "success" : "secondary"}>{row.word && row.translation ? "Ready" : "Missing"}</Badge></Td>
                  </tr>
                ))}
              </tbody>
            </Table>
            {!rows.length ? <p className="p-8 text-center font-bold text-muted-foreground">Parse input to preview rows.</p> : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>History</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {jobs.slice(0, 8).map((job) => (
              <div key={job.id} className="flex items-center justify-between rounded-xl bg-muted p-3">
                <div>
                  <p className="font-display font-bold">Job #{job.id} {job.targetSetName ? `- ${job.targetSetName}` : ""}</p>
                  <p className="text-sm font-semibold text-muted-foreground">{formatDateTime(job.createdAt)}</p>
                </div>
                <Badge>{job.status || "DONE"}</Badge>
              </div>
            ))}
            {!jobs.length ? <p className="font-semibold text-muted-foreground">No import jobs yet.</p> : null}
          </CardContent>
        </Card>
    </div>
  );
}

function Status({ label, value }: { label: string; value: number }) {
  return <div className="rounded-xl bg-muted p-4"><p className="font-display text-xs font-bold uppercase text-muted-foreground">{label}</p><p className="font-display text-2xl font-bold">{value}</p></div>;
}

function normalizeAudioUrl(url?: string) {
  if (!url) return "";
  return url.startsWith("//") ? `https:${url}` : url;
}
