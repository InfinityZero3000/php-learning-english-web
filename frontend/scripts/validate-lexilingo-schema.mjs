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
for (const definition of ["ExternalAnalyzeRequest", "ExternalAnalyzeResponse"]) {
  if (!ajv.getSchema(`${traceCagSchema.$id}#/$defs/${definition}`)) {
    throw new Error(`TraceCAG schema definition did not compile: ${definition}`);
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
