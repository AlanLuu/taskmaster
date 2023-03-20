import { CAPTCHA_ENABLED, validate, verifyCaptcha } from "./util.js";

function formInputsValid(form) {
    const username = form['signupuser'].value.trim().replace(/[^A-Za-z0-9]/g, "");
    const pass = form['signuppass'].value;
    const pass2 = form['signuppass2'].value;
    const email = form['signupemail'].value.trim();

    const usernameError = validate.username(username);
    const passError = validate.password(pass);
    const confirmPass = pass === pass2;
    const emailError = validate.email(email);

    document.getElementById("infotextuser").textContent = usernameError;
    document.getElementById("infotextpass").textContent = passError;
    document.getElementById("infotextpass2").textContent =
        !confirmPass ? "Passwords do not match" : "";
    document.getElementById("infotextemail").textContent = emailError;

    return usernameError.length === 0 && passError.length === 0 && confirmPass && emailError.length === 0;
}

const [form] = document.getElementsByTagName("form");
form.addEventListener("submit", e => {
    e.preventDefault();
    const infoTextCaptcha = document.getElementById("infotextcaptcha");
    const validForm = formInputsValid(form);
    const validCaptcha = CAPTCHA_ENABLED ? verifyCaptcha() : true;
    if (validForm && validCaptcha) {
        if (infoTextCaptcha) infoTextCaptcha.textContent = "";
        form.submit();
    } else if (!validCaptcha && infoTextCaptcha) {
        infoTextCaptcha.textContent = "Captcha verification failed";
    }
});

/*
    Prevents the same entry from being inserted multiple times
    if the user refreshes the page
    https://stackoverflow.com/a/45656609
*/
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}