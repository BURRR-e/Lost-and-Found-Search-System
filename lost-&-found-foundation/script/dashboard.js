(function () {
const confirmReport = document.getElementById('confirm-report');
const searchForm = document.querySelector('form.search');

function getStoredUser() {
  try { return JSON.parse(localStorage.getItem('lf_user')) || {}; } catch (e) { return {}; }
}

const images = {
  'school-supplies': '/assets/category/school-supplies.png',
  'electronics': '/assets/category/electronics.png',
  'personal-belongings': '/assets/category/personal-belongings.png',
  'clothing': '/assets/category/clothes.png',
  'others': '/assets/category/others.png'
};

async function displayConfirmedReports(searchQuery = '', categoryFilter = '', statusFilter = '') {
  if (!confirmReport) return;

  try {
    let url = '/api/reports.php?status=dashboard';

    if (statusFilter && statusFilter !== '') {
      url = `/api/reports.php?status=${encodeURIComponent(statusFilter)}`;
    }

    if (searchQuery) {
      url += `&search=${encodeURIComponent(searchQuery)}`;
    }

    if (categoryFilter) {
      url += `&category=${encodeURIComponent(categoryFilter)}`;
    }

    const res = await fetch(url);
    if (!res.ok) {
      confirmReport.innerHTML = '<p>Unable to load dashboard reports.</p>';
      return;
    }

    const result = await res.json();
    const reports = result.success ? result.data : [];

    confirmReport.innerHTML = '';

    if (reports.length === 0) {
      confirmReport.innerHTML = '<p>No items found matching your criteria.</p>';
      return;
    }

    reports.forEach(function(report) {
      const card = document.createElement('div');
      const catClass = report.category || 'others';
      card.classList.add('report-card', catClass);

      const image = document.createElement('img');
      image.src = report.photo_url || images[catClass] || images.others;
      image.alt = report.item_name;
      image.classList.add('categoryImage');

      const title = document.createElement('h3');
      title.textContent = report.item_name;

      const id = document.createElement('p');
      id.textContent = `Report ID: ${report.report_id}`;

      const description = document.createElement('p');
      description.textContent = `Description: ${report.description}`;

      const category = document.createElement('p');
      category.textContent = `Category: ${report.category}`;

      const location = document.createElement('p');
      location.textContent = `Location: ${report.location}`;

      const date = document.createElement('p');
      date.textContent = `Date: ${report.date_event || report.created_at}`;

      const type = document.createElement('p');
      type.textContent = `Report Type: ${(report.report_type || 'lost').toUpperCase()}`;

      const statusP = document.createElement('p');
      statusP.textContent = `Status: ${report.status}`;

      const claimBtn = document.createElement('button');
      claimBtn.type = 'button';
      claimBtn.textContent = 'Claim Item';
      claimBtn.style.marginTop = '10px';

      // Only items still waiting to be claimed can be claimed.
      if (report.status !== 'confirmed') {
        claimBtn.disabled = true;
        claimBtn.textContent = report.status === 'returned' ? 'Returned' : 'Already Claimed';
      }

      claimBtn.addEventListener('click', async function() {
        if (confirm(`Submit claim request for "${report.item_name}"?`)) {
          try {
            const user = getStoredUser();
            const claimRes = await fetch('/api/claims.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                report_code: report.report_id,
                item_name: report.item_name,
                email: user.email,
                full_name: user.full_name,
                school_id: user.school_id
              })
            });
            const claimResult = await claimRes.json();
            if (claimRes.ok && claimResult.success) {
              alert('Claim request submitted! You can view status in My Claims.');
              displayConfirmedReports();
            } else {
              alert(claimResult.error || 'Could not submit claim request.');
            }
          } catch (e) {
            alert('Error submitting claim request.');
          }
        }
      });

      card.append(
        image,
        title,
        id,
        description,
        category,
        location,
        date,
        type,
        statusP,
        claimBtn
      );

      confirmReport.appendChild(card);
    });
  } catch (err) {
    console.error('Error fetching dashboard reports:', err);
    if (confirmReport) {
      confirmReport.innerHTML = '<p>Error loading reports from database.</p>';
    }
  }
}

if (searchForm) {
  searchForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const searchVal = document.getElementById('search').value.trim();
    const categoryVal = document.getElementById('category').value;
    const statusVal = document.getElementById('status').value;
    displayConfirmedReports(searchVal, categoryVal, statusVal);
  });

  searchForm.addEventListener('reset', function() {
    setTimeout(() => {
      displayConfirmedReports();
    }, 50);
  });
}

displayConfirmedReports();
})();
