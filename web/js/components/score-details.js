import { element } from './dom.js';

function node(explanation) {
  const children = (explanation.details ?? []).map(node);

  return element('li', { class: 'explanation__node' }, [
    element('span', { class: 'explanation__value', text: explanation.value.toFixed(4) }),
    element('span', { class: 'explanation__description', text: explanation.description }),
    children.length > 0 ? element('ul', { class: 'explanation__children' }, children) : null,
  ]);
}

export function renderExplanation(explanation) {
  if (!explanation) {
    return null;
  }

  return element('details', { class: 'explanation' }, [
    element('summary', { text: `Why this result — score ${explanation.value.toFixed(4)}` }),
    element('ul', { class: 'explanation__tree' }, [node(explanation)]),
  ]);
}
