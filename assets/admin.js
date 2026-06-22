(function () {
  function normalizeText(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase();
  }

  document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[action*="admin-post.php"]');
    const overlay = document.querySelector('.idg-processing-overlay');
    const buttons = document.querySelectorAll('.idg-actions button[type="submit"], .idg-button-reset, .idg-button-reset-partial, .idg-radar-import-form button[type="submit"]');
    let clickedButton = null;

    buttons.forEach(function (button) {
      button.addEventListener('click', function (event) {
        const message = button.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
          event.preventDefault();
          return;
        }
        clickedButton = button;
        button.classList.add('idg-clicked');
      });
    });

    forms.forEach(function (form) {
      form.addEventListener('submit', function () {
        if (!clickedButton || !form.contains(clickedButton)) {
          return;
        }
        const step = clickedButton.value || '';
        if (['generate', 'editorial', 'seo', 'draft'].indexOf(step) === -1) {
          return;
        }
        document.body.classList.add('idg-is-processing');
        if (overlay) {
          overlay.setAttribute('aria-hidden', 'false');
        }
        clickedButton.dataset.originalText = clickedButton.textContent;
        clickedButton.textContent = step === 'draft' ? 'Creando borrador...' : (step === 'generate' ? 'Generando artículo...' : 'Procesando...');
      });
    });

    const priorityWrap = document.querySelector('.idg-priority-readings');
    const priorityField = document.getElementById('priority_readings');
    const categorySelect = document.getElementById('category_id');
    let priorityTouched = false;
    let lastAutoPrioritySuggestion = '';
    let categoryPresets = {};
    let tagPresets = {};

    if (priorityWrap && priorityField) {
      try {
        categoryPresets = JSON.parse(priorityWrap.getAttribute('data-category-presets') || '{}');
        tagPresets = JSON.parse(priorityWrap.getAttribute('data-tag-presets') || '{}');
      } catch (error) {
        categoryPresets = {};
        tagPresets = {};
      }

      priorityField.addEventListener('input', function () {
        const current = String(priorityField.value || '').trim();
        if (current !== lastAutoPrioritySuggestion) {
          priorityTouched = true;
        }
      });
    }

    function buildPrioritySuggestion(selectedTagIds) {
      const parts = [];
      const seen = {};
      const catId = categorySelect ? String(categorySelect.value || '') : '';
      if (catId && categoryPresets[catId]) {
        parts.push(categoryPresets[catId]);
      }
      (selectedTagIds || []).forEach(function (id) {
        const key = String(id);
        if (tagPresets[key]) {
          parts.push(tagPresets[key]);
        }
      });
      const tokens = [];
      parts.join(';').split(';').forEach(function (token) {
        token = String(token || '').trim().replace(/[.]+$/, '');
        if (!token) return;
        const normalized = normalizeText(token);
        if (seen[normalized]) return;
        seen[normalized] = true;
        tokens.push(token);
      });
      return tokens.length ? tokens.join('; ') + '.' : '';
    }

    function canAutoReplacePriority() {
      if (!priorityField) return false;
      const current = String(priorityField.value || '').trim();
      return !current || !priorityTouched || (lastAutoPrioritySuggestion && current === lastAutoPrioritySuggestion);
    }

    function applyPrioritySuggestion(force, selectedTagIds) {
      if (!priorityField) return;
      const suggestion = buildPrioritySuggestion(selectedTagIds || []);
      if (!suggestion) return;
      if (force || canAutoReplacePriority()) {
        priorityField.value = suggestion;
        lastAutoPrioritySuggestion = suggestion;
        priorityTouched = false;
      }
    }

    function selectedTagsFromPicker() {
      const pickerEl = document.querySelector('.idg-tag-picker');
      return pickerEl && pickerEl.__idgSelectedTags ? pickerEl.__idgSelectedTags() : [];
    }

    document.querySelectorAll('.idg-priority-update, .idg-priority-restore').forEach(function (button) {
      button.addEventListener('click', function () {
        applyPrioritySuggestion(true, selectedTagsFromPicker());
      });
    });

    if (categorySelect) {
      categorySelect.addEventListener('change', function () {
        applyPrioritySuggestion(false, selectedTagsFromPicker());
      });
    }

    const pieceTypeSelect = document.getElementById('piece_type');
    const pieceTypeDisplay = document.getElementById('piece_type_display');
    const pieceTypeWrap = document.querySelector('.idg-category-piece-row');
    const sponsoredPanel = document.querySelector('[data-sponsored-panel]');
    let pieceTypeMap = {};
    if (pieceTypeWrap) {
      try { pieceTypeMap = JSON.parse(pieceTypeWrap.getAttribute('data-piece-type-map') || '{}'); } catch (error) { pieceTypeMap = {}; }
    }
    function inferredPieceType() {
      const key = categorySelect ? String(categorySelect.value || '') : '';
      return pieceTypeMap[key] || 'Actualidad';
    }
    function updatePieceTypeFromCategory() {
      if (!pieceTypeSelect) return;
      const value = inferredPieceType();
      pieceTypeSelect.value = value;
      if (pieceTypeDisplay) pieceTypeDisplay.value = value;
      updateSponsoredPanel();
    }
    function updateSponsoredPanel() {
      if (!pieceTypeSelect || !sponsoredPanel) return;
      const value = normalizeText(pieceTypeSelect.value || '');
      const isSponsored = value.indexOf('patrocinado') !== -1 || value.indexOf('colaboraci') !== -1;
      sponsoredPanel.style.display = isSponsored ? '' : 'none';
      sponsoredPanel.setAttribute('aria-hidden', isSponsored ? 'false' : 'true');
    }
    if (pieceTypeSelect) {
      if (categorySelect) categorySelect.addEventListener('change', updatePieceTypeFromCategory);
      updatePieceTypeFromCategory();
    }

    document.querySelectorAll('[data-idg-counter]').forEach(function (field) {
      const targetSelector = field.getAttribute('data-idg-counter');
      const target = targetSelector ? document.querySelector(targetSelector) : null;
      if (!target) {
        return;
      }
      function updateCount() {
        target.textContent = String(field.value || '').length.toLocaleString();
      }
      field.addEventListener('input', updateCount);
      updateCount();
    });

    const picker = document.querySelector('.idg-tag-picker');
    if (!picker) {
      applyPrioritySuggestion(false, []);
      return;
    }

    const lockedPicker = picker.getAttribute('data-locked') === '1';
    const input = document.getElementById('idg_tag_filter');
    const selectedBox = document.getElementById('idg_tag_selected');
    const suggestionsBox = document.getElementById('idg_tag_suggestions');
    const inputsBox = document.getElementById('idg_tag_inputs');
    if (!input || !selectedBox || !suggestionsBox || !inputsBox) {
      applyPrioritySuggestion(false, []);
      return;
    }

    let tags = [];
    let selected = [];
    try {
      tags = JSON.parse(picker.getAttribute('data-tags') || '[]');
      selected = JSON.parse(picker.getAttribute('data-selected') || '[]').map(Number);
    } catch (error) {
      tags = [];
      selected = [];
    }

    picker.__idgSelectedTags = function () { return selected.slice(); };

    function isSelected(id) {
      return selected.indexOf(Number(id)) !== -1;
    }

    function addTag(id) {
      if (lockedPicker) return;
      id = Number(id);
      if (!id || isSelected(id)) {
        return;
      }
      selected.push(id);
      input.value = '';
      renderSelected();
      renderSuggestions();
      applyPrioritySuggestion(false, selected);
      input.focus();
    }

    function removeTag(id) {
      if (lockedPicker) return;
      id = Number(id);
      selected = selected.filter(function (value) { return value !== id; });
      renderSelected();
      renderSuggestions();
      applyPrioritySuggestion(false, selected);
      input.focus();
    }

    function renderSelected() {
      selectedBox.innerHTML = '';
      inputsBox.innerHTML = '';
      selected.forEach(function (id) {
        const tag = tags.find(function (item) { return Number(item.id) === Number(id); });
        if (!tag) {
          return;
        }
        const chip = document.createElement('span');
        chip.className = 'idg-tag-chip';
        chip.textContent = tag.name;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.setAttribute('aria-label', 'Quitar ' + tag.name);
        remove.textContent = '×';
        remove.addEventListener('click', function () { removeTag(id); });
        chip.appendChild(remove);
        selectedBox.appendChild(chip);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'tag_ids[]';
        hidden.value = String(id);
        inputsBox.appendChild(hidden);
      });
    }

    function tagBelongsToSelectedCategory(tag) {
      const catId = categorySelect ? Number(categorySelect.value || 0) : 0;
      if (!catId) return false;
      const categoryIds = Array.isArray(tag.categoryIds) ? tag.categoryIds.map(Number) : [];
      return categoryIds.indexOf(catId) !== -1;
    }

    function renderSuggestions() {
      if (lockedPicker) { suggestionsBox.innerHTML = ''; suggestionsBox.classList.remove('is-open'); return; }
      const query = normalizeText(input.value.trim());
      const catId = categorySelect ? Number(categorySelect.value || 0) : 0;
      suggestionsBox.innerHTML = '';

      let matches = tags.filter(function (tag) {
        if (isSelected(tag.id)) return false;
        if (query) return normalizeText(tag.name).indexOf(query) !== -1;
        return catId ? tagBelongsToSelectedCategory(tag) : false;
      });

      matches = matches.sort(function (a, b) {
        const aInCat = tagBelongsToSelectedCategory(a) ? 0 : 1;
        const bInCat = tagBelongsToSelectedCategory(b) ? 0 : 1;
        if (aInCat !== bInCat) return aInCat - bInCat;
        return String(a.name || '').localeCompare(String(b.name || ''));
      }).slice(0, 30);

      if (!query && catId && matches.length) {
        const label = document.createElement('div');
        label.className = 'idg-tag-suggestion-label';
        label.textContent = 'Etiquetas sugeridas para esta categoría';
        suggestionsBox.appendChild(label);
      }

      matches.forEach(function (tag, index) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'idg-tag-suggestion' + (index === 0 ? ' is-active' : '');
        item.textContent = tag.name;
        item.addEventListener('click', function () { addTag(tag.id); });
        suggestionsBox.appendChild(item);
      });

      if (!catId && !query) {
        const label = document.createElement('div');
        label.className = 'idg-tag-suggestion-label';
        label.textContent = 'Elige una categoría para ver etiquetas sugeridas.';
        suggestionsBox.appendChild(label);
      }

      suggestionsBox.classList.toggle('is-open', matches.length > 0 || (!catId && !query));
    }

    input.addEventListener('input', renderSuggestions);
    input.addEventListener('focus', renderSuggestions);
    if (categorySelect) {
      categorySelect.addEventListener('change', function () {
        renderSuggestions();
        applyPrioritySuggestion(false, selected);
      });
    }
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        const first = suggestionsBox.querySelector('.idg-tag-suggestion');
        if (first) {
          first.click();
        }
      }
      if (event.key === 'Escape') {
        suggestionsBox.classList.remove('is-open');
      }
    });

    document.addEventListener('click', function (event) {
      if (!picker.contains(event.target)) {
        suggestionsBox.classList.remove('is-open');
      }
    });

    renderSelected();
    applyPrioritySuggestion(false, selected);
  });
})();
