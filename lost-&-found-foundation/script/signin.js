const form = document.getElementById("questions");
const isAdminForm = form.dataset.admin === "true";

// Only allow same-site relative redirects (blocks ?next=//evil.com).
function safeNext() {
  const next = new URLSearchParams(window.location.search).get("next");
  return next && next.startsWith("/") && !next.startsWith("//") ? next : null;
}

form.addEventListener("submit", async function (event) {
  event.preventDefault();

  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;
  const button = document.getElementById("submit");

  button.disabled = true;
  try {
    const res = await fetch("/api/auth.php?action=login", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password, admin: isAdminForm })
    });
    const result = await res.json().catch(function () { return {}; });

    if (!res.ok || !result.success) {
      alert(result.error || "Could not sign in.");
      return;
    }

    if (result.data.role === "Admin") {
      window.location.href = safeNext() || "/admin/adminAccount.html";
    } else {
      window.location.href = safeNext() || "/page/home.html";
    }
  } catch (err) {
    alert("Network error. Please try again.");
  } finally {
    button.disabled = false;
  }
});
