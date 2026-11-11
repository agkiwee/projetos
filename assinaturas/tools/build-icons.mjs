// Gera as máscaras dos ícones usadas pelo gerador de ícones coloridos (app/lib/icons.php).
//
// Cada máscara é um PNG opaco de 96x96 em que:
//   canal vermelho = cobertura da cor escolhida pelo usuário
//   canal verde    = cobertura de branco (o desenho dentro das formas preenchidas)
// O PHP combina os dois canais para montar o ícone final na cor pedida.
//
// Uso: npm install && npm run icones   (dentro de assinaturas/tools)
import { createRequire } from 'node:module';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as si from 'simple-icons';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const here = dirname(fileURLToPath(import.meta.url));
const outDir = join(here, '..', 'app', 'resources', 'icons');

const brand = (key) => ({ kind: 'fill', body: `<path d="${si[key].path}"/>` });
const line = (file) => {
  const svg = readFileSync(join(here, 'node_modules', 'lucide-static', 'icons', `${file}.svg`), 'utf8');
  const body = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
  return { kind: 'stroke', body };
};

// chave do ícone => origem do desenho
const ICONS = {
  phone: line('phone'), mobile: line('smartphone'), email: line('mail'), website: line('globe'),
  address: line('map-pin'), link: line('link'), calendar: line('calendar-days'), cart: line('shopping-cart'),
  document: line('file-text'), video: line('video'), rss: line('rss'), chat: line('message-circle'),
  whatsapp: brand('siWhatsapp'), facebook: brand('siFacebook'), instagram: brand('siInstagram'),
  linkedin: brand('siLinkedin'), x: brand('siX'), youtube: brand('siYoutube'), tiktok: brand('siTiktok'),
  telegram: brand('siTelegram'), pinterest: brand('siPinterest'), threads: brand('siThreads'),
  github: brand('siGithub'), behance: brand('siBehance'), spotify: brand('siSpotify'),
  discord: brand('siDiscord'), vimeo: brand('siVimeo'), medium: brand('siMedium'),
  linktree: brand('siLinktree'), messenger: brand('siMessenger'), twitch: brand('siTwitch'),
  dribbble: brand('siDribbble'), maps: brand('siGooglemaps'), calendly: brand('siCalendly'),
  tripadvisor: brand('siTripadvisor'), soundcloud: brand('siSoundcloud'),
};

const RED = '#ff0000';
const GREEN = '#00ff00';
const S = 96;

function glyph(icon, size, color) {
  const scale = size / 24;
  const offset = (S - size) / 2;
  const paint = icon.kind === 'fill'
    ? `fill="${color}"`
    : `fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"`;
  return `<g transform="translate(${offset} ${offset}) scale(${scale})" ${paint}>${icon.body}</g>`;
}

const SHAPES = {
  plain: (icon) => glyph(icon, icon.kind === 'fill' ? 84 : 88, RED),
  circle: (icon) => `<circle cx="48" cy="48" r="48" fill="${RED}"/>` + glyph(icon, 50, GREEN),
  square: (icon) => `<rect x="0" y="0" width="96" height="96" rx="18" fill="${RED}"/>` + glyph(icon, 52, GREEN),
  'outline-circle': (icon) => `<circle cx="48" cy="48" r="45" fill="none" stroke="${RED}" stroke-width="5"/>` + glyph(icon, 46, RED),
  'outline-square': (icon) => `<rect x="2.5" y="2.5" width="91" height="91" rx="8" fill="none" stroke="${RED}" stroke-width="5"/>` + glyph(icon, 48, RED),
};

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: S, height: S }, deviceScaleFactor: 1 });
let count = 0;
for (const [shape, draw] of Object.entries(SHAPES)) {
  mkdirSync(join(outDir, shape), { recursive: true });
  for (const [name, icon] of Object.entries(ICONS)) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${S}" height="${S}" viewBox="0 0 ${S} ${S}">${draw(icon)}</svg>`;
    await page.setContent(`<html><body style="margin:0;background:#000">${svg}</body></html>`);
    const png = await page.screenshot({ clip: { x: 0, y: 0, width: S, height: S }, type: 'png' });
    writeFileSync(join(outDir, shape, `${name}.png`), png);
    count++;
  }
}
await browser.close();
writeFileSync(join(outDir, 'index.json'), JSON.stringify({ icons: Object.keys(ICONS), shapes: Object.keys(SHAPES) }, null, 1) + '\n');
console.log(`${count} máscaras geradas em ${outDir}`);
