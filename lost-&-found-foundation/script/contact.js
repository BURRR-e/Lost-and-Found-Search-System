const contactForm = document.querySelector('form');

if (contactForm) {
  contactForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value.trim();

    if (!name || !email || !subject || !message) {
      alert('Please fill in all required fields.');
      return;
    }

    try {
      const res = await fetch('/api/inquiries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, subject, message })
      });

      const result = await res.json();

      if (res.ok && result.success) {
        alert(result.message || 'Thank you! Your inquiry has been sent.');
        contactForm.reset();
      } else {
        alert(result.error || 'Failed to send inquiry.');
      }
    } catch (err) {
      alert('Network error sending inquiry. Please try again.');
    }
  });
}
