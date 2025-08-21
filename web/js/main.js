import { createSearchController } from './search/controller.js';
import { mountSearchBar } from './components/search-bar.js';
import { mountResultList } from './components/result-list.js';
import { mountFilterSidebar } from './components/filter-sidebar.js';
import { mountPagination } from './components/pagination.js';
import { mountIndexStats, mountStatsBar } from './components/stats-bar.js';
import { readUrl } from './services/url-state.js';
import { toggleValue } from './filters/filter-state.js';

const controller = createSearchController(readUrl());

mountSearchBar({
  form: document.getElementById('search-form'),
  input: document.getElementById('search-input'),
  suggestionList: document.getElementById('suggestions'),
  controller,
});

mountFilterSidebar({ host: document.getElementById('filters'), controller });

mountResultList({
  host: document.getElementById('results'),
  controller,
  onTagClick: (tag) => controller.setFilters(toggleValue(controller.store.get().filters, 'tag', tag)),
});

mountPagination({ host: document.getElementById('pagination'), controller });
mountStatsBar({ host: document.getElementById('result-meta'), controller });
mountIndexStats({ host: document.getElementById('index-stats') });

window.addEventListener('popstate', () => {
  controller.restore(readUrl());
});

controller.start();
