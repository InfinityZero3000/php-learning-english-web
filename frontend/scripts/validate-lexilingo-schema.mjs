import { readFile, readdir } from "node:fs/promises";
import Ajv2020 from "ajv/dist/2020.js";
import addFormats from "ajv-formats";

const root = new URL("../../", import.meta.url);
const schema = JSON.parse(await readFile(new URL("docs/openapi/lexilingo-import.schema.json", root)));
const ajv = new Ajv2020({ allErrors: true });
addFormats(ajv);
ajv.addSchema(schema);

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

console.log("LexiLingo schema fixtures passed.");
