function esc(v) {
  return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

async function loadAdminAccountOverview() {
  try {
    const statsRes = await fetch('/api/stats.php');
    if (statsRes.ok) {
      const statsJson = await statsRes.json();
      if (statsJson.success) {
        const stats = statsJson.data;
        const statsSection = document.querySelector('body > div:nth-of-type(2)');
        if (statsSection) {
          const boxes = statsSection.querySelectorAll('div > p');
          if (boxes[0]) boxes[0].textContent = (stats.lost_items + stats.found_items).toString();
          if (boxes[1]) boxes[1].textContent = stats.pending_claims.toString();
          if (boxes[2]) boxes[2].textContent = stats.returned_items.toString();
          if (boxes[3]) boxes[3].textContent = stats.pending_reports.toString();
        }
      }
    }
  } catch (e) {
    console.error('Error loading admin stats:', e);
  }

  try {
    const reportsRes = await fetch('/api/reports.php?status=pending');
    if (reportsRes.ok) {
      const reportsJson = await reportsRes.json();
      const reports = reportsJson.data || [];
      const reportsContainer = document.querySelector('body > div:nth-of-type(3)');
      if (reportsContainer) {
        const heading = reportsContainer.querySelector('h2');
        reportsContainer.innerHTML = '';
        if (heading) reportsContainer.appendChild(heading);

        if (reports.length === 0) {
          const p = document.createElement('p');
          p.textContent = 'No pending item reports.';
          p.style.padding = '10px';
          reportsContainer.appendChild(p);
        } else {
          reports.slice(0, 3).forEach(r => {
            const div = document.createElement('div');
            div.className = 'box1';
            div.style.border = '1px solid rgba(255,255,255,0.1)';
            div.style.padding = '10px';
            div.style.marginBottom = '10px';
            div.innerHTML = `
              <h3>${esc(r.item_name)} (${esc(r.report_id)})</h3>
              <p>Category: ${esc(r.category)}</p>
              <p>Type: ${esc(r.report_type)}</p>
              <p>Status: ${esc(r.status)}</p>
              <button type="button" onclick="confirmReportFromAdmin(${r.id})">Approve Report</button>
            `;
            reportsContainer.appendChild(div);
          });
        }
      }
    }
  } catch (e) {}

  try {
    const claimsRes = await fetch('/api/claims.php?status=pending');
    if (claimsRes.ok) {
      const claimsJson = await claimsRes.json();
      const claims = claimsJson.data || [];
      const claimsContainer = document.querySelector('body > div:nth-of-type(4)');
      if (claimsContainer) {
        const heading = claimsContainer.querySelector('h2');
        claimsContainer.innerHTML = '';
        if (heading) claimsContainer.appendChild(heading);

        if (claims.length === 0) {
          const p = document.createElement('p');
          p.textContent = 'No pending claim requests.';
          p.style.padding = '10px';
          claimsContainer.appendChild(p);
        } else {
          claims.slice(0, 3).forEach((c, idx) => {
            const div = document.createElement('div');
            div.className = idx % 2 === 0 ? 'box1' : 'box2';
            div.style.border = '1px solid rgba(255,255,255,0.1)';
            div.style.padding = '10px';
            div.style.marginBottom = '10px';
            div.innerHTML = `
              <h3>Claim ${esc(c.claim_id)}</h3>
              <p>Item: ${esc(c.item_name || 'Item')}</p>
              <p>Claimant: ${esc(c.full_name)} (${esc(c.email)})</p>
              <p>Status: ${esc(c.status)}</p>
              <button type="button" onclick="approveClaimFromAdmin(${c.id})">Approve Claim</button>
            `;
            claimsContainer.appendChild(div);
          });
        }
      }
    }
  } catch (e) {}
}

async function confirmReportFromAdmin(id) {
  try {
    const res = await fetch('/api/reports.php', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, status: 'confirmed' })
    });
    if (res.ok) {
      alert('Report approved!');
      loadAdminAccountOverview();
    }
  } catch (e) {}
}

async function approveClaimFromAdmin(id) {
  try {
    const res = await fetch('/api/claims.php', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, status: 'approved', staff_feedback: 'Visit office to collect.' })
    });
    if (res.ok) {
      alert('Claim approved!');
      loadAdminAccountOverview();
    }
  } catch (e) {}
}

document.addEventListener('DOMContentLoaded', loadAdminAccountOverview);
loadAdminAccountOverview();
