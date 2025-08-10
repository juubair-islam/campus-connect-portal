document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm");
  const loginStatus = document.getElementById("loginStatus");
  const forgotPasswordLink = document.getElementById("forgotPasswordLink");

  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    loginStatus.textContent = "";

    const loginId = document.getElementById("loginId").value.trim();
    const password = document.getElementById("loginPassword").value;
    const role = document.querySelector('input[name="role"]:checked').value;

    if (!loginId || !password) {
      loginStatus.textContent = "⚠️ Please enter your ID and password.";
      return;
    }

    // Admin fixed login
    if (role === "admin") {
      if (loginId === "001" && password === "102019") {
        loginStatus.style.color = "green";
        loginStatus.textContent = "Welcome Admin! Redirecting...";
        // Redirect to admin panel
        setTimeout(() => {
          window.location.href = "admin-panel.html"; // Change this path accordingly
        }, 1000);
      } else {
        loginStatus.style.color = "crimson";
        loginStatus.textContent = "Invalid Admin ID or password.";
      }
      return;
    }

    // For student and administrative_staff, check via PHP backend (you'll need to create login.php)
    try {
      const res = await fetch('php/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ role, loginId, password })
      });

      const result = await res.json();

      if (res.ok && result.success) {
        loginStatus.style.color = "green";
        loginStatus.textContent = "Login successful! Redirecting...";
        // Redirect based on role
        if (role === "student") {
          setTimeout(() => window.location.href = "student-dashboard.php", 1000);
        } else if (role === "administrative_staff") {
          setTimeout(() => window.location.href = "staff-dashboard.html", 1000);
        }
      } else {
        loginStatus.style.color = "crimson";
        loginStatus.textContent = result.error || "Login failed. Check credentials.";
      }
    } catch (err) {
      loginStatus.style.color = "crimson";
      loginStatus.textContent = "Error: " + err.message;
    }
  });



});
