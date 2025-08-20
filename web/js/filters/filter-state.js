export const EMPTY_FILTERS = Object.freeze({
  category: [],
  tag: [],
  author: [],
  from: '',
  to: '',
});

export function emptyFilters() {
  return { category: [], tag: [], author: [], from: '', to: '' };
}

export function toggleValue(filters, key, value) {
  const current = filters[key] ?? [];
  const next = current.includes(value) ? current.filter((entry) => entry !== value) : [...current, value];

  return { ...filters, [key]: next };
}

export function isActive(filters, key, value) {
  return (filters[key] ?? []).includes(value);
}

export function countActive(filters) {
  return (
    filters.category.length +
    filters.tag.length +
    filters.author.length +
    (filters.from === '' ? 0 : 1) +
    (filters.to === '' ? 0 : 1)
  );
}

export function toParams(filters) {
  return {
    category: filters.category,
    tag: filters.tag,
    author: filters.author,
    from: filters.from,
    to: filters.to,
  };
}

export function fromParams(params) {
  const list = (key) => {
    const value = params.get(key);

    return value === null || value === '' ? [] : value.split(',').filter(Boolean);
  };

  return {
    category: list('category'),
    tag: list('tag'),
    author: list('author'),
    from: params.get('from') ?? '',
    to: params.get('to') ?? '',
  };
}
