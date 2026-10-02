(function () {
  'use strict';
  const page = document.querySelector('.md7-page');
  if (!page) return;

  const tabs = page.querySelectorAll('[data-md7-tab]');
  const panels = page.querySelectorAll('[data-md7-panel]');
  tabs.forEach((button) => button.addEventListener('click', () => {
    tabs.forEach((item) => item.classList.remove('active'));
    panels.forEach((item) => item.classList.remove('active'));
    button.classList.add('active');
    const panel = page.querySelector('[data-md7-panel="' + button.dataset.md7Tab + '"]');
    if (panel) panel.classList.add('active');
  }));

  const editForm = page.querySelector('[data-md7-form]');
  const readView = page.querySelector('[data-md7-read]');
  const toggleEdit = (editing) => {
    if (!editForm || !readView) return;
    editForm.classList.toggle('active', editing);
    readView.classList.toggle('hidden', editing);
    if (editing) {
      const overviewTab = page.querySelector('[data-md7-tab="overview"]');
      if (overviewTab) overviewTab.click();
      editForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };
  page.querySelectorAll('[data-md7-edit]').forEach((button) => button.addEventListener('click', () => toggleEdit(true)));
  page.querySelectorAll('[data-md7-cancel]').forEach((button) => button.addEventListener('click', () => toggleEdit(false)));
})();
