async function renderStats() {
  const panel = document.getElementById('stats-panel');
  if (!panel) return;

  try {
    const res = await fetch('/api/stats.php');
    if (!res.ok) {
      panel.innerHTML = '<p>Unable to load stats from database.</p>';
      return;
    }

    const json = await res.json();
    const stats = json.data;

    panel.innerHTML = `
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="border:1px solid rgba(255,255,255,0.15); padding:15px; border-radius:8px; text-align:center;">
          <h3>Lost Items</h3>
          <p style="font-size:24px; font-weight:bold; margin-top:10px;">${stats.lost_items}</p>
        </div>
        <div style="border:1px solid rgba(255,255,255,0.15); padding:15px; border-radius:8px; text-align:center;">
          <h3>Found Items</h3>
          <p style="font-size:24px; font-weight:bold; margin-top:10px;">${stats.found_items}</p>
        </div>
        <div style="border:1px solid rgba(255,255,255,0.15); padding:15px; border-radius:8px; text-align:center;">
          <h3>Returned Items</h3>
          <p style="font-size:24px; font-weight:bold; margin-top:10px;">${stats.returned_items}</p>
        </div>
        <div style="border:1px solid rgba(255,255,255,0.15); padding:15px; border-radius:8px; text-align:center;">
          <h3>Pending Reports</h3>
          <p style="font-size:24px; font-weight:bold; margin-top:10px;">${stats.pending_reports}</p>
        </div>
        <div style="border:1px solid rgba(255,255,255,0.15); padding:15px; border-radius:8px; text-align:center;">
          <h3>Pending Claims</h3>
          <p style="font-size:24px; font-weight:bold; margin-top:10px;">${stats.pending_claims}</p>
        </div>
      </div>

      <h2>Category Distribution</h2>
      <ul style="list-style:none; padding:0; margin-top:15px;">
        ${(stats.categories || []).map(c => `<li style="padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.1);"><strong>${c.category}:</strong> ${c.total} items</li>`).join('')}
      </ul>
    `;
  } catch (e) {
    panel.innerHTML = '<p>Error loading statistics.</p>';
  }
}

document.addEventListener('DOMContentLoaded', renderStats);
renderStats();
