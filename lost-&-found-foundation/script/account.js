function setInfo(container, rows) {
  container.replaceChildren();
  rows.forEach(function (row) {
    const p = document.createElement('p');
    p.textContent = row[0] + ': ' + (row[1] == null ? '' : row[1]);
    container.appendChild(p);
  });
}

async function signOut() {
  try {
    await fetch('/api/auth.php?action=logout', { method: 'POST', credentials: 'same-origin' });
  } finally {
    window.location.href = '/page/signin.html';
  }
}

async function loadAccountData() {
  try {
    const res = await fetch('/api/account.php', { credentials: 'same-origin' });
    if (res.status === 401) {
      window.location.href = '/page/signin.html?next=/page/account.html';
      return;
    }
    if (!res.ok) return;

    const result = await res.json();
    if (!result.success) return;
    const data = result.data;

    const profileDiv = document.querySelector('body > div:nth-of-type(2)');
    const infoContainer = profileDiv && profileDiv.querySelector('div');
    if (infoContainer) {
      setInfo(infoContainer, [
        ['Full Name', data.full_name],
        ['Email Address', data.email],
        ['School ID', data.school_id],
        ['Role', data.role]
      ]);
    }

    const activityDiv = document.querySelector('body > div:nth-of-type(3)');
    if (activityDiv && data.activity) {
      const boxes = activityDiv.querySelectorAll('div');
      if (boxes[0]) boxes[0].querySelector('p').textContent = `${data.activity.lost_reports} reports submitted`;
      if (boxes[1]) boxes[1].querySelector('p').textContent = `${data.activity.found_reports} reports submitted`;
      if (boxes[2]) boxes[2].querySelector('p').textContent = `${data.activity.returned_items} items returned`;
      if (boxes[3]) boxes[3].querySelector('p').textContent = `${data.activity.claim_requests} claim requests submitted`;
    }

    const nameInput = document.getElementById('full-name');
    const emailInput = document.getElementById('account-email');
    if (nameInput) nameInput.value = data.full_name || '';
    if (emailInput) {
      emailInput.value = data.email || '';
      emailInput.readOnly = true;
      emailInput.title = 'Email cannot be changed';
    }
  } catch (err) {
    console.error('Error loading account data:', err);
  }
}

const profileForm = document.querySelector('#edit-profile form');
if (profileForm) {
  profileForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const fullName = document.getElementById('full-name').value.trim();
    if (!fullName) {
      alert('Please fill in Full Name.');
      return;
    }

    try {
      const res = await fetch('/api/account.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 'full-name': fullName })
      });
      const result = await res.json();
      if (res.ok && result.success) {
        alert('Profile updated successfully!');
        loadAccountData();
      } else {
        alert(result.error || 'Failed to update profile.');
      }
    } catch (err) {
      alert('Error updating profile.');
    }
  });
}

const signOutBtn = document.getElementById('sign-out-btn');
if (signOutBtn) signOutBtn.addEventListener('click', signOut);

const changePwBtn = document.getElementById('change-password-btn');
const pwForm = document.getElementById('password-form');
if (changePwBtn && pwForm) {
  changePwBtn.addEventListener('click', function () { pwForm.hidden = !pwForm.hidden; });

  pwForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const current = document.getElementById('current-password').value;
    const next = document.getElementById('new-password').value;
    const confirmPw = document.getElementById('confirm-password').value;

    if (next !== confirmPw) {
      alert('New passwords do not match.');
      return;
    }

    try {
      const res = await fetch('/api/auth.php?action=change_password', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ current_password: current, new_password: next })
      });
      const result = await res.json().catch(function () { return {}; });
      if (res.ok && result.success) {
        alert('Password updated.');
        pwForm.reset();
        pwForm.hidden = true;
      } else {
        alert(result.error || 'Could not update password.');
      }
    } catch (err) {
      alert('Error updating password.');
    }
  });
}

loadAccountData();
