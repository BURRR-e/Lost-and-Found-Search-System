const reportContainer = document.getElementById('report-container');

const images = {
  'school-supplies': '/assets/category/school-supplies.png',
  'electronics': '/assets/category/electronics.png',
  'personal-belongings': '/assets/category/personal-belongings.png',
  'clothing': '/assets/category/clothes.png',
  'others': '/assets/category/others.png'
};

function displayReports() {
  const reports = JSON.parse(localStorage.getItem('reportData')) || [];
  
  reportContainer.innerHTML = '';
  
  reports.slice().reverse().forEach(function(report) {
    const card = document.createElement('div');
    card.classList.add('report-card', report[3]);
    
    const image = document.createElement('img');
    image.src = report[6] || images[report[3]] || images['others'];
    image.alt = report[1];
    image.classList.add('categoryImage');
    
    const title = document.createElement('h3');
    title.textContent = report[1];
    
    const id = document.createElement('p');
    id.textContent = `Report ID: ${report[0]}`;
    
    const description = document.createElement('p');
    description.textContent = `Description: ${report[2]}`;
    
    const category = document.createElement('p');
    category.textContent = `Category: ${report[3]}`;
    
    const location = document.createElement('p');
    location.textContent = `Location: ${report[5]}`;
    
    const date = document.createElement('p');
    date.textContent = `Date: ${report[4]}`;
    
    const type = document.createElement('p');
    type.textContent = `Report Type: ${report[7]}`;
    
    const buttonConfirm = document.createElement('button');
    buttonConfirm.type = 'button';
    buttonConfirm.textContent = 'Confirm';
    buttonConfirm.classList.add('Confirm');
    
    buttonConfirm.addEventListener('click', function() {
      const currentReports = JSON.parse(
        localStorage.getItem('reportData')
      ) || [];
      
      const dashboardReports = JSON.parse(
        localStorage.getItem('dashboardReports')
      ) || [];
      
      const selectedReport = currentReports.find(function(item) {
        return item[0] === report[0];
      });
      
      if (!selectedReport) {
        displayReports();
        return;
      }
      
      const alreadyExists = dashboardReports.some(function(item) {
        return item[0] === report[0];
      });
      
      if (!alreadyExists) {
        dashboardReports.push(selectedReport);
      }
      
      const remainingReports = currentReports.filter(function(item) {
        return item[0] !== report[0];
      });
      
      localStorage.setItem(
        'dashboardReports',
        JSON.stringify(dashboardReports)
      );
      
      localStorage.setItem(
        'reportData',
        JSON.stringify(remainingReports)
      );
      
      displayReports();
    });
    
    const buttonRemove = document.createElement('button');
    buttonRemove.type = 'button';
    buttonRemove.textContent = 'Remove';
    buttonRemove.classList.add('Remove');
    
    buttonRemove.addEventListener('click', function() {
      const currentReports = JSON.parse(
        localStorage.getItem('reportData')
      ) || [];
      
      const dashboardReports = JSON.parse(
        localStorage.getItem('dashboardReports')
      ) || [];
      
      const remainingReports = currentReports.filter(function(item) {
        return item[0] !== report[0];
      });
      
      const remainingDashboardReports = dashboardReports.filter(
        function(item) {
          return item[0] !== report[0];
        }
      );
      
      localStorage.setItem(
        'reportData',
        JSON.stringify(remainingReports)
      );
      
      localStorage.setItem(
        'dashboardReports',
        JSON.stringify(remainingDashboardReports)
      );
      
      displayReports();
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
}

displayReports();