import { readFile, readdir } from "node:fs/promises";
import { createHash } from "node:crypto";
import Ajv2020 from "ajv/dist/2020.js";
import addFormats from "ajv-formats";

const root = new URL("../../", import.meta.url);
const schema = JSON.parse(await readFile(new URL("docs/openapi/lexilingo-import.schema.json", root)));
const traceCagContracts = await Promise.all([
  readFile(new URL("docs/openapi/trace-cag-external-v1.schema.json", root)),
  readFile(new URL("../LexiLingo/contracts/trace-cag/external-analyze-v1.schema.json", root)),
]);
const hashes = traceCagContracts.map((contract) => createHash("sha256").update(contract).digest("hex"));
if (hashes[0] !== hashes[1]) throw new Error(`TraceCAG contract SHA-256 mismatch: ${hashes.join(" != ")}`);

const ajv = new Ajv2020({ allErrors: true });
addFormats(ajv);
ajv.addSchema(schema);
const traceCagSchema = JSON.parse(traceCagContracts[0]);
ajv.addSchema(traceCagSchema);
const traceCagValidators = {};
for (const definition of ["ExternalAnalyzeRequest", "ExternalAnalyzeResponse"]) {
  const validate = ajv.getSchema(`${traceCagSchema.$id}#/$defs/${definition}`);
  if (!validate) {
    throw new Error(`TraceCAG schema definition did not compile: ${definition}`);
  }
  traceCagValidators[definition] = validate;
}

const request = {
  subject: "a".repeat(32),
  session_id: "session-1",
  input_type: "answer",
  learner_snapshot: {
    learning_goal: "Improve vocabulary",
    cefr_level: "B1",
    concepts: [{ code: "past-tense", mastery: 0.5 }],
    recent_errors: [{ code: "irregular-verb", count: 2 }],
  },
  exercise_context: {
    type: "quiz",
    prompt: "Choose the correct form.",
    expected_answer: "went",
    concept_codes: ["past-tense"],
  },
  text: "I goed home.",
};
const response = {
  schema_version: "1.0",
  request_id: "018f75d8-4ed6-7de2-a884-7a394888b081",
  diagnosis: { codes: ["irregular_verb"], summary: "Use the irregular past form." },
  hints: [{ level: 1, text: "The verb has an irregular past form." }],
  feedback: "Try the irregular form of go.",
  recommended_action: "retry",
  message: "Try once more.",
  degraded: false,
  model: { provider: "test", model: "contract-fixture", version: "1", latency_ms: 10 },
};
const clone = (value) => structuredClone(value);
const requestAtLimits = clone(request);
requestAtLimits.text = "x".repeat(2000);
requestAtLimits.learner_snapshot.concepts = Array.from(
  { length: 50 },
  (_, index) => ({ code: `concept-${index}`, mastery: 0.5 }),
);
requestAtLimits.learner_snapshot.recent_errors = Array.from(
  { length: 20 },
  (_, index) => ({ code: `error-${index}`, count: 1 }),
);
const degradedResponse = clone(response);
degradedResponse.degraded = true;
degradedResponse.model = null;

const traceCagFixtures = [
  { name: "request baseline", definition: "ExternalAnalyzeRequest", payload: request, valid: true },
  { name: "request text and counts at limits", definition: "ExternalAnalyzeRequest", payload: requestAtLimits, valid: true },
  { name: "response available", definition: "ExternalAnalyzeResponse", payload: response, valid: true },
  { name: "response degraded without model metadata", definition: "ExternalAnalyzeResponse", payload: degradedResponse, valid: true },
  { name: "request invalid text length", definition: "ExternalAnalyzeRequest", payload: { ...request, text: "x".repeat(2001) }, valid: false },
  {
    name: "request invalid concept count",
    definition: "ExternalAnalyzeRequest",
    payload: {
      ...request,
      learner_snapshot: {
        ...request.learner_snapshot,
        concepts: Array.from({ length: 51 }, (_, index) => ({ code: `concept-${index}`, mastery: 0.5 })),
      },
    },
    valid: false,
  },
  {
    name: "request invalid recent error count",
    definition: "ExternalAnalyzeRequest",
    payload: {
      ...request,
      learner_snapshot: {
        ...request.learner_snapshot,
        recent_errors: Array.from({ length: 21 }, (_, index) => ({ code: `error-${index}`, count: 1 })),
      },
    },
    valid: false,
  },
  { name: "request rejects additional properties", definition: "ExternalAnalyzeRequest", payload: { ...request, email: "private@example.com" }, valid: false },
  { name: "response rejects invalid UUID", definition: "ExternalAnalyzeResponse", payload: { ...response, request_id: "not-a-uuid" }, valid: false },
  { name: "response available requires model metadata", definition: "ExternalAnalyzeResponse", payload: { ...response, model: null }, valid: false },
  { name: "response rejects additional properties", definition: "ExternalAnalyzeResponse", payload: { ...response, trace: "private" }, valid: false },
  {
    name: "request rejects serialized body over 8 KiB",
    definition: "ExternalAnalyzeRequest",
    payload: { ...request, text: "😀".repeat(2000) },
    valid: false,
    enforceBodyBytes: true,
  },
];
for (const fixture of traceCagFixtures) {
  const validate = traceCagValidators[fixture.definition];
  const schemaAccepted = validate(fixture.payload);
  const bodyAccepted = !fixture.enforceBodyBytes || Buffer.byteLength(JSON.stringify(fixture.payload), "utf8") <= 8192;
  if ((schemaAccepted && bodyAccepted) !== fixture.valid) {
    throw new Error(
      `TraceCAG fixture "${fixture.name}" unexpectedly ${schemaAccepted && bodyAccepted ? "passed" : "failed"}: ${ajv.errorsText(validate.errors)}`,
    );
  }
}

for (const kind of ["valid", "invalid"]) {
  const directory = new URL(`frontend/test-fixtures/lexilingo/${kind}/`, root);
  for (const file of await readdir(directory)) {
    const cases = JSON.parse(await readFile(new URL(file, directory)));
    for (const testCase of cases) {
      const validate = ajv.getSchema(`${schema.$id}#/$defs/${testCase.definition}`);
      if (!validate) throw new Error(`Unknown definition: ${testCase.definition}`);
      const accepted = validate(testCase.payload);
      if (accepted !== (kind === "valid")) {
        throw new Error(`${kind}/${file}:${testCase.definition} unexpectedly ${accepted ? "passed" : "failed"}: ${ajv.errorsText(validate.errors)}`);
      }
    }
  }
}

console.log(`LexiLingo schema fixtures passed; TraceCAG contract SHA-256 ${hashes[0]}.`);
