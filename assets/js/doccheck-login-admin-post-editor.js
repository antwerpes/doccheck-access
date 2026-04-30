document.addEventListener("DOMContentLoaded", function () {
    var checkbox = document.getElementById("doccheck_protected");
    var restriction = document.getElementById("doccheck-role-restriction");

    if (!checkbox || !restriction) {
        return;
    }

    checkbox.addEventListener("change", function () {
        restriction.style.display = this.checked ? "" : "none";
    });
});
