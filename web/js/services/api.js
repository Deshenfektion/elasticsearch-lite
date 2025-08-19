const BASE = '/api';

class ApiError extends Error {
  constructor(message, status, type) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.type = type;
  }
}

async function request(path, params, signal) {
  const url = new URL(BASE + path, window.location.origin);

  for (const [key, value] of Object.entries(params ?? {})) {
    if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
      continue;
    }

    url.searchParams.set(key, Array.isArray(value) ? value.join(',') : String(value));
  }

  const response = await fetch(url, { signal, headers: { accept: 'application/json' } });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = payload.error ?? {};
    throw new ApiError(error.message ?? 'Request failed', response.status, error.type ?? 'unknown');
  }

  return payload;
}

export const api = {
  search(params, signal) {
    return request('/search', params, signal);
  },

  suggest(query, signal) {
    return request('/suggest', { q: query, size: 8 }, signal);
  },

  statistics(signal) {
    return request('/statistics', {}, signal);
  },
};

export { ApiError };
