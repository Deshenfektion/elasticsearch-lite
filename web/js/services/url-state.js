import { emptyFilters, fromParams } from '../filters/filter-state.js';

export function readUrl() {
  const params = new URLSearchParams(window.location.search);
  const page = Number.parseInt(params.get('page') ?? '1', 10);

  return {
    query: params.get('q') ?? '',
    filters: fromParams(params),
    page: Number.isNaN(page) || page < 1 ? 1 : page,
    sort: params.get('sort') ?? 'relevance',
    explain: params.get('explain') === '1',
  };
}

export function writeUrl(state, replace = false) {
  const params = new URLSearchParams();

  if (state.query !== '') {
    params.set('q', state.query);
  }

  for (const key of ['category', 'tag', 'author']) {
    const values = state.filters[key] ?? [];

    if (values.length > 0) {
      params.set(key, values.join(','));
    }
  }

  for (const key of ['from', 'to']) {
    if (state.filters[key]) {
      params.set(key, state.filters[key]);
    }
  }

  if (state.page > 1) {
    params.set('page', String(state.page));
  }

  if (state.sort !== 'relevance') {
    params.set('sort', state.sort);
  }

  if (state.explain) {
    params.set('explain', '1');
  }

  const query = params.toString();
  const url = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;

  if (replace) {
    window.history.replaceState(null, '', url);
  } else {
    window.history.pushState(null, '', url);
  }
}

export function defaultState() {
  return { query: '', filters: emptyFilters(), page: 1, sort: 'relevance', explain: false };
}
