(function () {
const claimsContainer = document.getElementById('claims-container');

async function loadClaims() {
  if (!claimsContainer) return;

  try {
    const res = await fetch('/api/claims.php?status=all');
    if (!res.ok) {
      claimsContainer.innerHTML = '<p>Unable to load claim requests.</p>';
      return;
    }

    const result = await res.json();
    const claims = result.success ? result.data : [];

    claimsContainer.innerHTML = '';

    if (claims.length === 0) {
      claimsContainer.innerHTML = '<p>No claim requests found in database.</p>';
      return;
    }

    claims.forEach(claim => {
      const card = document.createElement('div');
      card.style.border = '1px solid rgba(255, 255, 255, 0.15)';
      card.style.borderRadius = '8px';
      card.style.padding = '15px';
      card.style.marginBottom = '15px';

      const title = document.createElement('h3');
      title.textContent = `${claim.claim_id} - ${claim.item_name || 'Item'}`;

      const claimant = document.createElement('p');
      claimant.textContent = `Claimant: ${claim.full_name} (${claim.email}) - ID: ${claim.school_id}`;

      const reportCode = document.createElement('p');
      reportCode.textContent = `Report Code: ${claim.report_code || 'N/A'}`;

      const status = document.createElement('p');
      status.innerHTML = `<strong>Status:</strong> ${claim.status.toUpperCase()}`;

      const feedback = document.createElement('p');
      feedback.textContent = `Feedback: ${claim.staff_feedback || 'None'}`;

      const dateP = document.createElement('p');
      dateP.textContent = `Date: ${claim.created_at}`;

      const actionDiv = document.createElement('div');
      actionDiv.style.marginTop = '10px';
      actionDiv.style.display = 'flex';
      actionDiv.style.gap = '10px';

      const approveBtn = document.createElement('button');
      approveBtn.type = 'button';
      approveBtn.textContent = 'Approve';
      approveBtn.onclick = async () => {
        const msg = prompt('Enter staff feedback for claimant:', 'Visit the Lost and Found Office to collect the item.');
        if (msg !== null) {
          await updateClaimStatus(claim.id, 'approved', msg);
        }
      };

      const rejectBtn = document.createElement('button');
      rejectBtn.type = 'button';
      rejectBtn.textContent = 'Reject';
      rejectBtn.onclick = async () => {
        const msg = prompt('Enter rejection reason for claimant:', 'Verification failed. Details did not match.');
        if (msg !== null) {
          await updateClaimStatus(claim.id, 'rejected', msg);
        }
      };

      const completeBtn = document.createElement('button');
      completeBtn.type = 'button';
      completeBtn.textContent = 'Mark Completed';
      completeBtn.onclick = async () => {
        await updateClaimStatus(claim.id, 'completed', 'Item collected by owner.');
      };

      actionDiv.append(approveBtn, rejectBtn, completeBtn);
      card.append(title, claimant, reportCode, status, feedback, dateP, actionDiv);
      claimsContainer.appendChild(card);
    });

  } catch (err) {
    claimsContainer.innerHTML = '<p>Error loading claims from server.</p>';
  }
}

async function updateClaimStatus(id, status, feedback) {
  try {
    const res = await fetch('/api/claims.php', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: id,
        status: status,
        staff_feedback: feedback
      })
    });
    const result = await res.json();
    if (res.ok && result.success) {
      alert(`Claim request updated to ${status}.`);
      loadClaims();
    } else {
      alert(result.error || 'Failed to update claim.');
    }
  } catch (e) {
    alert('Network error updating claim status.');
  }
}

document.addEventListener('DOMContentLoaded', loadClaims);
loadClaims();
})();
