'use strict';
// Apply professional-assigned colors through the DOM so the app's strict CSP remains enabled.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.agenda-appointment[data-appointment-color]').forEach((card) => {
    const color = card.dataset.appointmentColor || '';
    if (/^#[0-9a-fA-F]{6}$/.test(color)) card.style.setProperty('--appointment-color', color);
  });
});
