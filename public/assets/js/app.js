document.addEventListener('DOMContentLoaded', () => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

  document.querySelectorAll('[data-enhanced-table]').forEach((table) => {
    const tbody = table.tBodies[0];

    if (!tbody || table.dataset.tableReady === '1') return;

    table.dataset.tableReady = '1';
    const rows = Array.from(tbody.rows);
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
    const filters = [];

    controls.className = 'table-tools';
    searchCaption.textContent = 'Search';
    search.type = 'search';
    search.placeholder = 'Search this table';
    search.dataset.tableSearch = '1';
    searchLabel.append(searchCaption, search);
    controls.appendChild(searchLabel);

    rows.forEach((row) => {
      Array.from(row.cells).forEach((cell, index) => {
        if (!cell.hasAttribute('data-label')) {
          cell.setAttribute('data-label', labels[index] || '');
        }
      });
    });

    headers.forEach((header, index) => {
      const label = labels[index] || 'Column';
      const skipFilter = header.dataset.noFilter === 'true';
      const skipSort = header.dataset.noSort === 'true';

      if (!skipSort) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'table-sort-button';
        button.textContent = label;
        button.dataset.sortDirection = 'none';
        header.textContent = '';
        header.appendChild(button);

        button.addEventListener('click', () => {
          const nextDirection = button.dataset.sortDirection === 'asc' ? 'desc' : 'asc';

          headers.forEach((otherHeader) => {
            const otherButton = otherHeader.querySelector('.table-sort-button');

            if (otherButton && otherButton !== button) {
              otherButton.dataset.sortDirection = 'none';
            }
          });

          button.dataset.sortDirection = nextDirection;
          const sorted = Array.from(tbody.rows).sort((a, b) => {
            const aText = (a.cells[index]?.textContent || '').replace(/\s+/g, ' ').trim();
            const bText = (b.cells[index]?.textContent || '').replace(/\s+/g, ' ').trim();
            const aNumber = Number(aText.replace(/[^\d.-]/g, ''));
            const bNumber = Number(bText.replace(/[^\d.-]/g, ''));
            const bothNumeric = aText !== '' && bText !== '' && Number.isFinite(aNumber) && Number.isFinite(bNumber);
            const result = bothNumeric ? aNumber - bNumber : aText.localeCompare(bText, undefined, { numeric: true, sensitivity: 'base' });

            return nextDirection === 'asc' ? result : -result;
          });

          sorted.forEach((row) => tbody.appendChild(row));
          applyFilters();
        });
      }

      if (skipFilter) return;

      const values = Array.from(new Set(rows.map((row) => row.cells[index]?.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean))).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

      if (values.length <= 1 || values.length > 40) return;

      const filterLabel = document.createElement('label');
      const filterCaption = document.createElement('span');
      const filter = document.createElement('select');
      const emptyOption = document.createElement('option');

      filterCaption.textContent = label;
      emptyOption.value = '';
      emptyOption.textContent = `All ${label.toLowerCase()}`;
      filter.appendChild(emptyOption);

      values.forEach((value) => {
        const option = document.createElement('option');
        option.value = normalizeText(value);
        option.textContent = value;
        filter.appendChild(option);
      });

      filter.dataset.tableFilterColumn = String(index);
      filterLabel.append(filterCaption, filter);
      controls.appendChild(filterLabel);
      filters.push(filter);
    });

    clear.className = 'btn secondary small';
    clear.type = 'button';
    clear.textContent = 'Clear';
    actions.className = 'table-tool-actions';
    actions.appendChild(clear);
    count.className = 'table-count muted';
    count.setAttribute('aria-live', 'polite');
    controls.append(actions, count);
    empty.className = 'empty-state table-empty';
    empty.hidden = true;
    empty.textContent = table.dataset.tableEmpty || 'No rows match these filters.';

    if (wrap && wrap.parentNode) {
      wrap.parentNode.insertBefore(controls, wrap);
      wrap.parentNode.insertBefore(empty, wrap.nextSibling);
    }

    function applyFilters() {
      const searchValue = normalizeText(search.value);
      let visible = 0;

      Array.from(tbody.rows).forEach((row) => {
        const searchableText = Array.from(row.cells)
          .filter((cell, index) => headers[index]?.dataset.noFilter !== 'true' && headers[index]?.dataset.noSort !== 'true')
          .map((cell) => cell.textContent || '')
          .join(' ');
        const matchesSearch = searchValue === '' || normalizeText(searchableText).includes(searchValue);
        const matchesFilters = filters.every((filter) => {
          const value = filter.value;
          const column = Number.parseInt(filter.dataset.tableFilterColumn || '0', 10);

          return value === '' || normalizeText(row.cells[column]?.textContent || '') === value;
        });
        const isVisible = matchesSearch && matchesFilters;

        row.hidden = !isVisible;

        if (isVisible) visible += 1;
      });

      count.textContent = `${visible} of ${rows.length} shown`;
      empty.hidden = visible > 0;
    }

    controls.addEventListener('submit', (event) => {
      event.preventDefault();
      applyFilters();
    });
    search.addEventListener('input', applyFilters);
    filters.forEach((filter) => filter.addEventListener('change', applyFilters));
    clear.addEventListener('click', () => {
      search.value = '';
      filters.forEach((filter) => {
        filter.value = '';
      });
      applyFilters();
    });
    applyFilters();
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
