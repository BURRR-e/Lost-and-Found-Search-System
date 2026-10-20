function getStoredUser() {
  try { return JSON.parse(localStorage.getItem('lf_user')) || {}; } catch (e) { return {}; }
}

function saveStoredUser(user) {
  try { localStorage.setItem('lf_user', JSON.stringify(user)); } catch (e) {}
}

async function loadAccountData() {
  try {
    const stored = getStoredUser();
    const res = await fetch('/api/account.php' + (stored.email ? '?email=' + encodeURIComponent(stored.email) : ''));
    if (!res.ok) return;

    const result = await res.json();
    if (!result.success) return;

    const data = result.data;
    saveStoredUser({ email: data.email, full_name: data.full_name, school_id: data.school_id });

    const profileDiv = document.querySelector('body > div:nth-of-type(2)');
    if (profileDiv) {
      const infoContainer = profileDiv.querySelector('div');
      if (infoContainer) {
        infoContainer.innerHTML = `
          <p>Full Name: ${data.full_name}</p>
          <p>Email Address: ${data.email}</p>
          <p>School ID: ${data.school_id}</p>
          <p>Role: ${data.role}</p>
        `;
      }
    }

    const activityDiv = document.querySelector('body > div:nth-of-type(3)');
    if (activityDiv && data.activity) {
      const boxes = activityDiv.querySelectorAll('div');
      if (boxes[0]) boxes[0].querySelector('p').textContent = `${data.activity.lost_reports} reports submitted`;
      if (boxes[1]) boxes[1].querySelector('p').textContent = `${data.activity.found_reports} reports submitted`;
      if (boxes[2]) boxes[2].querySelector('p').textContent = `${data.activity.claim_requests} claim requests submitted`;
    }

    const nameInput = document.getElementById('full-name');
    const emailInput = document.getElementById('account-email');
    if (nameInput) nameInput.value = data.full_name || '';
    if (emailInput) emailInput.value = data.email || '';

  } catch (err) {
    console.error('Error loading account data:', err);
  }
}

const profileForm = document.querySelector('#edit-profile form');
if (profileForm) {
  profileForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    const fullName = document.getElementById('full-name').value.trim();
    const email = document.getElementById('account-email').value.trim();

    if (!fullName || !email) {
      alert('Please fill in Full Name and Email Address.');
      return;
    }

    try {
      const res = await fetch('/api/account.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 'full-name': fullName, 'account-email': email })
      });

      const result = await res.json();
      if (res.ok && result.success) {
        saveStoredUser({ ...getStoredUser(), email, full_name: fullName });
        alert('Profile updated successfully!');
        loadAccountData();
      } else {
        alert(result.error || 'Failed to update profile.');
      }
    } catch (e) {
      alert('Error updating profile.');
    }
  });
}

document.addEventListener('DOMContentLoaded', loadAccountData);
loadAccountData();
