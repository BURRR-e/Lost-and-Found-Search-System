const form = document.getElementById("questions");
const isAdminForm = form.dataset.admin === "true";

form.addEventListener("submit", async function (event) {
  event.preventDefault();

  const payload = {
    name: document.getElementById("name").value.trim(),
    email: document.getElementById("email").value.trim().toLowerCase(),
    school_id: document.getElementById("schoolid").value.trim(),
    password: document.getElementById("password").value
  };

  const roleSelect = document.getElementById("role");
  if (roleSelect) payload.role = roleSelect.value;

  const invite = document.getElementById("invitecode");
  if (invite) payload.invite_code = invite.value;

  const confirm = document.getElementById("confirm");
  if (confirm && confirm.value !== payload.password) {
    alert("Passwords do not match.");
    return;
  }

  const button = document.getElementById("submit");
  button.disabled = true;
  try {
    const action = isAdminForm ? "register_admin" : "register";
    const res = await fetch("/api/auth.php?action=" + action, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const result = await res.json().catch(function () { return {}; });

    if (!res.ok || !result.success) {
      alert(result.error || "Could not create the account.");
      return;
    }

    alert("Account created successfully! Please sign in.");
    window.location.href = isAdminForm ? "admin-signin.html" : "signin.html";
  } catch (err) {
    alert("Network error. Please try again.");
  } finally {
    button.disabled = false;
  }
});
