import { renderSuggestions } from './suggestions.js';

export function mountSearchBar({ form, input, suggestionList, controller }) {
  let activeIndex = -1;
  let flatSuggestions = [];

  input.value = controller.store.get().query;

  input.addEventListener('input', () => {
    activeIndex = -1;
    controller.type(input.value);
  });

  input.addEventListener('keydown', (event) => {
    if (flatSuggestions.length === 0 && event.key !== 'Escape') {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      activeIndex = Math.min(activeIndex + 1, flatSuggestions.length - 1);
      highlightActive();

      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      activeIndex = Math.max(activeIndex - 1, -1);
      highlightActive();

      return;
    }

    if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault();
      accept(flatSuggestions[activeIndex]);

      return;
    }

    if (event.key === 'Escape') {
      controller.dismissSuggestions();
      activeIndex = -1;
    }
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    activeIndex = -1;
    controller.submit(input.value);
  });

  document.addEventListener('click', (event) => {
    if (!form.contains(event.target)) {
      controller.dismissSuggestions();
    }
  });

  function accept(query) {
    input.value = query;
    activeIndex = -1;
    controller.submit(query);
    input.focus();
  }

  function highlightActive() {
    for (const [index, node] of [...suggestionList.children].entries()) {
      node.classList.toggle('suggestions__item--active', index === activeIndex);
    }
  }

  controller.store.subscribe((state) => {
    if (document.activeElement !== input) {
      input.value = state.query;
    }

    flatSuggestions = renderSuggestions(suggestionList, state.suggestions, accept);
    const open = flatSuggestions.length > 0;
    suggestionList.hidden = !open;
    input.setAttribute('aria-expanded', open ? 'true' : 'false');

    if (!open) {
      activeIndex = -1;
    }

    highlightActive();
  });
}
