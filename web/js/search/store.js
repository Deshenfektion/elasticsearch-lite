export function createStore(initialState) {
  let state = { ...initialState };
  const listeners = new Set();

  return {
    get() {
      return state;
    },

    set(patch) {
      const next = typeof patch === 'function' ? patch(state) : patch;
      const merged = { ...state, ...next };
      const changed = Object.keys(merged).some((key) => merged[key] !== state[key]);

      if (!changed) {
        return state;
      }

      state = merged;

      for (const listener of listeners) {
        listener(state);
      }

      return state;
    },

    subscribe(listener) {
      listeners.add(listener);
      listener(state);

      return () => listeners.delete(listener);
    },
  };
}
