// Topbar global search: a debounced fetch-as-you-type dropdown layered on top of a
// plain GET <form> (see layouts/app.blade.php and layouts/admin.blade.php). If JS
// never runs, or the fetch fails, the form still submits to the results page — the
// dropdown is purely an enhancement, never the only way to search.
(function () {
  function debounce(fn, wait) {
    var timer;
    return function () {
      var args = arguments;
      var ctx = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('.topbar-search');
    for (var i = 0; i < forms.length; i++) {
      initSearch(forms[i]);
    }
  });

  function initSearch(form) {
    var input = form.querySelector('input[name="q"]');
    var dropdown = form.querySelector('.search-dropdown');
    var endpoint = form.dataset.searchEndpoint;
    if (!input || !dropdown || !endpoint) return;

    function hide() {
      dropdown.hidden = true;
    }

    function escapeHtml(value) {
      var div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    }

    function render(data) {
      var groups = [];
      var all = data.groups || [];
      for (var i = 0; i < all.length; i++) {
        if (all[i].items && all[i].items.length) groups.push(all[i]);
      }

      if (!groups.length) {
        dropdown.innerHTML = '<div class="search-dropdown-empty">' + escapeHtml(dropdown.dataset.noResults || '') + '</div>';
        dropdown.hidden = false;
        return;
      }

      var html = '';
      for (var g = 0; g < groups.length; g++) {
        var group = groups[g];
        html += '<div class="search-dropdown-group">';
        html += '<div class="search-dropdown-label">' + escapeHtml(group.label) + '</div>';
        for (var j = 0; j < group.items.length; j++) {
          var item = group.items[j];
          html += '<a href="' + escapeHtml(item.url) + '">' + escapeHtml(item.label) + '</a>';
        }
        html += '</div>';
      }
      dropdown.innerHTML = html;
      dropdown.hidden = false;
    }

    var fetchResults = debounce(function (q) {
      fetch(endpoint + '?format=json&q=' + encodeURIComponent(q), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (data) {
          // The person may have kept typing while this request was in flight —
          // only render a response that still matches what's in the box now.
          if (!data || input.value.trim() !== q) return;
          render(data);
        })
        .catch(function () {
          // A failed live lookup should never block the plain GET fallback.
        });
    }, 300);

    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (q.length < 2) {
        hide();
        return;
      }
      fetchResults(q);
    });

    input.addEventListener('focus', function () {
      if (input.value.trim().length >= 2 && dropdown.innerHTML) {
        dropdown.hidden = false;
      }
    });

    input.addEventListener('blur', function () {
      // Delay so a click on a dropdown link has a chance to register first.
      setTimeout(hide, 150);
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') hide();
    });
  }
})();
