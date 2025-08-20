import { element, formatNumber, replaceChildren } from './dom.js';

export function renderSuggestions(host, suggestions, onAccept) {
  if (suggestions === null) {
    replaceChildren(host);

    return [];
  }

  const items = [];
  const nodes = [];

  for (const term of suggestions.terms ?? []) {
    items.push(term.query);
    nodes.push(
      element('li', { class: 'suggestions__item', role: 'option', onclick: () => onAccept(term.query) }, [
        element('span', { class: 'suggestions__term', text: term.term }),
        element('span', { class: 'suggestions__count', text: `${formatNumber(term.documents)} docs` }),
      ]),
    );
  }

  for (const popular of suggestions.queries ?? []) {
    if (items.includes(popular.query)) {
      continue;
    }

    items.push(popular.query);
    nodes.push(
      element('li', { class: 'suggestions__item', role: 'option', onclick: () => onAccept(popular.query) }, [
        element('span', { class: 'suggestions__term', text: popular.query }),
        element('span', { class: 'suggestions__count', text: `${formatNumber(popular.searches)} searches` }),
      ]),
    );
  }

  replaceChildren(host, ...nodes);

  return items;
}
