(() => {
    "use strict";

    const loginForm = document.getElementById("login-form");
    const registerForm = document.getElementById("register-form");
    const loginTab = document.getElementById("login-tab");
    const registerTab = document.getElementById("register-tab");
    const csrfToken = document.body.dataset.csrfToken;

    function setMode(mode) {
        const isLogin = mode === "login";
        loginForm.hidden = !isLogin;
        registerForm.hidden = isLogin;
        loginTab.classList.toggle("auth-tab-active", isLogin);
        registerTab.classList.toggle("auth-tab-active", !isLogin);
        loginTab.setAttribute("aria-selected", String(isLogin));
        registerTab.setAttribute("aria-selected", String(!isLogin));
        document.getElementById("login-title").textContent = isLogin ? "Welcome back" : "Create your account";
    }

    async function submitForm(form, action, values) {
        const error = form.querySelector('[role="alert"]');
        const submit = form.querySelector('[type="submit"]');
        error.hidden = true;
        submit.disabled = true;
        try {
            const response = await fetch(`api.php?action=${action}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify(values)
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || "Unable to complete your request.");
            window.location.replace("dashboard.php");
        } catch (exception) {
            error.textContent = exception.message;
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    }

    loginTab.addEventListener("click", () => setMode("login"));
    registerTab.addEventListener("click", () => setMode("register"));
    loginForm.addEventListener("submit", event => {
        event.preventDefault();
        submitForm(loginForm, "login", {
            email: loginForm.elements.email.value.trim(),
            password: loginForm.elements.password.value
        });
    });
    registerForm.addEventListener("submit", event => {
        event.preventDefault();
        submitForm(registerForm, "register", {
            name: registerForm.elements.name.value.trim(),
            email: registerForm.elements.email.value.trim(),
            course: registerForm.elements.course.value.trim(),
            password: registerForm.elements.password.value
        });
    });
})();
