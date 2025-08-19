import { api, ApiError } from '../services/api.js';
import { toParams } from '../filters/filter-state.js';
import { createStore } from './store.js';
import { debounce } from './debounce.js';
import { writeUrl } from '../services/url-state.js';

const PAGE_SIZE = 10;

export function createSearchController(initial) {
  const store = createStore({
    query: initial.query,
    filters: initial.filters,
    page: initial.page,
    sort: initial.sort,
    explain: initial.explain,
    response: null,
    loading: false,
    error: null,
    suggestions: null,
  });

  let searchController = null;
  let suggestController = null;
  let sequence = 0;

  async function run({ pushUrl = true } = {}) {
    const state = store.get();

    if (pushUrl) {
      writeUrl(state, state.response === null);
    }

    if (searchController !== null) {
      searchController.abort();
    }

    searchController = new AbortController();
    const ticket = ++sequence;
    store.set({ loading: true, error: null });

    try {
      const response = await api.search(
        {
          q: state.query,
          page: state.page,
          size: PAGE_SIZE,
          sort: state.sort,
          explain: state.explain ? '1' : '',
          highlight: '1',
          ...toParams(state.filters),
        },
        searchController.signal,
      );

      if (ticket === sequence) {
        store.set({ response, loading: false });
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      if (ticket === sequence) {
        store.set({
          loading: false,
          error: error instanceof ApiError ? error : new ApiError('Search is unavailable', 0, 'network'),
        });
      }
    }
  }

  const runDebounced = debounce(() => run(), 180);

  async function loadSuggestions(query) {
    if (query.trim().length < 2) {
      store.set({ suggestions: null });

      return;
    }

    if (suggestController !== null) {
      suggestController.abort();
    }

    suggestController = new AbortController();

    try {
      const suggestions = await api.suggest(query, suggestController.signal);
      store.set({ suggestions });
    } catch (error) {
      if (error.name !== 'AbortError') {
        store.set({ suggestions: null });
      }
    }
  }

  const loadSuggestionsDebounced = debounce(loadSuggestions, 120);

  return {
    store,

    type(query) {
      store.set({ query, page: 1 });
      loadSuggestionsDebounced(query);
      runDebounced();
    },

    submit(query) {
      runDebounced.cancel();
      loadSuggestionsDebounced.cancel();
      store.set({ query, page: 1, suggestions: null });

      return run();
    },

    setFilters(filters) {
      store.set({ filters, page: 1 });

      return run();
    },

    setSort(sort) {
      store.set({ sort, page: 1 });

      return run();
    },

    setExplain(explain) {
      store.set({ explain });

      return run();
    },

    goToPage(page) {
      store.set({ page });
      window.scrollTo({ top: 0, behavior: 'smooth' });

      return run();
    },

    dismissSuggestions() {
      store.set({ suggestions: null });
    },

    restore(state) {
      store.set({ ...state, suggestions: null });

      return run({ pushUrl: false });
    },

    start() {
      return run({ pushUrl: false });
    },
  };
}
