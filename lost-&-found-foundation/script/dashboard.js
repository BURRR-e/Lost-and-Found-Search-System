const confirmReport = document.getElementById('confirm-report');

const images = {
'school-supplies': '/assets/category/school-supplies.png',
'electronics': '/assets/category/electronics.png',
'personal-belongings': '/assets/category/personal-belongings.png',
'clothing': '/assets/category/clothes.png',
'others': '/assets/category/others.png'
};

function displayConfirmedReports() {
const reports = JSON.parse(
localStorage.getItem('dashboardReports')
) || [];

confirmReport.innerHTML = '';

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

card.append(
  image,
  title,
  id,
  description,
  category,
  location,
  date,
  type
);

confirmReport.appendChild(card);

});
}

displayConfirmedReports();