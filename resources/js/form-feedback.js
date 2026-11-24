/**
 * One error standard everywhere (owner, 2026-11-24): the user sees what
 * went wrong, where, and how to fix it.
 *
 * 1. Forms. After a submit comes back with mistakes (Laravel validation,
 *    window.xposFormErrors from partials/form-feedback), every field at
 *    fault gets a red outline and its own message right under it, a
 *    summary at the top of the form lists them all (each one jumps to its
 *    field), and the page opens at the first. Typing in a field clears its
 *    red. Fields that already print their own message aren't given a
 *    second; a page with its own <x-error-summary> keeps that one.
 *
 * 2. Actions that call the server from the page (POS, kitchen board, rider
 *    app, cart): failureMessage() turns a failed response, or no response
 *    at all, into a sentence that says what happened and what to do,
 *    instead of a bare "Action failed."
 */
const text = (key, fallback) => (window.xposText && window.xposText[key]) || fallback;

const GENERIC = ['Server Error', 'This action is unauthorized.', 'Unauthenticated.', 'CSRF token mismatch.', 'Not Found', 'Too Many Attempts.', 'Service Unavailable'];

/**
 * @param {Response|null} response the failed response, or null when the request never got one
 * @param {object|null} payload its JSON body, if it had one
 * @param {string} fallback what to say when nothing better is known
 */
export function failureMessage(response, payload = null, fallback = '') {
    if (! response) {
        return navigator.onLine === false ? text('offline') : text('network');
    }

    const status = response.status;

    if (status === 422 && payload && payload.errors) {
        return Object.values(payload.errors).flat().join(' ');
    }

    const message = payload && typeof payload.message === 'string' ? payload.message : '';
    if (message && status < 500 && ! GENERIC.includes(message)) {
        return message;
    }

    switch (status) {
        case 401: return text('signedOut');
        case 403: return text('forbidden');
        case 404: return text('notFound');
        case 413: return text('tooLarge');
        case 419: return text('expired');
        case 429: return text('tooMany');
        case 503: return text('unavailable');
    }

    return status >= 500 ? text('server') : (fallback || text('server'));
}

window.failureMessage = failureMessage;

/**
 * For a catch block: a request that never reached the server (no
 * internet) says so; anything else keeps its own message.
 */
export function explainError(error, fallback = '') {
    const message = error && error.message ? String(error.message) : '';
    if (error && error.name === 'TypeError' && /fetch|network|load failed/i.test(message)) {
        return failureMessage(null);
    }

    return message || fallback || text('server');
}

window.explainError = explainError;

/* ---------- Forms ---------- */

const LAST_FORM = 'xpos-last-form';

/** Which form on the page this is, stable across a reload. */
function signature(form) {
    const action = form.getAttribute('action') || location.pathname;
    const same = [...document.querySelectorAll('form')].filter((f) => (f.getAttribute('action') || location.pathname) === action);

    return action + '#' + same.indexOf(form);
}

/** "items.0.name" → the names a field with that error could have. */
function namesFor(key) {
    const parts = key.split('.');
    const bracketed = parts[0] + parts.slice(1).map((p) => `[${p}]`).join('');
    const names = [key, bracketed];
    if (/\.\d+$/.test(key)) {
        names.push(parts[0] + parts.slice(1, -1).map((p) => `[${p}]`).join('') + '[]');
    }

    return names;
}

function fieldFor(key, scope) {
    for (const name of namesFor(key)) {
        const field = scope.querySelector(`[name="${CSS.escape(name)}"]:not([type="hidden"])`);
        if (field) return field;
    }

    return null;
}

/** The visible thing to outline: the field, or the custom control a hidden input stands for. */
function outlineTarget(field) {
    if (field.type === 'radio' || field.type === 'checkbox') {
        return field.closest('fieldset, [role="group"], label') || field;
    }

    return field;
}

function alreadyShown(field, message) {
    const block = field.closest('div, fieldset, label, td, li') || field.parentElement;

    return block && block.textContent.includes(message);
}

function messageElement(message) {
    const p = document.createElement('p');
    p.className = 'xpos-error-text';
    p.setAttribute('role', 'alert');
    p.textContent = message;

    return p;
}

function markField(field, messages) {
    const target = outlineTarget(field);
    target.classList.add('xpos-invalid');
    field.setAttribute('aria-invalid', 'true');

    const message = messages[0];
    if (! alreadyShown(field, message)) {
        const after = field.type === 'radio' || field.type === 'checkbox' ? target : field;
        const note = messageElement(message);
        note.dataset.xposFor = field.name;
        after.insertAdjacentElement('afterend', note);
    }

    const clear = () => {
        target.classList.remove('xpos-invalid');
        field.removeAttribute('aria-invalid');
        document.querySelectorAll(`.xpos-error-text[data-xpos-for="${CSS.escape(field.name)}"]`).forEach((el) => el.remove());
    };
    field.addEventListener('input', clear, { once: true });
    field.addEventListener('change', clear, { once: true });
}

function summary(entries) {
    const box = document.createElement('div');
    box.className = 'xpos-error-summary';
    box.setAttribute('role', 'alert');
    box.setAttribute('tabindex', '-1');
    box.dataset.errorSummary = '';

    const heading = document.createElement('p');
    heading.className = 'xpos-error-summary-title';
    heading.textContent = entries.length === 1 ? text('summaryOne') : text('summaryMany').replace(':count', entries.length);
    box.appendChild(heading);

    const list = document.createElement('ul');
    for (const { message, field } of entries) {
        const item = document.createElement('li');
        if (field) {
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = message;
            link.addEventListener('click', (event) => {
                event.preventDefault();
                outlineTarget(field).scrollIntoView({ block: 'center', behavior: 'smooth' });
                field.focus({ preventScroll: true });
            });
            item.appendChild(link);
        } else {
            item.textContent = message;
        }
        list.appendChild(item);
    }
    box.appendChild(list);

    return box;
}

function showFormErrors() {
    const errors = window.xposFormErrors;
    if (! errors || ! Object.keys(errors).length) return;

    let form = null;
    try {
        const last = sessionStorage.getItem(LAST_FORM);
        sessionStorage.removeItem(LAST_FORM);
        form = [...document.querySelectorAll('form')].find((f) => signature(f) === last) || null;
    } catch (e) { /* storage blocked: search the whole page */ }

    const entries = Object.entries(errors).map(([key, messages]) => {
        const field = (form && fieldFor(key, form)) || fieldFor(key, document);
        if (field) markField(field, messages);

        return { message: messages[0], field };
    });

    const first = entries.find((e) => e.field)?.field;
    const host = form || first?.closest('form');
    let box = document.querySelector('[data-error-summary]');

    if (! box) {
        box = summary(entries);
        if (host) {
            host.insertAdjacentElement('afterbegin', box);
        } else {
            (document.querySelector('main') || document.body).insertAdjacentElement('afterbegin', box);
        }
    }

    (box || first)?.scrollIntoView({ block: 'center' });
    first?.focus({ preventScroll: true });
}

document.addEventListener('submit', (event) => {
    try {
        sessionStorage.setItem(LAST_FORM, signature(event.target));
    } catch (e) { /* storage blocked: errors are matched page-wide */ }
}, true);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showFormErrors);
} else {
    showFormErrors();
}
