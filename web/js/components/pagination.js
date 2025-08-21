import { element, replaceChildren } from './dom.js';

const WINDOW = 2;

function pageNumbers(current, total) {
  const pages = new Set([1, total]);

  for (let page = current - WINDOW; page <= current + WINDOW; page++) {
    if (page >= 1 && page <= total) {
      pages.add(page);
    }
  }

  return [...pages].sort((left, right) => left - right);
}

export function mountPagination({ host, controller }) {
  controller.store.subscribe((state) => {
    const response = state.response;

    if (response === null || response.pages <= 1) {
      replaceChildren(host);

      return;
    }

    const current = response.page;
    const nodes = [
      element('button', {
        class: 'pagination__step',
        type: 'button',
        text: 'Previous',
        disabled: current <= 1,
        onclick: () => controller.goToPage(current - 1),
      }),
    ];

    let previous = 0;

    for (const page of pageNumbers(current, response.pages)) {
      if (previous !== 0 && page - previous > 1) {
        nodes.push(element('span', { class: 'pagination__gap', text: '…' }));
      }

      nodes.push(
        element('button', {
          class: page === current ? 'pagination__page pagination__page--active' : 'pagination__page',
          type: 'button',
          text: String(page),
          'aria-current': page === current ? 'page' : null,
          onclick: () => controller.goToPage(page),
        }),
      );

      previous = page;
    }

    nodes.push(
      element('button', {
        class: 'pagination__step',
        type: 'button',
        text: 'Next',
        disabled: current >= response.pages,
        onclick: () => controller.goToPage(current + 1),
      }),
    );

    replaceChildren(host, ...nodes);
  });
}
