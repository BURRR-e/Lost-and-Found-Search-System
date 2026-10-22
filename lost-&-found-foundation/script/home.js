async function loadHomeData() {
  try {
    const statsRes = await fetch('/api/stats.php?scope=mine', { credentials: 'same-origin' });
    if (statsRes.ok) {
      const statsData = await statsRes.json();
      if (statsData.success) {
        const stats = statsData.data;
        const signedIn = statsData.signed_in !== false;
        const lostEl = document.getElementById('lost-items-count');
        const foundEl = document.getElementById('found-items-count');
        const returnedEl = document.getElementById('returned-items-count');

        if (!signedIn) {
          const msg = 'Sign in to see your items';
          if (lostEl) lostEl.textContent = msg;
          if (foundEl) foundEl.textContent = msg;
          if (returnedEl) returnedEl.textContent = msg;
        } else {
          if (lostEl) lostEl.textContent = `${stats.lost_items} of your reported items`;
          if (foundEl) foundEl.textContent = `${stats.found_items} of your reported items`;
          if (returnedEl) returnedEl.textContent = `${stats.returned_items} of your items returned`;
        }
      }
    }
  } catch (err) {
    console.error('Error fetching home stats:', err);
  }

  const recentContainer = document.getElementById('home-recent-reports');
  if (!recentContainer) return;

  try {
    const reportsRes = await fetch('/api/reports.php?status=dashboard&limit=6');
    if (reportsRes.ok) {
      const reportsData = await reportsRes.json();
      if (reportsData.success) {
        const reports = reportsData.data;
        recentContainer.innerHTML = '';

        if (reports.length === 0) {
          recentContainer.innerHTML = '<p>No recent reports found.</p>';
          return;
        }

        const images = {
          'school-supplies': '/assets/category/school-supplies.png',
          'electronics': '/assets/category/electronics.png',
          'personal-belongings': '/assets/category/personal-belongings.png',
          'clothing': '/assets/category/clothes.png',
          'others': '/assets/category/others.png'
        };

        reports.forEach(report => {
          const card = document.createElement('div');
          const catClass = report.category || 'others';
          card.classList.add('report-card', catClass);

          const img = document.createElement('img');
          img.src = report.photo_url || images[catClass] || images.others;
          img.alt = report.item_name;
          img.classList.add('categoryImage');

          const title = document.createElement('h3');
          title.textContent = report.item_name;

          const id = document.createElement('p');
          id.textContent = `Report ID: ${report.report_id}`;

          const desc = document.createElement('p');
          desc.textContent = `Description: ${report.description}`;

          const cat = document.createElement('p');
          cat.textContent = `Category: ${report.category}`;

          const loc = document.createElement('p');
          loc.textContent = `Location: ${report.location}`;

          const date = document.createElement('p');
          date.textContent = `Date: ${report.date_event || report.created_at}`;

          card.append(img, title, id, desc, cat, loc, date);
          recentContainer.appendChild(card);
        });
      }
    }
  } catch (err) {
    console.error('Error fetching recent reports:', err);
  }
}

document.addEventListener('DOMContentLoaded', loadHomeData);
loadHomeData();
