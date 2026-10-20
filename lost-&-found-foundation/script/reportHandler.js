(function () {
const reportContainer = document.getElementById('report-container');

const images = {
  'school-supplies': '/assets/category/school-supplies.png',
  'electronics': '/assets/category/electronics.png',
  'personal-belongings': '/assets/category/personal-belongings.png',
  'clothing': '/assets/category/clothes.png',
  'others': '/assets/category/others.png'
};

async function displayReports() {
  if (!reportContainer) return;

  try {
    const res = await fetch('/api/reports.php?status=pending');
    if (!res.ok) {
      reportContainer.innerHTML = '<p>Unable to load pending reports.</p>';
      return;
    }

    const result = await res.json();
    const reports = result.success ? result.data : [];

    reportContainer.innerHTML = '';

    if (reports.length === 0) {
      reportContainer.innerHTML = '<p>No pending report requests.</p>';
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

      const buttonConfirm = document.createElement('button');
      buttonConfirm.type = 'button';
      buttonConfirm.textContent = 'Confirm & Publish';
      buttonConfirm.classList.add('Confirm');

      buttonConfirm.addEventListener('click', async function() {
        buttonConfirm.disabled = true;
        try {
          const patchRes = await fetch('/api/reports.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: report.id,
              report_id: report.report_id,
              status: 'confirmed'
            })
          });

          if (patchRes.ok) {
            alert(`Report ${report.report_id} confirmed and published to Dashboard!`);
            displayReports();
          } else {
            alert('Failed to confirm report.');
            buttonConfirm.disabled = false;
          }
        } catch (e) {
          alert('Network error confirming report.');
          buttonConfirm.disabled = false;
        }
      });

      const buttonRemove = document.createElement('button');
      buttonRemove.type = 'button';
      buttonRemove.textContent = 'Reject / Remove';
      buttonRemove.classList.add('Remove');

      buttonRemove.addEventListener('click', async function() {
        if (confirm(`Reject report ${report.report_id}?`)) {
          buttonRemove.disabled = true;
          try {
            const delRes = await fetch(`/api/reports.php?id=${report.id}`, {
              method: 'DELETE'
            });

            if (delRes.ok) {
              displayReports();
            } else {
              alert('Failed to remove report.');
              buttonRemove.disabled = false;
            }
          } catch (e) {
            alert('Network error removing report.');
            buttonRemove.disabled = false;
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
        buttonConfirm,
        buttonRemove
      );

      reportContainer.appendChild(card);
    });
  } catch (e) {
    reportContainer.innerHTML = '<p>Error loading pending reports from server.</p>';
  }
}

displayReports();
})();
