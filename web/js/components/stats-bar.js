import { element, formatNumber, replaceChildren } from './dom.js';
import { api } from '../services/api.js';

export function mountStatsBar({ host, controller }) {
  controller.store.subscribe((state) => {
    const response = state.response;

    if (response === null) {
      replaceChildren(host, element('span', { class: 'result-meta__hint', text: 'Searching…' }));

      return;
    }

    const parts = [
      element('strong', { text: `${formatNumber(response.total)} ${response.total === 1 ? 'result' : 'results'}` }),
      element('span', { text: `${response.took_ms.toFixed(1)} ms` }),
    ];

    if (response.cached) {
      parts.push(element('span', { class: 'badge', text: 'cached' }));
    }

    if (state.loading) {
      parts.push(element('span', { class: 'badge badge--live', text: 'updating' }));
    }

    if (response.query?.rewritten && state.query !== '') {
      parts.push(element('code', { class: 'result-meta__query', text: response.query.rewritten }));
    }

    replaceChildren(host, ...parts);
  });
}

export function mountIndexStats({ host }) {
  api
    .statistics()
    .then((statistics) => {
      const index = statistics.index ?? {};
      const ranking = statistics.ranking ?? {};

      replaceChildren(
        host,
        element('h2', { class: 'panel__title', text: 'Index' }),
        element('dl', { class: 'stats' }, [
          element('dt', { text: 'Documents' }),
          element('dd', { text: formatNumber(index.documents ?? 0) }),
          element('dt', { text: 'Terms' }),
          element('dd', { text: formatNumber(index.terms ?? 0) }),
          element('dt', { text: 'Postings' }),
          element('dd', { text: formatNumber(index.postings ?? 0) }),
          element('dt', { text: 'Tokens' }),
          element('dd', { text: formatNumber(index.tokens ?? 0) }),
          element('dt', { text: 'Model' }),
          element('dd', { text: String(ranking.model ?? 'unknown').toUpperCase() }),
        ]),
      );
    })
    .catch(() => {
      replaceChildren(host, element('p', { class: 'panel__note', text: 'Index statistics are unavailable.' }));
    });
}
