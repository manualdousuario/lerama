// Copy only the Tabler webfont faces referenced by app.min.css. The package
// ships every weight plus ~106 MB of SVG fonts we never serve.
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const FACES = ['tabler-icons.woff2', 'tabler-icons.woff', 'tabler-icons.ttf'];

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const src = resolve(root, 'node_modules/@tabler/icons-webfont/dist/fonts');
const dest = resolve(root, 'public/assets/fonts');

mkdirSync(dest, { recursive: true });

for (const face of FACES) {
    copyFileSync(resolve(src, face), resolve(dest, face));
}

console.log(`Copied ${FACES.length} Tabler icon fonts to public/assets/fonts`);
