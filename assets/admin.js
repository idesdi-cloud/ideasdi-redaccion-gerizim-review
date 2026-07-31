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
    const buttons = document.querySelectorAll('.idg-actions button[type="submit"], .idg-button-reset, .idg-button-reset-partial, .idg-radar-import-form button[type="submit"], .idg-recurring-apply-form button[type="submit"]');
    let clickedButton = null;
    let tempMaterialFileReady = true;

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
      form.addEventListener('submit', function (event) {
        if (!clickedButton || !form.contains(clickedButton)) {
          return;
        }
        const step = clickedButton.value || '';
        if (['generate', 'editorial', 'seo', 'draft', 'draft_force', 'recurring_event_content', 'recurring_event_content_force'].indexOf(step) === -1) {
          return;
        }
        if (step === 'generate' && !tempMaterialFileReady) {
          event.preventDefault();
          const fileInput = document.getElementById('temp_material_file');
          const liveStatus = document.getElementById('idg_temp_material_file_live_status');
          if (liveStatus && !liveStatus.textContent) {
            liveStatus.textContent = 'Reemplaza o descarta el archivo temporal antes de generar.';
            liveStatus.classList.add('is-error');
          }
          if (fileInput) fileInput.focus();
          return;
        }
        document.body.classList.add('idg-is-processing');
        if (overlay) {
          overlay.setAttribute('aria-hidden', 'false');
        }
        clickedButton.dataset.originalText = clickedButton.textContent;
        clickedButton.textContent = step.indexOf('recurring_event_content') === 0 ? 'Aplicando redacción...' : (step.indexOf('draft') === 0 ? 'Creando borrador...' : (step === 'generate' ? 'Generando artículo...' : 'Procesando...'));
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
      const catId = categorySelect ? String(categorySelect.value || '') : '';
      const categoryPreset = catId && categoryPresets[catId] ? categoryPresets[catId] : null;
      let winner = null;
      const modifiers = [];

      (selectedTagIds || []).forEach(function (id) {
        const preset = tagPresets[String(id)];
        if (!preset || typeof preset !== 'object') return;
        const role = String(preset.role || '');
        if (role === 'modifier' || role === 'context') {
          if (preset.tag) modifiers.push(String(preset.tag));
          return;
        }
        const priority = Number(preset.priority || 0);
        if (!winner || priority > Number(winner.priority || 0)) {
          winner = preset;
        }
      });

      let suggestion = '';
      if (winner && winner.summary) {
        suggestion = String(winner.summary).trim();
      } else if (categoryPreset && typeof categoryPreset === 'object' && categoryPreset.summary) {
        suggestion = String(categoryPreset.summary).trim();
      } else if (typeof categoryPreset === 'string') {
        suggestion = String(categoryPreset).trim();
      }
      if (suggestion && modifiers.length) {
        suggestion += ' Considerar ' + modifiers.join(', ') + ' únicamente cuando la documentación lo respalde.';
      }
      return suggestion;
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
      const fixed = pieceTypeWrap ? String(pieceTypeWrap.getAttribute('data-fixed-piece-type') || '') : '';
      if (fixed) return fixed;
      const key = categorySelect ? String(categorySelect.value || '') : '';
      return pieceTypeMap[key] || (pieceTypeSelect ? String(pieceTypeSelect.value || '') : '') || 'Actualidad';
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

    const recurringStartDate = document.querySelector('[data-recurring-start-date]');
    const recurringEndDate = document.querySelector('[data-recurring-end-date]');
    if (recurringStartDate && recurringEndDate) {
      function syncRecurringEndMinimum() {
        const startValue = String(recurringStartDate.value || '').trim();
        if (startValue) {
          recurringEndDate.setAttribute('min', startValue);
        } else {
          recurringEndDate.removeAttribute('min');
        }
        if (startValue && recurringEndDate.value && recurringEndDate.value < startValue) {
          recurringEndDate.setCustomValidity('La fecha de fin debe ser igual o posterior a la fecha de inicio.');
        } else {
          recurringEndDate.setCustomValidity('');
        }
      }
      recurringStartDate.addEventListener('change', syncRecurringEndMinimum);
      recurringStartDate.addEventListener('input', syncRecurringEndMinimum);
      recurringEndDate.addEventListener('change', syncRecurringEndMinimum);
      recurringEndDate.addEventListener('input', syncRecurringEndMinimum);
      syncRecurringEndMinimum();
    }

    const recurringPreparation = document.querySelector('.idg-recurring-preparation');
    const recurringAnalyzeButton = recurringPreparation ? recurringPreparation.querySelector('.idg-recurring-analyze-button') : null;
    const recurringOverwriteWarning = recurringPreparation ? recurringPreparation.querySelector('[data-idg-overwrite-warning]') : null;
    const recurringModeInputs = recurringPreparation ? recurringPreparation.querySelectorAll('input[name="update_mode"]') : [];
    if (recurringPreparation && recurringOverwriteWarning && recurringModeInputs.length) {
      const syncRecurringModeWarning = function () {
        const selected = recurringPreparation.querySelector('input[name="update_mode"]:checked');
        recurringOverwriteWarning.hidden = !(selected && selected.value === 'update_existing');
      };
      recurringModeInputs.forEach(function (input) {
        input.addEventListener('change', syncRecurringModeWarning);
      });
      syncRecurringModeWarning();
    }
    if (recurringPreparation && recurringAnalyzeButton) {
      const resetAnalyzeState = function () {
        if (!recurringAnalyzeButton.classList.contains('idg-step-done')) return;
        recurringAnalyzeButton.classList.remove('idg-step-done');
        recurringAnalyzeButton.textContent = recurringAnalyzeButton.getAttribute('data-default-label') || 'Analizar cambios';
      };
      recurringPreparation.querySelectorAll('input, select, textarea').forEach(function (field) {
        if (field.type === 'hidden') return;
        field.addEventListener('input', resetAnalyzeState);
        field.addEventListener('change', resetAnalyzeState);
      });
    }

    const proposedTitle = document.querySelector('.idg-recurring-proposed-title');
    const proposedSlug = document.querySelector('.idg-recurring-proposed-slug');
    if (proposedTitle && proposedSlug) {
      let slugTouched = false;
      proposedSlug.addEventListener('input', function () { slugTouched = true; proposedSlug.dataset.manualSlug = '1'; });
      proposedTitle.addEventListener('input', function () {
        if (slugTouched) return;
        proposedSlug.value = String(proposedTitle.value || '')
          .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .toLowerCase().trim()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '');
      });
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

    const tempFileInput = document.getElementById('temp_material_file');
    const tempFileStatus = document.getElementById('idg_temp_material_file_live_status');
    const generateButton = document.getElementById('idg_generate_button');
    const serverFileError = document.querySelector('.idg-file-server-status.notice-error');
    const clearFileError = document.querySelector('input[name="temp_material_clear_file_error"]');
    const removeCurrentFile = document.querySelector('input[name="temp_material_remove_file"]');
    const allowedTempExtensions = ['txt', 'md', 'markdown', 'docx', 'pdf', 'html', 'htm', 'csv'];

    function setTempFileState(ok, message, kind) {
      tempMaterialFileReady = !!ok;
      if (generateButton) generateButton.disabled = !tempMaterialFileReady;
      if (tempFileStatus) {
        tempFileStatus.textContent = message || '';
        tempFileStatus.classList.remove('is-error', 'is-ready');
        if (kind) tempFileStatus.classList.add(kind);
      }
    }

    function validateTempFileSelection() {
      if (!tempFileInput || !tempFileInput.files || !tempFileInput.files.length) {
        const cleared = clearFileError && clearFileError.checked;
        setTempFileState(!serverFileError || cleared, cleared ? 'El archivo rechazado será descartado.' : '', cleared ? 'is-ready' : '');
        return;
      }
      const file = tempFileInput.files[0];
      const maxBytes = Number(tempFileInput.getAttribute('data-max-bytes') || 6291456);
      const nameParts = String(file.name || '').toLowerCase().split('.');
      const extension = nameParts.length > 1 ? nameParts.pop() : '';
      if (file.size > maxBytes) {
        setTempFileState(false, 'El archivo supera 6 MB. Selecciona otra versión antes de generar.', 'is-error');
        return;
      }
      if (allowedTempExtensions.indexOf(extension) === -1) {
        setTempFileState(false, 'Formato no soportado. Usa TXT, MD, DOCX, PDF, HTML o CSV.', 'is-error');
        return;
      }
      setTempFileState(true, 'Archivo dentro del límite. Gerizim comprobará que tenga texto legible antes de generar.', 'is-ready');
    }

    if (tempFileInput) {
      tempFileInput.addEventListener('change', validateTempFileSelection);
    }
    if (clearFileError) {
      clearFileError.addEventListener('change', validateTempFileSelection);
    }
    if (removeCurrentFile) {
      removeCurrentFile.addEventListener('change', function () {
        setTempFileState(true, removeCurrentFile.checked ? 'El archivo actual será retirado al guardar o generar.' : '', removeCurrentFile.checked ? 'is-ready' : '');
      });
    }
    validateTempFileSelection();

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
