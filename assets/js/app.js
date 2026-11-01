/* APTARS — chart renderer for all dashboard/analysis charts */
document.addEventListener('DOMContentLoaded', function () {

  if (typeof Chart === 'undefined') return;
  const C = window.APTARS_CHARTS || {};

  const palette = ['#0f3460','#16a085','#e67e22','#c0392b','#8e44ad','#2980b9','#f39c12'];
  Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif';

  // --- Line chart (performance trend) ---
  if (C.trendChart && document.getElementById('trendChart')) {
    new Chart(document.getElementById('trendChart'), {
      type: 'line',
      data: {
        labels: C.trendChart.labels,
        datasets: [{
          label: 'Average %', data: C.trendChart.data,
          borderColor: '#0f3460', backgroundColor: 'rgba(15,52,96,.08)',
          tension: 0.3, fill: true, pointRadius: 5, pointBackgroundColor: '#0f3460'
        }]
      },
      options: { responsive:true, scales:{ y:{ beginAtZero:true, max:100 } },
                 plugins:{ legend:{ display:false } } }
    });
  }

  // --- Bar: grade distribution ---
  if (C.gradeChart && document.getElementById('gradeChart')) {
    new Chart(document.getElementById('gradeChart'), {
      type: 'bar',
      data: { labels:C.gradeChart.labels,
        datasets:[{ label:'Results', data:C.gradeChart.data, backgroundColor:'#0f3460' }] },
      options:{ responsive:true, plugins:{ legend:{ display:false } },
                scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
    });
  }

  // --- Bar: subject comparison ---
  if (C.subjectChart && document.getElementById('subjectChart')) {
    new Chart(document.getElementById('subjectChart'), {
      type: 'bar',
      data:{ labels:C.subjectChart.labels,
        datasets:[{ label:'Average %', data:C.subjectChart.data, backgroundColor:'#16a085' }] },
      options:{ responsive:true, plugins:{ legend:{ display:false } },
                scales:{ y:{ beginAtZero:true, max:100 } } }
    });
  }

  // --- Per-subject grade distribution (lecturer analysis) ---
  document.querySelectorAll('canvas[id^="gd"], canvas[id^="grade"]').forEach(function(cv){
    const key = cv.id.replace('gd','grade');
    if (cv.id === 'gradeChart') return;
    if (!C[key]) return;
    new Chart(cv, {
      type:'bar',
      data:{ labels:C[key].labels,
        datasets:[{ data:C[key].data, backgroundColor:palette }] },
      options:{ responsive:true, plugins:{ legend:{ display:false } },
                scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
    });
  });

  // --- Scatter: factor vs performance ---
  document.querySelectorAll('canvas[id^="scatter"]').forEach(function(cv){
    const pts = C[cv.id];
    if (!pts) return;
    new Chart(cv, {
      type:'scatter',
      data:{ datasets:[{
        label:'Student', data:pts,
        backgroundColor:'#0f3460', pointRadius:6
      }]},
      options:{
        responsive:true,
        plugins:{ legend:{ display:false },
          tooltip:{ callbacks:{ label: c => `(${c.raw.x}, ${c.raw.y}%)` } } },
        scales:{
          x:{ title:{ display:true, text:'Factor Rating' } },
          y:{ title:{ display:true, text:'Average %' }, beginAtZero:true, max:100 }
        }
      }
    });
  });

  // --- Confirm dialogs ---
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });
});