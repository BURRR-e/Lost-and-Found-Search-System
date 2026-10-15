async function loadHomeData() {
  // Load stats
  const lostElem = document.getElementById('lost-items-count');
  const foundElem = document.getElementById('found-items-count');
  const returnedElem = document.getElementById('returned-items-count');

  try {
    const statsRes = await fetch('/api/stats.php');
    if (statsRes.ok) {
      const statsData = await statsRes.json();
      if (statsData.success) {
        const s = statsData.data;
        if (lostElem) lostElem.textContent = `${s.lost_items} reported items`;
        if (foundElem) foundElem.textContent = `${s.found_items} reported items`;
        if (returnedElem) returnedElem.textContent = `${s.returned_items} returned items`;
      }
    }
  } catch (e) {
    console.error('Failed to load stats:', e);
  }

  // Load recent reports
  const container = document.getElementById('home-recent-reports');
  if (!container) return;

  try {
    const reportsRes = await fetch('/api/reports.php?status=all&limit=6');
    if (!reportsRes.ok) return;

    const reportsData = await reportsRes.json();
    const reports = reportsData.success ? reportsData.data : [];

    container.innerHTML = '';

    if (reports.length === 0) {
      container.innerHTML = '<p>No recently reported items.</p>';
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
      card.style.border = '1px solid rgba(255, 255, 255, 0.15)';
      card.style.borderRadius = '8px';
      card.style.padding = '15px';
      card.style.marginBottom = '15px';

      const img = document.createElement('img');
      img.src = report.photo_url || images[catClass] || images.others;
      img.alt = report.item_name;
      img.classList.add('categoryImage');

      const title = document.createElement('h3');
      title.textContent = report.item_name;

      const codeP = document.createElement('p');
      codeP.textContent = `Report ID: ${report.report_id}`;

      const descP = document.createElement('p');
      descP.textContent = `Description: ${report.description}`;

      const catP = document.createElement('p');
      catP.textContent = `Category: ${report.category}`;

      const locP = document.createElement('p');
      locP.textContent = `Location: ${report.location}`;

      const dateP = document.createElement('p');
      dateP.textContent = `Date: ${report.date_event || report.created_at}`;

      card.append(img, title, codeP, descP, catP, locP, dateP);
      container.appendChild(card);
    });

  } catch (e) {
    console.error('Failed to load recent reports:', e);
  }
}

document.addEventListener('DOMContentLoaded', loadHomeData);
loadHomeData();
