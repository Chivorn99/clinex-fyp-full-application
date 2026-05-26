import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

function parseArgs(argv) {
  const args = {
    md: 'clinex_system_design.md',
    html: 'clinex-system-design.html',
    help: false,
  };

  for (let i = 2; i < argv.length; i++) {
    const arg = argv[i];

    if (arg === '--help' || arg === '-h') {
      args.help = true;
      continue;
    }

    if (arg === '--md') {
      const value = argv[++i];
      if (!value) throw new Error('Missing value for --md');
      args.md = value;
      continue;
    }

    if (arg === '--html') {
      const value = argv[++i];
      if (!value) throw new Error('Missing value for --html');
      args.html = value;
      continue;
    }

    throw new Error(`Unknown argument: ${arg}`);
  }

  return args;
}

function printHelp() {
  // Keep this minimal; the defaults are what you want 99% of the time.
  // eslint-disable-next-line no-console
  console.log(`Usage:
  node tools/generate-system-design-html.mjs

Options:
  --md <path>     Markdown input (default: clinex_system_design.md)
  --html <path>   HTML file to update (default: clinex-system-design.html)
`);
}

const args = parseArgs(process.argv);
if (args.help) {
  printHelp();
  process.exit(0);
}

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDir, '..');

const mdPath = path.resolve(repoRoot, args.md);
const htmlPath = path.resolve(repoRoot, args.html);

const startTag = '<script type="text/markdown" id="md-content">';

try {
  const [mdRaw, htmlRaw] = await Promise.all([
    fs.readFile(mdPath, 'utf8'),
    fs.readFile(htmlPath, 'utf8'),
  ]);

  const startIndex = htmlRaw.indexOf(startTag);
  if (startIndex === -1) {
    throw new Error(
      `Could not find the markdown container in ${path.relative(repoRoot, htmlPath)}.\n` +
        `Expected to find: ${startTag}`,
    );
  }

  const contentStart = startIndex + startTag.length;
  const endIndex = htmlRaw.indexOf('</script>', contentStart);
  if (endIndex === -1) {
    throw new Error(
      `Could not find closing </script> for markdown container in ${path.relative(repoRoot, htmlPath)}.`,
    );
  }

  // Guard against prematurely closing the <script> tag.
  // (Rare, but safe for code blocks that might contain </script>.)
  const mdSafe = mdRaw.replaceAll('</script>', '<\\/script>').trimEnd();

  const replacement = `\n${mdSafe}\n`;
  const htmlUpdated = htmlRaw.slice(0, contentStart) + replacement + htmlRaw.slice(endIndex);

  await fs.writeFile(htmlPath, htmlUpdated, 'utf8');

  const mdRel = path.relative(repoRoot, mdPath);
  const htmlRel = path.relative(repoRoot, htmlPath);

  // eslint-disable-next-line no-console
  console.log(`Updated ${htmlRel} from ${mdRel}`);
} catch (error) {
  // eslint-disable-next-line no-console
  console.error(error instanceof Error ? error.message : error);
  process.exit(1);
}
