document.addEventListener('click', (e) => {
  const btn = e.target.closest('.password-toggle');
  if (!btn) return;
  const field = btn.closest('.password-field');
  const input = field ? field.querySelector('input') : null;
  if (!input) return;
  const visible = input.type === 'text';
  input.type = visible ? 'password' : 'text';
  btn.dataset.visible = visible ? 'false' : 'true';
  btn.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
});
