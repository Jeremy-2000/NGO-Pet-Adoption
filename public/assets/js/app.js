document.addEventListener('DOMContentLoaded', () => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const confirmDialog = document.createElement('dialog');
  confirmDialog.className = 'app-dialog confirm-dialog';
  confirmDialog.innerHTML = `
    <div class="dialog-shell">
      <header class="dialog-header">
        <div>
          <p class="eyebrow">Confirm</p>
          <h2 data-confirm-title>Continue?</h2>
        </div>
        <button class="dialog-close" type="button" data-confirm-cancel>Close</button>
      </header>
      <p class="dialog-copy" data-confirm-message></p>
      <div class="dialog-actions">
        <button class="btn secondary" type="button" data-confirm-cancel>Cancel</button>
        <button class="btn green" type="button" data-confirm-accept>Confirm</button>
      </div>
    </div>
  `;
  document.body.appendChild(confirmDialog);

  const askConfirm = (message) => new Promise((resolve) => {
    if (typeof confirmDialog.showModal !== 'function') {
      resolve(window.confirm(message || 'Are you sure?'));
      return;
    }

    confirmDialog.querySelector('[data-confirm-message]').textContent = message || 'Are you sure?';
    const accept = confirmDialog.querySelector('[data-confirm-accept]');
    const cancelButtons = confirmDialog.querySelectorAll('[data-confirm-cancel]');
    const cleanup = (value) => {
      accept.removeEventListener('click', onAccept);
      cancelButtons.forEach((button) => button.removeEventListener('click', onCancel));
      confirmDialog.removeEventListener('cancel', onCancel);
      confirmDialog.removeEventListener('close', onClose);

      if (confirmDialog.open) {
        confirmDialog.close();
      }

      resolve(value);
    };
    const onAccept = () => cleanup(true);
    const onCancel = () => cleanup(false);
    const onClose = () => cleanup(false);

    accept.addEventListener('click', onAccept);
    cancelButtons.forEach((button) => button.addEventListener('click', onCancel));
    confirmDialog.addEventListener('cancel', onCancel);
    confirmDialog.addEventListener('close', onClose);

    confirmDialog.showModal();
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    const submitter = event.submitter;
    const message = submitter?.dataset.confirm || form.dataset.confirm;

    if (!message || form.dataset.confirmed === '1') return;

    event.preventDefault();

    if (await askConfirm(message)) {
      form.dataset.confirmed = '1';
      form.requestSubmit(submitter || undefined);
      window.setTimeout(() => {
        form.dataset.confirmed = '0';
      }, 0);
    }
  });

  document.querySelectorAll('[data-copy-text]').forEach((button) => {
    button.addEventListener('click', async () => {
      const original = button.textContent;

      try {
        await navigator.clipboard.writeText(button.dataset.copyText || '');
        button.textContent = 'Copied';
      } catch (error) {
        button.textContent = 'Copy failed';
      }

      window.setTimeout(() => {
        button.textContent = original;
      }, 1600);
    });
  });

  document.querySelectorAll('[data-account-type]').forEach((select) => {
    const fields = document.querySelectorAll('[data-shelter-field]');
    const syncFields = () => {
      const isShelter = select.value === 'shelter';

      fields.forEach((field) => {
        field.hidden = !isShelter;
      });
    };

    select.addEventListener('change', syncFields);
    syncFields();
  });

  document.querySelectorAll('[data-answer-type]').forEach((select) => {
    const container = select.closest('form');
    const choiceOptions = container?.querySelector('[data-choice-options]');
    const syncOptions = () => {
      if (choiceOptions) {
        choiceOptions.hidden = select.value !== 'choice';
      }
    };

    select.addEventListener('change', syncOptions);
    syncOptions();
  });

  document.querySelectorAll('.image-manager select[name*="[crop_focus]"]').forEach((select) => {
    select.addEventListener('change', () => {
      const image = select.closest('article')?.querySelector('img');

      if (image) {
        image.style.objectPosition = select.value;
      }
    });
  });

  document.querySelectorAll('[data-open-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(button.dataset.openDialog || '');

      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
      }
    });
  });

  document.querySelectorAll('dialog[data-auto-open]').forEach((dialog) => {
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    }
  });

  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });

    dialog.querySelectorAll('[data-close-dialog]').forEach((button) => {
      button.addEventListener('click', () => dialog.close());
    });
  });

  document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    carousel.dataset.index = carousel.dataset.index || '0';
    const slides = carousel.querySelector('.slides');
    const total = carousel.querySelectorAll('.slide').length;

    const move = (direction) => {
      if (!slides || total < 2) return;
      const current = Number.parseInt(carousel.dataset.index || '0', 10);
      const next = (current + direction + total) % total;
      carousel.dataset.index = String(next);
      slides.style.transform = `translateX(-${next * 100}%)`;
    };

    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => move(-1));
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => move(1));
  });

  const normalizeText = (value) => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
  const emptyFilterValue = '__empty__';

  document.querySelectorAll('[data-enhanced-table]').forEach((table, tableIndex) => {
    const tbody = table.tBodies[0];

    if (!tbody || table.dataset.tableReady === '1') return;

    table.dataset.tableReady = '1';
    const originalRows = Array.from(tbody.rows);
    const headers = Array.from(table.tHead?.rows[0]?.cells || []);
    const labels = headers.map((header) => header.textContent.replace(/\s+/g, ' ').trim());
    const wrap = table.closest('.table-wrap') || table.parentElement;
    const controls = document.createElement('form');
    const searchLabel = document.createElement('label');
    const searchCaption = document.createElement('span');
    const search = document.createElement('input');
    const actions = document.createElement('div');
    const clear = document.createElement('button');
    const count = document.createElement('p');
    const empty = document.createElement('p');
    const menus = [];
    const storageKey = table.dataset.tableKey ? `petAdoption.table.${table.dataset.tableKey}` : `petAdoption.table.${window.location.pathname}.${tableIndex}`;
    const storedState = (() => {
      try {
        return JSON.parse(window.localStorage.getItem(storageKey) || '{}');
      } catch (error) {
        return {};
      }
    })();
    let filterState = {};
    let sortState = {
      column: Number.isInteger(storedState.sort?.column) ? storedState.sort.column : null,
      direction: storedState.sort?.direction === 'desc' ? 'desc' : storedState.sort?.direction === 'asc' ? 'asc' : 'none',
    };

    Object.entries(storedState.filters || {}).forEach(([column, values]) => {
      if (Array.isArray(values)) {
        filterState[column] = values;
      }
    });

    controls.className = 'table-tools table-tools-sharepoint';
    searchCaption.textContent = 'Search';
    search.type = 'search';
    search.placeholder = 'Search this table';
    search.dataset.tableSearch = '1';
    search.value = typeof storedState.search === 'string' ? storedState.search : '';
    searchLabel.append(searchCaption, search);

    clear.className = 'btn secondary small';
    clear.type = 'button';
    clear.textContent = 'Clear';
    actions.className = 'table-tool-actions';
    actions.appendChild(clear);
    count.className = 'table-count muted';
    count.setAttribute('aria-live', 'polite');
    controls.append(searchLabel, actions, count);

    empty.className = 'empty-state table-empty';
    empty.hidden = true;
    empty.textContent = table.dataset.tableEmpty || 'No rows match these filters.';

    rowsSetLabels();

    headers.forEach((header, index) => {
      const label = labels[index] || 'Column';
      const skipFilter = header.dataset.noFilter === 'true';
      const skipSort = header.dataset.noSort === 'true';
      const values = collectColumnValues(index);
      const headerWrap = document.createElement('div');
      const trigger = document.createElement('button');
      const labelText = document.createElement('span');
      const state = document.createElement('span');

      headerWrap.className = 'table-head-wrap';
      trigger.type = 'button';
      trigger.className = 'table-filter-button';
      trigger.dataset.column = String(index);
      labelText.textContent = label;
      state.className = 'table-filter-state';
      state.setAttribute('aria-hidden', 'true');
      trigger.append(labelText, state);
      header.textContent = '';
      header.appendChild(headerWrap);
      headerWrap.appendChild(trigger);

      if (skipFilter && skipSort) {
        trigger.disabled = true;
        return;
      }

      const menu = buildColumnMenu(index, label, values, skipFilter, skipSort);
      headerWrap.appendChild(menu);
      menus.push({ index, trigger, menu, values, skipFilter, skipSort });

      trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        closeMenus(menu);
        menu.hidden = !menu.hidden;
        trigger.setAttribute('aria-expanded', String(!menu.hidden));
      });
    });

    cleanStoredState();

    if (wrap && wrap.parentNode) {
      wrap.parentNode.insertBefore(controls, wrap);
      wrap.parentNode.insertBefore(empty, wrap.nextSibling);
    }

    controls.addEventListener('submit', (event) => {
      event.preventDefault();
      applyTableState();
    });

    search.addEventListener('input', () => applyTableState());

    clear.addEventListener('click', () => {
      search.value = '';
      filterState = {};
      sortState = { column: null, direction: 'none' };
      try {
        window.localStorage.removeItem(storageKey);
      } catch (error) {
        // Keep the visible table reset even if localStorage is unavailable.
      }
      applyTableState({ persist: false });
    });

    document.addEventListener('click', () => closeMenus());
    table.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeMenus();
      }
    });

    applyTableState();

    function rowsSetLabels() {
      originalRows.forEach((row) => {
        Array.from(row.cells).forEach((cell, index) => {
          if (!cell.hasAttribute('data-label')) {
            cell.setAttribute('data-label', labels[index] || '');
          }
        });
      });
    }

    function cellText(row, index) {
      return (row.cells[index]?.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function valueKey(value) {
      const text = String(value || '').trim();
      return text === '' ? emptyFilterValue : normalizeText(text);
    }

    function collectColumnValues(index) {
      const unique = new Map();

      originalRows.forEach((row) => {
        const text = cellText(row, index);
        unique.set(valueKey(text), text === '' ? '(Empty)' : text);
      });

      return Array.from(unique.entries())
        .map(([key, label]) => ({ key, label }))
        .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' }));
    }

    function buildColumnMenu(index, label, values, skipFilter, skipSort) {
      const menu = document.createElement('div');
      const actionList = document.createElement('div');
      const asc = document.createElement('button');
      const desc = document.createElement('button');
      const clearColumn = document.createElement('button');
      const optionList = document.createElement('div');
      const footer = document.createElement('div');
      const close = document.createElement('button');

      menu.className = 'table-menu';
      menu.hidden = true;
      menu.addEventListener('click', (event) => event.stopPropagation());
      actionList.className = 'table-menu-actions';
      asc.type = 'button';
      asc.textContent = 'Ascending';
      desc.type = 'button';
      desc.textContent = 'Descending';
      clearColumn.type = 'button';
      clearColumn.textContent = `Clear Filters from ${label}`;
      [asc, desc, clearColumn].forEach((button) => {
        button.className = 'table-menu-action';
      });

      asc.disabled = skipSort;
      desc.disabled = skipSort;
      clearColumn.disabled = skipFilter;
      actionList.append(asc, desc, clearColumn);
      optionList.className = 'table-menu-options';

      if (skipFilter) {
        const note = document.createElement('p');
        note.className = 'muted table-menu-note';
        note.textContent = 'Filtering is not available for this column.';
        optionList.appendChild(note);
      } else {
        values.forEach((value) => {
          const optionLabel = document.createElement('label');
          const checkbox = document.createElement('input');
          const text = document.createElement('span');

          optionLabel.className = 'table-menu-check';
          checkbox.type = 'checkbox';
          checkbox.value = value.key;
          text.textContent = value.label;
          optionLabel.append(checkbox, text);
          optionList.appendChild(optionLabel);

          checkbox.addEventListener('change', () => {
            const checked = Array.from(optionList.querySelectorAll('input[type="checkbox"]:checked')).map((input) => input.value);

            if (checked.length === values.length) {
              delete filterState[String(index)];
            } else {
              filterState[String(index)] = checked;
            }

            applyTableState();
          });
        });
      }

      close.className = 'btn secondary small';
      close.type = 'button';
      close.textContent = 'Close';
      footer.className = 'table-menu-footer';
      footer.appendChild(close);
      menu.append(actionList, optionList, footer);

      asc.addEventListener('click', () => {
        sortState = { column: index, direction: 'asc' };
        closeMenus();
        applyTableState();
      });

      desc.addEventListener('click', () => {
        sortState = { column: index, direction: 'desc' };
        closeMenus();
        applyTableState();
      });

      clearColumn.addEventListener('click', () => {
        delete filterState[String(index)];
        closeMenus();
        applyTableState();
      });

      close.addEventListener('click', () => closeMenus());

      return menu;
    }

    function closeMenus(except = null) {
      document.querySelectorAll('.table-menu').forEach((menu) => {
        if (menu !== except) {
          menu.hidden = true;
          menu.closest('.table-head-wrap')?.querySelector('.table-filter-button')?.setAttribute('aria-expanded', 'false');
        }
      });
    }

    function cleanStoredState() {
      menus.forEach(({ index, values, skipFilter }) => {
        const key = String(index);

        if (skipFilter || !Array.isArray(filterState[key])) return;

        const validValues = new Set(values.map((value) => value.key));
        filterState[key] = filterState[key].filter((value) => validValues.has(value));

        if (filterState[key].length === values.length) {
          delete filterState[key];
        }
      });

      if (sortState.column !== null && (!headers[sortState.column] || headers[sortState.column]?.dataset.noSort === 'true')) {
        sortState = { column: null, direction: 'none' };
      }
    }

    function sortedRows() {
      const rows = originalRows.slice();

      if (sortState.column === null || sortState.direction === 'none') {
        return rows;
      }

      return rows.sort((a, b) => {
        const aText = cellText(a, sortState.column);
        const bText = cellText(b, sortState.column);
        const aNumber = Number(aText.replace(/[^\d.-]/g, ''));
        const bNumber = Number(bText.replace(/[^\d.-]/g, ''));
        const bothNumeric = aText !== '' && bText !== '' && Number.isFinite(aNumber) && Number.isFinite(bNumber);
        const result = bothNumeric ? aNumber - bNumber : aText.localeCompare(bText, undefined, { numeric: true, sensitivity: 'base' });

        return sortState.direction === 'asc' ? result : -result;
      });
    }

    function rowMatchesFilters(row) {
      return Object.entries(filterState).every(([column, selected]) => {
        if (!Array.isArray(selected)) return true;
        return selected.includes(valueKey(cellText(row, Number.parseInt(column, 10))));
      });
    }

    function searchableText(row) {
      return Array.from(row.cells)
        .filter((cell, index) => headers[index]?.dataset.noFilter !== 'true' && headers[index]?.dataset.noSort !== 'true')
        .map((cell) => cell.textContent || '')
        .join(' ');
    }

    function syncMenuState() {
      menus.forEach(({ index, trigger, menu, values, skipFilter }) => {
        const selected = filterState[String(index)];
        const isFiltered = Array.isArray(selected) && selected.length < values.length;
        const isSorted = sortState.column === index && sortState.direction !== 'none';
        const state = trigger.querySelector('.table-filter-state');
        const checkboxes = Array.from(menu.querySelectorAll('input[type="checkbox"]'));

        checkboxes.forEach((checkbox) => {
          checkbox.checked = skipFilter || !isFiltered || selected.includes(checkbox.value);
        });

        trigger.dataset.filtered = isFiltered ? 'true' : 'false';
        trigger.dataset.sortDirection = isSorted ? sortState.direction : 'none';

        if (state) {
          state.textContent = isFiltered ? '*' : '';
        }
      });
    }

    function persistState() {
      const nextState = {
        search: search.value,
        filters: filterState,
        sort: sortState,
      };

      try {
        window.localStorage.setItem(storageKey, JSON.stringify(nextState));
      } catch (error) {
        return;
      }
    }

    function applyTableState(options = {}) {
      const shouldPersist = options.persist !== false;
      const searchValue = normalizeText(search.value);
      let visible = 0;

      sortedRows().forEach((row) => tbody.appendChild(row));

      originalRows.forEach((row) => {
        const matchesSearch = searchValue === '' || normalizeText(searchableText(row)).includes(searchValue);
        const isVisible = matchesSearch && rowMatchesFilters(row);

        row.hidden = !isVisible;

        if (isVisible) visible += 1;
      });

      count.textContent = `${visible} of ${originalRows.length} shown`;
      empty.hidden = visible > 0;
      syncMenuState();

      if (shouldPersist) {
        persistState();
      }
    }
  });

  if (reduceMotion) return;

  document.querySelectorAll('[data-animate]').forEach((element, index) => {
    element.animate([
      { opacity: 0, transform: 'translateY(10px)' },
      { opacity: 1, transform: 'translateY(0)' }
    ], {
      duration: 240,
      delay: index * 45,
      fill: 'both',
      easing: 'ease-out'
    });
  });
});
