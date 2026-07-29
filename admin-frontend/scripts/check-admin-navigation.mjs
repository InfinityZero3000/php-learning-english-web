import assert from 'node:assert/strict';
import { access, readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { adminAliases, adminNavigation, navigationForRole } from '../src/lib/admin-navigation.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const canonicalItems = adminNavigation.flatMap((group) => group.items);

assert.equal(new Set(canonicalItems.map(({ href }) => href)).size, canonicalItems.length, 'Canonical routes must be unique');

for (const { href } of canonicalItems) {
  await access(join(root, 'src/app', href.slice(1), 'page.tsx'));
}

const adminHrefs = navigationForRole('admin').flatMap((group) => group.items.map(({ href }) => href));
const superHrefs = navigationForRole('super_admin').flatMap((group) => group.items.map(({ href }) => href));
const privilegedHrefs = adminNavigation.find(({ role }) => role === 'super_admin').items.map(({ href }) => href);

assert(privilegedHrefs.every((href) => !adminHrefs.includes(href)), 'Admin must not see Super Admin routes');
assert(canonicalItems.every(({ href }) => superHrefs.includes(href)), 'Super Admin must see both navigation zones');

for (const [alias, destination] of Object.entries(adminAliases)) {
  const source = await readFile(join(root, 'src/app', alias.slice(1), 'page.tsx'), 'utf8');
  assert.match(source, /import\s*{\s*redirect\s*}\s*from\s*['"]next\/navigation['"]/, `${alias} must use the server redirect API`);
  assert(source.includes(`redirect('${destination}')`) || source.includes(`redirect("${destination}")`), `${alias} must redirect to ${destination}`);
}

console.log(`Admin navigation OK: ${canonicalItems.length} canonical routes, ${Object.keys(adminAliases).length} aliases.`);
