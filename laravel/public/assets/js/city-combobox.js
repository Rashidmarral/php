// Progressive enhancement for a long/searchable <select> into a type-to-filter
// combobox: a text input plus a filtered listbox layered in front of the real
// <select>. If JS never runs, the plain <select> stays visible and still
// submits a real value — this only ever sits in front of it, exactly like
// global-search.js layers its dropdown on top of a plain GET <form> that
// keeps working on its own.
//
// Two opt-in classes share this exact mechanism:
//  - select.city-select — the original Saudi-city picker (resources/views/
//    app/settings/index.blade.php, resources/views/app/clients/form.blade.php),
//    whose options may carry a data-ar attribute matched alongside the
//    visible label so typing in either language narrows the list.
//  - select.combo-select — the same generic combobox for any other long
//    list (e.g. a client or project picker), with no language-specific
//    matching assumed. Both render the same .city-combo/.city-combo-list/
//    .city-combo-option markup (see public/assets/css/app.css) — a second,
//    differently-named class was deliberately not introduced.
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var selects = document.querySelectorAll('select.city-select, select.combo-select');
    for (var i = 0; i < selects.length; i++) {
      initCombo(selects[i]);
    }
  });

  function initCombo(select) {
    // A disabled <select> is a read-only display field (e.g. a non-owner
    // viewing company settings) — leave it exactly as rendered.
    if (select.disabled) return;

    var options = [];
    for (var i = 0; i < select.options.length; i++) {
      var opt = select.options[i];
      if (opt.value === '') continue;
      options.push({ value: opt.value, label: opt.textContent, ar: (opt.getAttribute('data-ar') || '').toLowerCase() });
    }

    var wrapper = document.createElement('div');
    wrapper.className = 'city-combo';

    var input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    input.placeholder = select.getAttribute('data-placeholder') || '';

    var listbox = document.createElement('ul');
    listbox.className = 'city-combo-list search-dropdown';
    listbox.hidden = true;
    listbox.setAttribute('role', 'listbox');

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(input);
    wrapper.appendChild(listbox);
    wrapper.appendChild(select);
    select.classList.add('city-select-native');

    var activeIndex = -1;
    var filtered = options;

    function selectedOption() {
      for (var i = 0; i < options.length; i++) {
        if (options[i].value === select.value) return options[i];
      }
      return null;
    }

    function setInputFromSelection() {
      var sel = selectedOption();
      input.value = sel ? sel.label : '';
    }

    function render() {
      listbox.innerHTML = '';
      if (!filtered.length) {
        var empty = document.createElement('li');
        empty.className = 'city-combo-empty';
        empty.textContent = select.getAttribute('data-no-results') || '';
        listbox.appendChild(empty);
      } else {
        for (var i = 0; i < filtered.length; i++) {
          var li = document.createElement('li');
          li.className = 'city-combo-option' + (i === activeIndex ? ' is-active' : '');
          li.textContent = filtered[i].label;
          li.setAttribute('role', 'option');
          li.setAttribute('data-index', String(i));
          listbox.appendChild(li);
        }
      }
      listbox.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function close() {
      listbox.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }

    function filter(query) {
      var q = (query || '').trim().toLowerCase();
      filtered = !q ? options : options.filter(function (o) {
        return o.label.toLowerCase().indexOf(q) !== -1 || o.ar.indexOf(q) !== -1;
      });
      activeIndex = filtered.length ? 0 : -1;
      render();
    }

    function choose(option) {
      select.value = option.value;
      input.value = option.label;
      close();
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    setInputFromSelection();

    input.addEventListener('focus', function () {
      filter(''); // Show the full list on focus; typing narrows it further.
      input.select();
    });

    input.addEventListener('input', function () {
      filter(input.value);
    });

    input.addEventListener('keydown', function (e) {
      if (listbox.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
        e.preventDefault();
        filter(input.value);
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (filtered.length) {
          activeIndex = (activeIndex + 1) % filtered.length;
          render();
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (filtered.length) {
          activeIndex = (activeIndex - 1 + filtered.length) % filtered.length;
          render();
        }
      } else if (e.key === 'Enter') {
        if (!listbox.hidden && activeIndex >= 0 && filtered[activeIndex]) {
          e.preventDefault();
          choose(filtered[activeIndex]);
        }
      } else if (e.key === 'Escape') {
        if (!listbox.hidden) {
          close();
          setInputFromSelection();
        }
      }
    });

    listbox.addEventListener('mousedown', function (e) {
      var li = e.target.closest('.city-combo-option');
      if (!li) return;
      e.preventDefault();
      var idx = parseInt(li.getAttribute('data-index'), 10);
      if (filtered[idx]) choose(filtered[idx]);
    });

    document.addEventListener('click', function (e) {
      if (wrapper.contains(e.target)) return;
      if (!listbox.hidden) close();
      // Revert the visible text to the real selection whenever it doesn't
      // exactly match an option, so stray typed text is never mistaken for
      // a choice — the <select> stays the single source of truth submitted.
      setInputFromSelection();
    });
  }
})();
