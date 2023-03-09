import Swal from "./lib/swal.min.js";
//import Swal from "https://cdn.jsdelivr.net/npm/sweetalert2@11.7.1/+esm";

export default class {
    static SUCCESS = "success";
    static ERROR = "error";
    static WARNING = "warning";
    static INFO = "info";
    static QUESTION = "question";

    static async alert(msg, icon, additionalSettings = {}) {
        const Alert = Swal.mixin({
            html: msg,
            icon: icon,
            allowOutsideClick: false,
            allowEscapeKey: false
        });
        await Alert.fire(additionalSettings);
    }

    static async bigAlert(msg, icon, additionalSettings = {}) {
        const BigAlert = Swal.mixin({
            title: msg,
            icon: icon,
            allowOutsideClick: false,
            allowEscapeKey: false
        });
        await BigAlert.fire(additionalSettings);
    }

    static async confirm(msg, additionalSettings = {}) {
        const Confirm = Swal.mixin({
            titleText: msg,
            icon: "question",
            allowOutsideClick: false,
            allowEscapeKey: false,
            showDenyButton: true,
            confirmButtonText: "Yes",
            confirmButtonColor: "#00AB66"
        });
        const result = await Confirm.fire(additionalSettings);
        return result.isConfirmed;
    }

    static async prompt(msg, {defaultValue = "", additionalSettings = {}} = {}) {
        const Prompt = Swal.mixin({
            titleText: msg,
            input: "text",
            showCancelButton: true,
            inputValue: defaultValue
        });
        const result = await Prompt.fire(additionalSettings);
        return result.value;
    }

    static async toast(msg, icon, ms, additionalSettings = {}) {
        const Toast = Swal.mixin({
            title: msg,
            icon: icon,
            toast: true,
            showConfirmButton: false,
            position: 'top-end',
            timer: ms,
            timerProgressBar: true
        });
        await Toast.fire(additionalSettings);
    }
}
export { default as Swal } from "./lib/swal.min.js";
//export { default as Swal } from "https://cdn.jsdelivr.net/npm/sweetalert2@11.7.1/+esm";