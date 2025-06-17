document.addEventListener("DOMContentLoaded", () => {
  const roleButtons = document.querySelectorAll(".role-toggle button");
  const roleInput = document.querySelector('input[name="role"]');
  const classRow = document.getElementById("class-row");
  const dobRow = document.getElementById("dob-row");
  const parentExtra = document.getElementById("parent-extra");
  const passwordInput = document.getElementById("password");
  const strengthText = document.getElementById("password-strength-text");

  const evaluateStrength = (password) => {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 2) return "Weak";
    if (score === 3 || score === 4) return "Medium";
    return "Strong";
  };

  passwordInput.addEventListener("input", () => {
    const password = passwordInput.value;
    const strength = evaluateStrength(password);
    strengthText.textContent = `Password Strength: ${strength}`;
    strengthText.style.color =
      strength === "Strong"
        ? "green"
        : strength === "Medium"
        ? "orange"
        : "red";
  });

  roleButtons.forEach((button) => {
    button.addEventListener("click", () => {
      roleButtons.forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      const selectedRole = button.textContent;
      roleInput.value = selectedRole;

      // Toggle relevant fields
      classRow.style.display = selectedRole === "Student" ? "" : "none";
      dobRow.style.display = selectedRole === "Student" ? "" : "none";
      parentExtra.style.display = selectedRole === "Parent" ? "" : "none";
    });
  });
});
