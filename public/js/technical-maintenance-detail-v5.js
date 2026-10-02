(() => {
  'use strict';
  const activate = (name) => {
    const button = document.querySelector(`[data-tm3-tab="${name}"]`);
    if (button) button.click();
  };
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tm3-tab]').forEach((button) => {
      button.addEventListener('click', () => {
        try { sessionStorage.setItem('ego-maintenance-detail-tab', button.dataset.tm3Tab); } catch (_) {}
      });
    });
    document.querySelectorAll('[data-tm3-tab-open]').forEach((button) => {
      button.addEventListener('click', () => {
        activate(button.dataset.tm3TabOpen);
        document.querySelector('.tm3-tabs')?.scrollIntoView({behavior:'smooth', block:'start'});
      });
    });
    try {
      const saved = sessionStorage.getItem('ego-maintenance-detail-tab');
      if (saved && document.querySelector(`[data-tm3-panel="${saved}"]`)) activate(saved);
    } catch (_) {}
  });
})();
