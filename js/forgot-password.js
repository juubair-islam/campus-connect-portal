document.addEventListener("DOMContentLoaded", () => {
  const userIdInput = document.getElementById("userId");
  const userContactInput = document.getElementById("userContact");
  const verifyBtn = document.getElementById("verifyBtn");
  const newPasswordSection = document.getElementById("newPasswordSection");
  const newPasswordInput = document.getElementById("newPassword");
  const confirmPasswordInput = document.getElementById("confirmPassword");
  const resetPasswordBtn = document.getElementById("resetPasswordBtn");
  const messageDiv = document.getElementById("message");

  let verifiedUser = null; // Store user info after verification

  // Helper to show messages
  function showMessage(text, type = "error") {
    messageDiv.textContent = text;
    messageDiv.className = `message ${type}`;
  }

  // Clear messages
  function clearMessage() {
    messageDiv.textContent = "";
    messageDiv.className = "message";
  }

  // Step 1: Verify ID + Contact
  verifyBtn.addEventListener("click", async () => {
    clearMessage();

    const userId = userIdInput.value.trim();
    const userContact = userContactInput.value.trim();

    if (!userId || !userContact) {
      showMessage("⚠️ Please fill in both fields.");
      return;
    }

    showMessage("🔄 Verifying...", "success");

    try {
      const res = await fetch("php/forgot-password.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "verify", userId, userContact }),
      });

      const data = await res.json();

      if (res.ok && data.success) {
        showMessage("✅ Verification successful! Please enter your new password.", "success");
        newPasswordSection.style.display = "block";
        verifiedUser = data.user; // user info for next step
        verifyBtn.disabled = true;
        userIdInput.disabled = true;
        userContactInput.disabled = true;
      } else {
        showMessage(data.error || "Verification failed.");
      }
    } catch (error) {
      showMessage("Server error: " + error.message);
    }
  });

  // Step 2: Reset Password
  resetPasswordBtn.addEventListener("click", async () => {
    clearMessage();

    if (!verifiedUser) {
      showMessage("⚠️ Please verify your ID and contact first.");
      return;
    }

    const newPass = newPasswordInput.value;
    const confirmPass = confirmPasswordInput.value;

    if (!newPass || !confirmPass) {
      showMessage("⚠️ Please enter and confirm your new password.");
      return;
    }

    if (newPass !== confirmPass) {
      showMessage("⚠️ Passwords do not match.");
      return;
    }

    try {
      const res = await fetch("php/forgot-password.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "reset",
          userId: verifiedUser.userId,
          role: verifiedUser.role,
          newPassword: newPass,
        }),
      });

      const data = await res.json();

      if (res.ok && data.success) {
        showMessage("🎉 Password reset successful! You can now log in.", "success");
        resetPasswordBtn.disabled = true;
        newPasswordInput.disabled = true;
        confirmPasswordInput.disabled = true;
      } else {
        showMessage(data.error || "Password reset failed.");
      }
    } catch (error) {
      showMessage("Server error: " + error.message);
    }
  });
});
