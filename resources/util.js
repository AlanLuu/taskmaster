export function validateUsername(name) {
    const usernameMaxChars = 15;
    name = name.replace(/\s+/, ""); //Remove whitespace

    if (name.length === 0) {
        return "Username cannot be blank";
    }

    if (name.length > usernameMaxChars) {
        return `Username must be ${usernameMaxChars} characters or less`;
    }

    return "";
}
export function validatePassword(password) {
    const passwordMinChars = 2;

    if (password.length === 0) {
        return "Password cannot be blank";
    }
    
    if (password.length < passwordMinChars) {
        return `Password must be at least ${passwordMinChars} character${passwordMinChars !== 1 ? "s" : ""}`;
    }

    //Password regex taken from here: https://stackoverflow.com/a/21456918
    const passwordPattern = `^(?=.*[A-Za-z])(?=.*\\d)[A-Za-z\\d]{${passwordMinChars},}$`;
    const passwordRegex = new RegExp(passwordPattern);
    if (!passwordRegex.test(password)) {
        return "Password must contain at least 1 letter and 1 number";
    }

    return "";
}
export function validateEmail(email) {
    const emailRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    if (email.length > 0 && !emailRegex.test(email)) {
        return "Invalid email format";
    }
    return "";
}
export const validate = Object.freeze({
    username: validateUsername,
    password: validatePassword,
    email: validateEmail
});

/*
    Convenience function to send an HTTP request to a url
    If param is specified, then POST is used, otherwise GET
*/
export function sendRequest(url, param) {
    let paramStr;
    if (param) {
        paramStr = "";
        for (const [key, value] of Object.entries(param)) {
            paramStr += `${key}=${value}&`;
        }
        paramStr = paramStr.substring(0, paramStr.length - 1);
    }
    return fetch(url, param ? {
        method: "POST",
        headers: {
            "Content-type": "application/x-www-form-urlencoded"
        },
        body: paramStr
    } : null);
}

export function verifyCaptcha() {
    return Boolean(window.grecaptcha.getResponse());
}

export const getCookiesAsObject = () =>
    Object.fromEntries(document.cookie.split("; ").map(e => e.split("=")));

// export const getWebsiteName = () => getCookiesAsObject()["website_name"]
//     ?.replace(/%20/g, " ") ?? "Title goes here";

export const listArrItems = (arr) =>
    arr.toString().split(",").map((e, i) => (i !== arr.length - 1 ? `${e}, ` : `and ${e}`)).join("");

export const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
export const sleepSec = seconds => sleep(seconds * 1000);

export const CAPTCHA_ENABLED = Number(getCookiesAsObject()["captcha_enabled"]);