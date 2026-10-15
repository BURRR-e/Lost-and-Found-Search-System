async function loadDatabaseTables() {
  const reportsContainer = document.getElementById('reports-table-container');
  const claimsContainer = document.getElementById('claims-table-container');
  const inquiriesContainer = document.getElementById('inquiries-table-container');

  if (reportsContainer) {
    try {
      const res = await fetch('/api/reports.php?status=all');
      const data = await res.json();
      const reports = data.success ? data.data : [];

      let html = '<h2>Reports Table (' + reports.length + ')</h2>';
      if (reports.length === 0) {
        html += '<p>No reports in database.</p>';
      } else {
        html += '<table style="width:100%; border-collapse:collapse; text-align:left;" border="1" cellpadding="8">';
        html += '<tr style="background:rgba(255,255,255,0.1);"><th>ID</th><th>Report Code</th><th>Item Name</th><th>Category</th><th>Type</th><th>Location</th><th>Date</th><th>Status</th></tr>';
        reports.forEach(r => {
          html += `<tr>
            <td>${r.id}</td>
            <td>${r.report_id}</td>
            <td>${r.item_name}</td>
            <td>${r.category}</td>
            <td>${(r.report_type || '').toUpperCase()}</td>
            <td>${r.location}</td>
            <td>${r.date_event || r.created_at}</td>
            <td><strong>${r.status}</strong></td>
          </tr>`;
        });
        html += '</table>';
      }
      reportsContainer.innerHTML = html;
    } catch (e) {
      reportsContainer.innerHTML = '<p>Error loading reports table.</p>';
    }
  }

  if (claimsContainer) {
    try {
      const res = await fetch('/api/claims.php?status=all');
      const data = await res.json();
      const claims = data.success ? data.data : [];

      let html = '<h2>Claims Table (' + claims.length + ')</h2>';
      if (claims.length === 0) {
        html += '<p>No claims in database.</p>';
      } else {
        html += '<table style="width:100%; border-collapse:collapse; text-align:left;" border="1" cellpadding="8">';
        html += '<tr style="background:rgba(255,255,255,0.1);"><th>Claim ID</th><th>Report Code</th><th>Item Name</th><th>Claimant</th><th>Email</th><th>School ID</th><th>Status</th><th>Staff Feedback</th></tr>';
        claims.forEach(c => {
          html += `<tr>
            <td>${c.claim_id}</td>
            <td>${c.report_code || 'N/A'}</td>
            <td>${c.item_name || 'N/A'}</td>
            <td>${c.full_name}</td>
            <td>${c.email}</td>
            <td>${c.school_id}</td>
            <td><strong>${c.status}</strong></td>
            <td>${c.staff_feedback || ''}</td>
          </tr>`;
        });
        html += '</table>';
      }
      claimsContainer.innerHTML = html;
    } catch (e) {
      claimsContainer.innerHTML = '<p>Error loading claims table.</p>';
    }
  }

  if (inquiriesContainer) {
    try {
      const res = await fetch('/api/inquiries.php');
      const data = await res.json();
      const inquiries = data.success ? data.data : [];

      let html = '<h2>Inquiries Table (' + inquiries.length + ')</h2>';
      if (inquiries.length === 0) {
        html += '<p>No inquiries in database.</p>';
      } else {
        html += '<table style="width:100%; border-collapse:collapse; text-align:left;" border="1" cellpadding="8">';
        html += '<tr style="background:rgba(255,255,255,0.1);"><th>ID</th><th>Full Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th></tr>';
        inquiries.forEach(i => {
          html += `<tr>
            <td>${i.id}</td>
            <td>${i.full_name}</td>
            <td>${i.email}</td>
            <td>${i.subject}</td>
            <td>${i.message}</td>
            <td>${i.created_at}</td>
          </tr>`;
        });
        html += '</table>';
      }
      inquiriesContainer.innerHTML = html;
    } catch (e) {
      inquiriesContainer.innerHTML = '<p>Error loading inquiries table.</p>';
    }
  }
}

document.addEventListener('DOMContentLoaded', loadDatabaseTables);
loadDatabaseTables();
