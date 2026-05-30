document.addEventListener("DOMContentLoaded", function () {

    const typeField = document.getElementById("type");
    const customerField = document.getElementById("customerField");
    const statusField = document.getElementById("statusField");
    const paymentField = document.getElementById("paymentField");

    function toggleReplenish() {

        if (!typeField) return; // ❗ safety check

        if (typeField.value === "replenish") {

            if (customerField) customerField.disabled = true;
            if (statusField) statusField.disabled = true;
            if (paymentField) paymentField.disabled = true;

            if (statusField) statusField.value = "completed";
            if (paymentField) paymentField.value = "paid";

        } else {

            if (customerField) customerField.disabled = false;
            if (statusField) statusField.disabled = false;
            if (paymentField) paymentField.disabled = false;
        }
    }

    if (typeField) {
        typeField.addEventListener("change", toggleReplenish);
        toggleReplenish();
    }

});