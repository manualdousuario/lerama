// Tailwind inlines the tabler-icons-webfont @import but keeps its relative
// url(./fonts/...), which would resolve against public/assets/css/. Rewrite
// them to the absolute path where the fonts are actually served.
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const cssPath = resolve(root, 'public/assets/css/app.min.css');

writeFileSync(cssPath, readFileSync(cssPath, 'utf8').replaceAll('url(./fonts/', 'url(/assets/fonts/'));

console.log('Rewrote Tabler icon font paths in app.min.css');
