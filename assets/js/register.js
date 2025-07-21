document.addEventListener("DOMContentLoaded", () => {
  const roleButtons = document.querySelectorAll(".role-toggle button");
  const roleInput = document.querySelector('input[name="role"]');
  const classRow = document.getElementById("class-row");
  const dobRow = document.getElementById("dob-row");
  const parentExtra = document.getElementById("parent-extra");
  const secretCodeRow = document.getElementById("secret-code-row");
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

  const updateFieldRequirements = (role) => {
    if (role === "Student") {
      classRow.style.display = "";
      dobRow.style.display = "";
      parentExtra.style.display = "none";
      secretCodeRow.style.display = "none";

      classRow.querySelector("select").setAttribute("required", "required");
      dobRow.querySelector("input").setAttribute("required", "required");
      parentExtra
        .querySelectorAll("input")
        .forEach((input) => input.removeAttribute("required"));
      secretCodeRow.querySelector("input").removeAttribute("required");
    } else if (role === "Parent") {
      classRow.style.display = "none";
      dobRow.style.display = "none";
      parentExtra.style.display = "";
      secretCodeRow.style.display = "none";

      parentExtra
        .querySelectorAll("input")
        .forEach((input) => input.setAttribute("required", "required"));
      classRow.querySelector("select").removeAttribute("required");
      dobRow.querySelector("input").removeAttribute("required");
      secretCodeRow.querySelector("input").removeAttribute("required");
    } else if (role === "Teacher" || role === "Admin") {
      classRow.style.display = "none";
      dobRow.style.display = "none";
      parentExtra.style.display = "none";
      secretCodeRow.style.display = "";

      classRow.querySelector("select").removeAttribute("required");
      dobRow.querySelector("input").removeAttribute("required");
      parentExtra
        .querySelectorAll("input")
        .forEach((input) => input.removeAttribute("required"));
      secretCodeRow.querySelector("input").setAttribute("required", "required");
    } else {
      classRow.style.display = "none";
      dobRow.style.display = "none";
      parentExtra.style.display = "none";
      secretCodeRow.style.display = "none";

      classRow.querySelector("select").removeAttribute("required");
      dobRow.querySelector("input").removeAttribute("required");
      parentExtra
        .querySelectorAll("input")
        .forEach((input) => input.removeAttribute("required"));
      secretCodeRow.querySelector("input").removeAttribute("required");
    }
  };

  function debounce(fn, delay) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn.apply(this, args), delay);
  };
}

passwordInput.addEventListener(
  "input",
  debounce(() => {
    const password = passwordInput.value;
    const strength = evaluateStrength(password);
    strengthText.textContent = `Password Strength: ${strength}`;
    strengthText.style.color =
      strength === "Strong"
        ? "green"
        : strength === "Medium"
        ? "orange"
        : "red";
  }, 200)
);


  roleButtons.forEach((button) => {
    button.addEventListener("click", () => {
      roleButtons.forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");

      const selectedRole = button.textContent.trim();
      roleInput.value = selectedRole;

      updateFieldRequirements(selectedRole);
    });
  });

  // Run on page load to reflect preselected role
  updateFieldRequirements(roleInput.value);
});
