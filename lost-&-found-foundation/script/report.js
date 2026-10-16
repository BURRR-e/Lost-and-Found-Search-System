const report = document.getElementById('report');
const confirmation = document.getElementById('confirmation');
const reportContainer = document.getElementById('report-container');

const images = {
  'school-supplies': '/assets/category/school-supplies.png',
  'electronics': '/assets/category/electronics.png',
  'personal-belongings': '/assets/category/personal-belongings.png',
  'clothing': '/assets/category/clothes.png',
  'others': '/assets/category/others.png'
};

async function getReports() {
  try {
    const response = await fetch('/api/reports.php?status=all');
    if (!response.ok) return [];
    const json = await response.json();
    return json.success ? json.data : [];
  } catch (err) {
    console.error('Failed to fetch reports:', err);
    return [];
  }
}

async function displayReports() {
  if (!reportContainer) return;

  const reports = await getReports();
  reportContainer.innerHTML = '';

  if (reports.length === 0) {
    reportContainer.innerHTML = '<p>No reports found.</p>';
    return;
  }

  reports.slice(0, 6).forEach(function(savedReport) {
    const card = document.createElement('div');
    const categoryClass = savedReport.category || 'others';
    card.classList.add('report-card', categoryClass);

    const image = document.createElement('img');
    image.src = savedReport.photo_url || images[categoryClass] || images.others;
    image.alt = savedReport.item_name || 'Reported item';
    image.classList.add('categoryImage');

    const title = document.createElement('h3');
    title.textContent = savedReport.item_name;

    const id = document.createElement('p');
    id.textContent = `Report ID: ${savedReport.report_id}`;

    const description = document.createElement('p');
    description.textContent = `Description: ${savedReport.description}`;

    const category = document.createElement('p');
    category.textContent = `Category: ${savedReport.category}`;

    const location = document.createElement('p');
    location.textContent = `Location: ${savedReport.location}`;

    const date = document.createElement('p');
    date.textContent = `Date Reported: ${savedReport.date_event || savedReport.created_at}`;

    card.append(image, title, id, description, category, location, date);
    reportContainer.appendChild(card);
  });
}

if (report && confirmation) {
  report.addEventListener('submit', function(event) {
    event.preventDefault();

    const itemName = report.elements['item-name'].value.trim();
    const category = report.elements['item-category'].value;
    const description = report.elements['description'].value.trim();
    const date = report.elements['date'].value;
    const location = report.elements['location'].value.trim();
    const photo = report.elements['photo'].files[0];

    if (!images[category] && category !== 'clothing') {
      alert('Please select a valid category.');
      return;
    }

    confirmation.innerHTML = '';

    const card = document.createElement('div');
    card.classList.add('report-card', category);

    const image = document.createElement('img');
    image.src = photo ? URL.createObjectURL(photo) : (images[category] || images.others);
    image.alt = itemName;
    image.classList.add('categoryImage');

    const title = document.createElement('h3');
    title.textContent = itemName;

    const categoryText = document.createElement('p');
    categoryText.textContent = `Category: ${category}`;

    const descriptionText = document.createElement('p');
    descriptionText.textContent = `Description: ${description}`;

    const locationText = document.createElement('p');
    locationText.textContent = `Location: ${location}`;

    const dateText = document.createElement('p');
    dateText.textContent = `Date Reported: ${date}`;

    const cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.textContent = 'Cancel';

    const confirmButton = document.createElement('button');
    confirmButton.type = 'button';
    confirmButton.textContent = 'Confirm Submit';

    card.append(
      image,
      title,
      categoryText,
      descriptionText,
      locationText,
      dateText,
      cancelButton,
      confirmButton
    );

    confirmation.appendChild(card);
    confirmation.hidden = false;

    cancelButton.addEventListener('click', function() {
      if (image.src.startsWith('blob:')) {
        URL.revokeObjectURL(image.src);
      }
      confirmation.hidden = true;
      confirmation.innerHTML = '';
      report.reset();
    });

    confirmButton.addEventListener('click', async function() {
      confirmButton.disabled = true;

      try {
        const reportTypeSelect = report.elements['report-type'];
        const reportType = reportTypeSelect ? reportTypeSelect.value : 'lost';

        const formData = new FormData();
        formData.append('item-name', itemName);
        formData.append('item-category', category);
        formData.append('description', description);
        formData.append('date', date);
        formData.append('location', location);
        formData.append('report_type', reportType);
        if (photo) {
          formData.append('photo', photo);
        }

        const res = await fetch('/api/reports.php', {
          method: 'POST',
          body: formData
        });

        const result = await res.json();

        if (res.ok && result.success) {
          alert('Report submitted successfully! Waiting for staff review.');
          if (image.src.startsWith('blob:')) {
            URL.revokeObjectURL(image.src);
          }
          confirmation.hidden = true;
          confirmation.innerHTML = '';
          report.reset();
          displayReports();
        } else {
          alert(result.error || 'Unable to save the report.');
          confirmButton.disabled = false;
        }
      } catch (error) {
        confirmButton.disabled = false;
        alert('Unable to save the report. Please try again.');
      }
    });

  });
}

displayReports();
