async function loadUserClaims() {
  const claimsContainer = document.querySelector('body > div:nth-of-type(2)');
  if (!claimsContainer) return;

  try {
    const res = await fetch('/api/claims.php?email=user@school.edu');
    if (!res.ok) return;

    const result = await res.json();
    const claims = result.success ? result.data : [];
    const heading = claimsContainer.querySelector('h2');
    claimsContainer.innerHTML = '';
    if (heading) claimsContainer.appendChild(heading);

    if (claims.length === 0) {
      const p = document.createElement('p');
      p.textContent = 'No claim requests found.';
      p.style.padding = '15px';
      claimsContainer.appendChild(p);
      return;
    }

    const images = {
      'school-supplies': '/assets/category/school-supplies.png',
      'electronics': '/assets/category/electronics.png',
      'personal-belongings': '/assets/category/personal-belongings.png',
      'clothing': '/assets/category/clothes.png',
      'others': '/assets/category/others.png'
    };

    claims.forEach((claim, idx) => {
      const box = document.createElement('div');
      box.className = idx % 2 === 0 ? 'box1' : 'box2';
      box.style.border = '1px solid rgba(255, 255, 255, 0.1)';
      box.style.padding = '15px';
      box.style.marginBottom = '15px';
      box.style.borderRadius = '8px';

      const img = document.createElement('img');
      const category = claim.category || 'others';
      img.src = claim.photo_url || images[category] || images.others;
      img.alt = claim.item_name || 'Claim item';
      img.style.maxWidth = '100px';

      const title = document.createElement('h3');
      title.textContent = claim.item_name || 'Reported Item';

      const claimIdP = document.createElement('p');
      claimIdP.textContent = `Claim ID: ${claim.claim_id}`;

      const reportCodeP = document.createElement('p');
      reportCodeP.textContent = `Report Code: ${claim.report_code || 'N/A'}`;

      const dateP = document.createElement('p');
      dateP.textContent = `Date Requested: ${claim.created_at}`;

      const statusP = document.createElement('p');
      statusP.innerHTML = `<strong>Status:</strong> ${claim.status.toUpperCase()}`;

      const feedbackP = document.createElement('p');
      feedbackP.textContent = `Staff Feedback: ${claim.staff_feedback || 'Pending review.'}`;

      box.append(img, title, claimIdP, reportCodeP, dateP, statusP, feedbackP);

      if (claim.status === 'pending') {
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.textContent = 'Cancel Request';
        cancelBtn.style.marginTop = '10px';

        cancelBtn.addEventListener('click', async () => {
          if (confirm(`Cancel claim request ${claim.claim_id}?`)) {
            try {
              const delRes = await fetch(`/api/claims.php?id=${claim.id}`, { method: 'DELETE' });
              if (delRes.ok) {
                loadUserClaims();
              } else {
                alert('Failed to cancel claim request.');
              }
            } catch (e) {
              alert('Error cancelling claim request.');
            }
          }
        });

        box.appendChild(cancelBtn);
      }

      claimsContainer.appendChild(box);
    });

  } catch (err) {
    console.error('Error loading claims:', err);
  }
}

document.addEventListener('DOMContentLoaded', loadUserClaims);
loadUserClaims();
