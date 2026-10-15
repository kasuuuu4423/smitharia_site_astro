import { decodeHtmlEscape } from './Util';

import type { Work } from './WP';

function toPlainText(value: string | false | undefined): string {
  if (typeof value !== 'string') return '';
  return decodeHtmlEscape(
    value
      .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
      .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '')
      .replace(/<[^>]*>/g, ' ')
  )
    .replace(/&#(x[\da-f]+|\d+);/gi, (entity, code: string) => {
      const codePoint = code.toLowerCase().startsWith('x')
        ? Number.parseInt(code.slice(1), 16)
        : Number.parseInt(code, 10);
      return codePoint > 0 && codePoint <= 0x10ffff ? String.fromCodePoint(codePoint) : entity;
    })
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function getWorkMetadata(work: Work) {
  const title = toPlainText(work.title.rendered);
  const description =
    [
      work.acf.project_summary,
      work.acf.description,
      work.acf.project_summary_en,
      work.acf.description_en,
      work.content.rendered,
      title,
    ]
      .map(toPlainText)
      .find(Boolean) || 'Creative Platform';
  const characters = Array.from(description);

  return {
    title,
    description: characters.length > 160 ? `${characters.slice(0, 159).join('')}…` : description,
    image: work.acf.thumbnail?.url || '/ith_motion.gif',
    imageAlt: toPlainText(work.acf.thumbnail?.alt) || title,
  };
}
