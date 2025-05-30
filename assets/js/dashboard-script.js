const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const toggle = document.getElementById('menu-toggle');
const closeBtn = document.getElementById('close-sidebar');
const main = document.getElementById('main-content');

// เปิดเมนู
toggle.addEventListener('click', function () {
  sidebar.classList.add('show');
  overlay.classList.add('show');
});

// ปิดเมนู
overlay.addEventListener('click', function () {
  sidebar.classList.remove('show');
  overlay.classList.remove('show');
});

closeBtn.addEventListener('click', function () {
  sidebar.classList.remove('show');
  overlay.classList.remove('show');
});

// Chart.js กราฟ
const ctx = document.getElementById('repairsChart');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
      datasets: [{
        label: 'จำนวนผลงาน',
        data: [0, 0, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0],
        backgroundColor: 'rgba(59, 130, 246, 0.5)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
}
