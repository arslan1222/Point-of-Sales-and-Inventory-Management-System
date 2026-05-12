// assets/js/app.js

// ─── Sidebar Toggle ─────────────────────────────────
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const wrapper = document.getElementById('mainWrapper');
  if (window.innerWidth <= 768) {
    sidebar.classList.toggle('show');
  } else {
    sidebar.classList.toggle('hidden');
    wrapper.classList.toggle('expanded');
  }
}

// ─── Auto-dismiss alerts ────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const alerts = document.querySelectorAll('.alert.alert-dismissible');
  alerts.forEach(a => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(a);
      if (bsAlert) bsAlert.close();
    }, 4000);
  });

  // Tooltips
  const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  tooltips.forEach(el => new bootstrap.Tooltip(el));
});

// ─── Confirm delete helper ──────────────────────────
function confirmDelete(msg, formId) {
  if (confirm(msg || 'Are you sure you want to delete this record?')) {
    if (formId) document.getElementById(formId).submit();
    return true;
  }
  return false;
}

// ─── Format currency ────────────────────────────────
function formatCurrency(amount) {
  return '$' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ─── AJAX helper ────────────────────────────────────
function ajaxPost(url, data, callback) {
  const formData = new FormData();
  for (const key in data) formData.append(key, data[key]);
  fetch(url, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(callback)
    .catch(err => console.error('AJAX Error:', err));
}