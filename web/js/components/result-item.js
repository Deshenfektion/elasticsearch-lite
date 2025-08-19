import { element, formatDate } from './dom.js';
import { renderExplanation } from './score-details.js';

function snippet(hit) {
  const fragments = hit.highlights?.body ?? [];

  if (fragments.length === 0) {
    return null;
  }

  return element('p', { class: 'result__snippet', html: fragments.join(' <span class="result__gap">…</span> ') });
}

function title(hit) {
  const heading = hit.highlights?.title?.[0] ?? escapeText(hit.title);

  if (hit.url) {
    return element('a', { class: 'result__title', href: hit.url, rel: 'noreferrer noopener', html: heading });
  }

  return element('span', { class: 'result__title', html: heading });
}

function escapeText(value) {
  const node = document.createElement('span');
  node.textContent = value;

  return node.innerHTML;
}

function meta(hit, onTagClick) {
  const parts = [element('span', { class: 'result__score', text: `score ${hit.score.toFixed(3)}` })];

  if (hit.category) {
    parts.push(element('span', { class: 'result__category', text: hit.category }));
  }

  if (hit.author) {
    parts.push(element('span', { text: hit.author }));
  }

  const published = formatDate(hit.published_at);

  if (published) {
    parts.push(element('span', { text: published }));
  }

  parts.push(element('span', { class: 'result__tokens', text: `${hit.token_count} tokens` }));

  const tags = (hit.tags ?? []).map((tag) =>
    element('button', { class: 'result__tag', type: 'button', text: tag, onclick: () => onTagClick(tag) }),
  );

  return element('div', { class: 'result__meta' }, [...parts, ...tags]);
}

export function renderResult(hit, position, onTagClick) {
  return element('article', { class: 'result' }, [
    element('div', { class: 'result__rank', text: String(position) }),
    element('div', { class: 'result__body' }, [
      title(hit),
      meta(hit, onTagClick),
      snippet(hit),
      renderExplanation(hit.explanation),
    ]),
  ]);
}
