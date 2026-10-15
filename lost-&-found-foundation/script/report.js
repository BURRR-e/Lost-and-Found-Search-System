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

function getReports() {
  return JSON.parse(localStorage.getItem('reportData')) || [];
}

function displayReports() {
  if (!reportContainer) return;
  
  const reports = getReports();
  reportContainer.innerHTML = '';
  
  reports.slice().reverse().slice(0, 6).forEach(function(savedReport) {
    const card = document.createElement('div');
    card.classList.add('report-card', savedReport[3]);
    
    const image = document.createElement('img');
    image.src = savedReport[6] || images[savedReport[3]] || images.others;
    image.alt = savedReport[1];
    image.classList.add('categoryImage');
    
    const title = document.createElement('h3');
    title.textContent = savedReport[1];
    
    const id = document.createElement('p');
    id.textContent = `Report ID: ${savedReport[0]}`;
    
    const description = document.createElement('p');
    description.textContent = `Description: ${savedReport[2]}`;
    
    const category = document.createElement('p');
    category.textContent = `Category: ${savedReport[3]}`;
    
    const location = document.createElement('p');
    location.textContent = `Location: ${savedReport[5]}`;
    
    const date = document.createElement('p');
    date.textContent = `Date Reported: ${savedReport[4]}`;
    
    card.append(image, title, id, description, category, location, date);
    reportContainer.appendChild(card);
    
  });
}

function generateReportID() {
  const today = new Date();

  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  const year = today.getFullYear();

  const date = `${month}${day}${year}`;
  const storageKey = `reportCount-${date}`;

  let count = Number(localStorage.getItem(storageKey)) || 0;
  count++;

  localStorage.setItem(storageKey, count);

  return `R-${date}-#${String(count).padStart(3, '0')}`;
}


function readPhoto(photo) {
  return new Promise(function(resolve, reject) {
    if (!photo) {
      resolve('');
      return;
    }
    
    const reader = new FileReader();
    
    reader.onload = function() {
      resolve(reader.result);
    };
    
    reader.onerror = function() {
      reject(new Error('Unable to read photo.'));
    };
    
    reader.readAsDataURL(photo);
    
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
    
    if (!images[category]) {
      alert('Please select a valid category.');
      return;
    }
    
    confirmation.innerHTML = '';
    
    const card = document.createElement('div');
    card.classList.add('report-card', category);
    
    const image = document.createElement('img');
    image.src = photo ? URL.createObjectURL(photo) : images[category];
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
    confirmButton.textContent = 'Confirm';
    
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
        const savedPhoto = await readPhoto(photo);
        const reports = getReports();
        
        reports.push([
          generateReportID(),
          itemName,
          description,
          category,
          date,
          location,
          savedPhoto || images[category]
        ]);
        
        localStorage.setItem('reportData', JSON.stringify(reports));
        
        if (image.src.startsWith('blob:')) {
          URL.revokeObjectURL(image.src);
        }
        
        confirmation.hidden = true;
        confirmation.innerHTML = '';
        report.reset();
        
        displayReports();
      } catch (error) {
        confirmButton.disabled = false;
        alert('Unable to save the report. Please try again.');
      }
    });
    
  });
}

displayReports();