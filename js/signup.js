document.addEventListener("DOMContentLoaded", () => {
  const roleRadios = document.querySelectorAll('input[name="role"]');
  const studentFields = document.getElementById("studentFields");
  const facultyFields = document.getElementById("facultyFields");

  // Student inputs
  const iubIdInput = document.getElementById("iub_id");
  const nameInput = document.getElementById("name");
  const departmentInput = document.getElementById("department");
  const majorInput = document.getElementById("major");
  const minorInput = document.getElementById("minor");
  const emailInput = document.getElementById("email");
  const contactInput = document.getElementById("contact_number");
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirm_password");
  const passwordMatchMsg = document.getElementById("passwordMatchMsg");

  // Faculty inputs
  const fullNameInput = document.getElementById("full_name");
  const employeeIdInput = document.getElementById("employee_id");
  const facultyDepartmentSelect = document.getElementById("department_faculty");
  const facultyEmailInput = document.getElementById("iub_email");
  const facultyContactInput = document.getElementById("contact_number_faculty");
  const facultyPasswordInput = document.getElementById("password_faculty");
  const facultyConfirmPasswordInput = document.getElementById("confirm_password_faculty");
  const facultyPasswordMatchMsg = document.getElementById("passwordMatchMsgFaculty");

  const loadStudentBtn = document.getElementById("loadStudentBtn");
  const form = document.getElementById("signupForm");
  const formStatus = document.getElementById("formStatus");
  const successMessage = document.getElementById("successMessage");

  function toggleRoleFields() {
    const selectedRole = document.querySelector('input[name="role"]:checked').value;
    if (selectedRole === "student") {
      studentFields.style.display = "block";
      facultyFields.style.display = "none";
      clearFacultyFields();
    } else {
      studentFields.style.display = "none";
      facultyFields.style.display = "block";
      clearStudentFields();
    }
    clearMessages();
  }

  function clearMessages() {
    formStatus.textContent = "";
    successMessage.style.display = "none";
    passwordMatchMsg.textContent = "";
    facultyPasswordMatchMsg.textContent = "";
  }

  function clearStudentFields() {
    nameInput.value = "";
    departmentInput.value = "";
    majorInput.value = "";
    minorInput.value = "";
    emailInput.value = "";
    contactInput.value = "";
    passwordInput.value = "";
    confirmPasswordInput.value = "";
  }

  function clearFacultyFields() {
    fullNameInput.value = "";
    employeeIdInput.value = "";
    facultyDepartmentSelect.value = "";
    facultyEmailInput.value = "";
    facultyContactInput.value = "";
    facultyPasswordInput.value = "";
    facultyConfirmPasswordInput.value = "";
  }

  confirmPasswordInput.addEventListener("input", () => {
    passwordMatchMsg.textContent =
      passwordInput.value !== confirmPasswordInput.value ? "Passwords do not match." : "";
  });

  facultyConfirmPasswordInput.addEventListener("input", () => {
    facultyPasswordMatchMsg.textContent =
      facultyPasswordInput.value !== facultyConfirmPasswordInput.value ? "Passwords do not match." : "";
  });

  // You can implement IUB API fetching on loadStudentBtn click here (optional)
  loadStudentBtn.addEventListener("click", () => {
    // Implement your IUB student data fetch if you want here
    alert("IUB API fetch not implemented, fill manually or implement fetch.");
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearMessages();

    const role = document.querySelector('input[name="role"]:checked').value;

    if (role === "student") {
      if (
        !iubIdInput.value.trim() ||
        !nameInput.value.trim() ||
        !emailInput.value.trim() ||
        !passwordInput.value ||
        !confirmPasswordInput.value
      ) {
        formStatus.textContent = "⚠️ Please fill all required fields.";
        return;
      }
      if (passwordInput.value !== confirmPasswordInput.value) {
        formStatus.textContent = "⚠️ Passwords do not match.";
        return;
      }
    } else {
      if (
        !fullNameInput.value.trim() ||
        !employeeIdInput.value.trim() ||
        !facultyDepartmentSelect.value ||
        !facultyEmailInput.value.trim() ||
        !facultyPasswordInput.value ||
        !facultyConfirmPasswordInput.value
      ) {
        formStatus.textContent = "⚠️ Please fill all required fields for Administrative Staff.";
        return;
      }
      if (facultyPasswordInput.value !== facultyConfirmPasswordInput.value) {
        formStatus.textContent = "⚠️ Passwords do not match.";
        return;
      }
    }

    let dataToSend = {};

    if (role === "student") {
      dataToSend = {
        role: "student",
        iub_id: iubIdInput.value.trim(),
        name: nameInput.value.trim(),
        department: departmentInput.value.trim(),
        major: majorInput.value.trim(),
        minor: minorInput.value.trim(),
        email: emailInput.value.trim(),
        contact_number: contactInput.value.trim(),
        password: passwordInput.value,
      };
    } else {
      dataToSend = {
        role: "administrative_staff",
        full_name: fullNameInput.value.trim(),
        employee_id: employeeIdInput.value.trim(),
        department: facultyDepartmentSelect.value,
        iub_email: facultyEmailInput.value.trim(),
        contact_number: facultyContactInput.value.trim(),
        password: facultyPasswordInput.value,
      };
    }

    try {
      const res = await fetch("php/signup.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dataToSend),
      });
      const result = await res.json();

        if (res.ok && result.success) {
          const userName = (role === "student") ? dataToSend.name : dataToSend.full_name;
          successMessage.style.display = "block";
          successMessage.textContent = `🎉 Signup successful! Welcome, ${userName}! Redirecting to login...`;
          form.reset();
          clearStudentFields();
          clearFacultyFields();

          // Redirect after 3 seconds (3000 ms)
          setTimeout(() => {
            window.location.href = "login.html";
          }, 3000);
        }
        else {
        formStatus.textContent = result.error || "Signup failed. Try again.";
      }
    } catch (err) {
      formStatus.textContent = "Signup error: " + err.message;
    }
  });

  toggleRoleFields();
  roleRadios.forEach((radio) => radio.addEventListener("change", toggleRoleFields));
});
