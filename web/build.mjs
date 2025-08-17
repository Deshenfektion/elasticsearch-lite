import { cp, mkdir, readdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as sass from 'sass';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'web');
const target = join(root, 'public');
const watch = process.argv.includes('--watch');

async function buildStyles() {
  const result = await sass.compileAsync(join(source, 'scss/main.scss'), {
    style: 'compressed',
    loadPaths: [join(source, 'scss')],
  });

  await mkdir(join(target, 'assets'), { recursive: true });
  await writeFile(join(target, 'assets/app.css'), result.css);

  return result.loadedUrls.map((url) => fileURLToPath(url));
}

async function copyScripts() {
  await rm(join(target, 'assets/js'), { recursive: true, force: true });
  await cp(join(source, 'js'), join(target, 'assets/js'), { recursive: true });
}

async function copyMarkup() {
  await cp(join(source, 'index.html'), join(target, 'index.html'));
}

async function build() {
  const started = Date.now();
  const styles = await buildStyles();
  await copyScripts();
  await copyMarkup();
  process.stdout.write(`built public/ in ${Date.now() - started} ms\n`);

  return styles;
}

async function collectFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const path = join(directory, entry.name);
    files.push(...(entry.isDirectory() ? await collectFiles(path) : [path]));
  }

  return files;
}

let building = false;

async function rebuild() {
  if (building) {
    return;
  }

  building = true;

  try {
    await build();
  } catch (error) {
    process.stderr.write(`build failed: ${error.message}\n`);
  } finally {
    building = false;
  }
}

await rebuild();

if (watch) {
  const { watch: watchDirectory } = await import('node:fs');
  const directories = [join(source, 'scss'), join(source, 'js'), source];

  for (const directory of directories) {
    watchDirectory(directory, { recursive: true }, () => {
      setTimeout(rebuild, 40);
    });
  }

  const tracked = await collectFiles(join(source, 'js'));
  process.stdout.write(`watching ${tracked.length} scripts and the stylesheet\n`);
}
