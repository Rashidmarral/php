document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('[data-password-strength-input]');
  const fill = document.querySelector('[data-password-strength-fill]');
  const label = document.querySelector('[data-password-strength-label]');
  if (!input || !fill || !label) return;

  let labels;
  try {
    labels = JSON.parse(label.dataset.labels || '{}');
  } catch (e) {
    labels = {};
  }

  const colors = ['#d1453b', '#e2832b', '#e0b428', '#2f9e52'];
  const texts = [labels.weak, labels.fair, labels.good, labels.strong];

  input.addEventListener('input', () => {
    const value = input.value;

    if (!value) {
      fill.style.width = '0%';
      label.textContent = '';
      return;
    }

    let score = 0;
    if (value.length >= 8) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value) || value.length >= 12) score++;

    const tier = Math.max(score - 1, 0);
    fill.style.width = Math.max(score, 1) * 25 + '%';
    fill.style.backgroundColor = colors[tier];
    label.textContent = texts[tier] || '';
  });
});
