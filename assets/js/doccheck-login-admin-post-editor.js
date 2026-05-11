document.addEventListener("DOMContentLoaded", function () {
    var checkbox = document.getElementById("docacc_protected");
    var restriction = document.getElementById("docacc-role-restriction");

    if (!checkbox || !restriction) {
        return;
    }

    checkbox.addEventListener("change", function () {
        restriction.style.display = this.checked ? "" : "none";
    });
});
