import { element, replaceChildren } from './dom.js';
import { renderResult } from './result-item.js';

function emptyState(query) {
  return element('div', { class: 'placeholder' }, [
    element('h2', { text: query === '' ? 'Nothing indexed yet' : 'No documents matched' }),
    element('p', {
      text:
        query === ''
          ? 'Run "make seed" to index the demo corpus, then search.'
          : 'Try fewer terms, drop a filter, or use OR between words.',
    }),
  ]);
}

function errorState(error) {
  return element('div', { class: 'placeholder placeholder--error' }, [
    element('h2', { text: 'Search failed' }),
    element('p', { text: error.message }),
  ]);
}

export function mountResultList({ host, controller, onTagClick }) {
  controller.store.subscribe((state) => {
    host.classList.toggle('results--loading', state.loading);

    if (state.error !== null) {
      replaceChildren(host, errorState(state.error));

      return;
    }

    const response = state.response;

    if (response === null) {
      return;
    }

    if (response.hits.length === 0) {
      replaceChildren(host, emptyState(state.query));

      return;
    }

    const offset = (response.page - 1) * response.size;
    const nodes = response.hits.map((hit, index) => renderResult(hit, offset + index + 1, onTagClick));

    replaceChildren(host, ...nodes);
  });
}
