import { element, formatNumber, replaceChildren } from './dom.js';
import { countActive, emptyFilters, isActive, toggleValue } from '../filters/filter-state.js';

const GROUPS = [
  { key: 'category', label: 'Category', facet: 'categories' },
  { key: 'tag', label: 'Tags', facet: 'tags' },
  { key: 'author', label: 'Author', facet: 'authors' },
];

function checkbox(label, count, checked, onChange) {
  return element('li', { class: 'facet' }, [
    element('label', { class: checked ? 'facet__label facet__label--active' : 'facet__label' }, [
      element('input', { type: 'checkbox', checked, onchange: onChange }),
      element('span', { class: 'facet__name', text: label }),
      element('span', { class: 'facet__count', text: formatNumber(count) }),
    ]),
  ]);
}

function group(definition, facets, filters, onFilters) {
  const counts = facets[definition.facet] ?? {};
  const names = Object.keys(counts);
  const selected = filters[definition.key] ?? [];

  for (const name of selected) {
    if (!names.includes(name)) {
      names.push(name);
    }
  }

  if (names.length === 0) {
    return null;
  }

  return element('div', { class: 'panel__group' }, [
    element('h3', { class: 'panel__heading', text: definition.label }),
    element(
      'ul',
      { class: 'facets' },
      names.map((name) =>
        checkbox(name, counts[name] ?? 0, isActive(filters, definition.key, name), () =>
          onFilters(toggleValue(filters, definition.key, name)),
        ),
      ),
    ),
  ]);
}

function dateRange(filters, onFilters) {
  return element('div', { class: 'panel__group' }, [
    element('h3', { class: 'panel__heading', text: 'Published' }),
    element('div', { class: 'date-range' }, [
      element('input', {
        type: 'date',
        value: filters.from,
        'aria-label': 'Published from',
        onchange: (event) => onFilters({ ...filters, from: event.target.value }),
      }),
      element('input', {
        type: 'date',
        value: filters.to,
        'aria-label': 'Published to',
        onchange: (event) => onFilters({ ...filters, to: event.target.value }),
      }),
    ]),
  ]);
}

function controls(state, controller) {
  return element('div', { class: 'panel__group' }, [
    element('h3', { class: 'panel__heading', text: 'Order' }),
    element(
      'select',
      {
        class: 'select',
        'aria-label': 'Sort order',
        onchange: (event) => controller.setSort(event.target.value),
      },
      [
        element('option', { value: 'relevance', text: 'Relevance', selected: state.sort === 'relevance' }),
        element('option', { value: 'newest', text: 'Newest first', selected: state.sort === 'newest' }),
        element('option', { value: 'oldest', text: 'Oldest first', selected: state.sort === 'oldest' }),
      ],
    ),
    element('label', { class: 'toggle' }, [
      element('input', {
        type: 'checkbox',
        checked: state.explain,
        onchange: (event) => controller.setExplain(event.target.checked),
      }),
      element('span', { text: 'Show ranking details' }),
    ]),
  ]);
}

export function mountFilterSidebar({ host, controller }) {
  const onFilters = (filters) => controller.setFilters(filters);

  controller.store.subscribe((state) => {
    const facets = state.response?.facets ?? {};
    const active = countActive(state.filters);

    const nodes = [
      element('div', { class: 'panel__header' }, [
        element('h2', { class: 'panel__title', text: 'Filters' }),
        active > 0
          ? element('button', {
              class: 'panel__clear',
              type: 'button',
              text: `Clear ${active}`,
              onclick: () => onFilters(emptyFilters()),
            })
          : null,
      ]),
      controls(state, controller),
      ...GROUPS.map((definition) => group(definition, facets, state.filters, onFilters)),
      dateRange(state.filters, onFilters),
      facets.truncated
        ? element('p', { class: 'panel__note', text: 'Facet counts are capped at 5000 matching documents.' })
        : null,
    ];

    replaceChildren(host, ...nodes);
  });
}
