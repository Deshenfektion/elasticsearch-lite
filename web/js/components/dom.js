export function element(tag, attributes = {}, children = []) {
  const node = document.createElement(tag);

  for (const [name, value] of Object.entries(attributes)) {
    if (value === null || value === undefined || value === false) {
      continue;
    }

    if (name === 'class') {
      node.className = value;
    } else if (name === 'html') {
      node.innerHTML = value;
    } else if (name === 'text') {
      node.textContent = String(value);
    } else if (name.startsWith('on') && typeof value === 'function') {
      node.addEventListener(name.slice(2), value);
    } else if (value === true) {
      node.setAttribute(name, '');
    } else {
      node.setAttribute(name, String(value));
    }
  }

  for (const child of Array.isArray(children) ? children : [children]) {
    if (child === null || child === undefined || child === false) {
      continue;
    }

    node.append(typeof child === 'string' ? document.createTextNode(child) : child);
  }

  return node;
}

export function replaceChildren(host, ...children) {
  host.replaceChildren(...children.filter((child) => child !== null && child !== undefined && child !== false));
}

export function formatDate(value) {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  return Number.isNaN(date.getTime())
    ? null
    : date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' });
}

export function formatNumber(value) {
  return new Intl.NumberFormat('en-GB').format(value);
}
