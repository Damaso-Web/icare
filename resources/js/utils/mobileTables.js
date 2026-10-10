// On phones every data table is shown as a stack of cards, one per row, with
// each value labelled by its column heading (see ".itable.cards" in app.css).
// CSS can't read a column's heading from a cell, so this copies the heading
// text onto each cell as data-label, for every table on every page, and keeps
// doing so as rows are loaded or re-rendered. It changes nothing on desktop.

function labelTable(table) {
  const headRows = table.tHead ? [...table.tHead.rows] : [];
  // Tables with merged heading cells (the printed-report layouts) keep their grid.
  const simple = headRows.length === 1 && ![...headRows[0].cells].some(th => th.colSpan > 1 || th.rowSpan > 1);
  table.classList.toggle('cards', simple);
  if (!simple) return;

  const labels = [...headRows[0].cells].map(th => th.textContent.trim());
  for (const body of table.tBodies) {
    for (const row of body.rows) {
      [...row.cells].forEach((cell, i) => {
        const label = cell.colSpan > 1 ? '' : (labels[i] || '');
        if (cell.dataset.label !== label) cell.dataset.label = label;
      });
    }
  }
}

export function watchMobileTables() {
  let queued = false;
  const run = () => {
    queued = false;
    document.querySelectorAll('table.itable').forEach(labelTable);
  };
  const schedule = () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(run);
  };
  // Rows arrive after the page does (API calls, paging, filters).
  new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
  schedule();
}
