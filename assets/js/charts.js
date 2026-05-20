/**
 * Student Performance Dashboard — Chart.js Helpers
 * Shared chart defaults and factory functions updated for Dark/Glassmorphic Theme
 */

// ─── Global Defaults ──────────────────────────────────────
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.color = '#94a3b8'; // Brighter text color for dark mode readability
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)'; // Dark glass tooltip
Chart.defaults.plugins.tooltip.titleColor = '#f8fafc';
Chart.defaults.plugins.tooltip.bodyColor = '#cbd5e1';
Chart.defaults.plugins.tooltip.padding = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 10;
Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.1)';
Chart.defaults.plugins.tooltip.borderWidth = 1;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
Chart.defaults.plugins.legend.labels.color = '#94a3b8';

// ─── Color Palette ─────────────────────────────────────────
const COLORS = {
  accent:  '#6366f1',
  success: '#10b981', // Updated to modern emerald emerald
  warning: '#f59e0b',
  danger:  '#ef4444',
  info:    '#06b6d4',
  purple:  '#8b5cf6',
  orange:  '#f97316',
  pink:    '#ec4899',
};

const SUBJECT_COLORS = [
  COLORS.accent, COLORS.success, COLORS.warning,
  COLORS.info,   COLORS.purple,  COLORS.orange,
  COLORS.pink,   COLORS.danger,
];

// Grid lines color for dark mode
const GRID_COLOR = 'rgba(255, 255, 255, 0.06)';

// ─── Bar Chart: Marks per Subject ──────────────────────────
function createMarksBarChart(canvasId, labels, data, title = 'Marks per Subject') {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  
  const backgrounds = data.map((_, i) => SUBJECT_COLORS[i % SUBJECT_COLORS.length] + '44'); // slightly higher opacity for dark mode
  const borders     = data.map((_, i) => SUBJECT_COLORS[i % SUBJECT_COLORS.length]);

  return new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Marks Obtained',
        data,
        backgroundColor: backgrounds,
        borderColor: borders,
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, title: { display: false } },
      scales: {
        y: {
          beginAtZero: true, max: 100,
          grid: { color: GRID_COLOR },
          ticks: { color: '#94a3b8', callback: v => v + '%' }
        },
        x: { 
          grid: { display: false },
          ticks: { color: '#94a3b8' }
        }
      },
      animation: { duration: 800, easing: 'easeInOutQuart' }
    }
  });
}

// ─── Line Chart: GPA Trend ──────────────────────────────────
function createGPATrendChart(canvasId, labels, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  return new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'GPA',
        data,
        borderColor: COLORS.accent,
        backgroundColor: 'rgba(99,102,241,0.15)',
        borderWidth: 3,
        pointBackgroundColor: COLORS.accent,
        pointBorderColor: '#080c14', // Match theme background
        pointBorderWidth: 2.5,
        pointRadius: 6,
        pointHoverRadius: 8,
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: {
          min: 0, max: 4.0,
          grid: { color: GRID_COLOR },
          ticks: { stepSize: 0.5, color: '#94a3b8', callback: v => v.toFixed(1) }
        },
        x: { 
          grid: { display: false },
          ticks: { color: '#94a3b8' }
        }
      },
      animation: { duration: 900, easing: 'easeInOutCubic' }
    }
  });
}

// ─── Multi-Line Chart: Marks per Subject over Semesters ────
function createSubjectTrendChart(canvasId, labels, datasets) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  const chartDatasets = datasets.map((ds, i) => ({
    label: ds.label,
    data: ds.data,
    borderColor: SUBJECT_COLORS[i % SUBJECT_COLORS.length],
    backgroundColor: 'transparent',
    borderWidth: 2.5,
    pointBackgroundColor: SUBJECT_COLORS[i % SUBJECT_COLORS.length],
    pointRadius: 4,
    tension: 0.3,
  }));

  return new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: chartDatasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { 
        legend: { 
          position: 'top',
          labels: { color: '#94a3b8' }
        } 
      },
      scales: {
        y: { beginAtZero: true, max: 100, grid: { color: GRID_COLOR }, ticks: { color: '#94a3b8', callback: v => v + '%' } },
        x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
      }
    }
  });
}

// ─── Doughnut: Attendance Summary ──────────────────────────
function createAttendanceDoughnut(canvasId, present, absent, late) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  return new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Present', 'Absent', 'Late'],
      datasets: [{
        data: [present, absent, late],
        backgroundColor: [
          COLORS.success + 'd0', COLORS.danger + 'd0', COLORS.warning + 'd0'
        ],
        borderColor: '#1e293b', // Match card background in dark mode
        borderWidth: 3,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: { 
        legend: { 
          position: 'bottom',
          labels: { color: '#94a3b8' }
        } 
      },
      animation: { duration: 800 }
    }
  });
}
