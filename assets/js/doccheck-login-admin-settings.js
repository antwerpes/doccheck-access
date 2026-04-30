jQuery(document).ready(function ($) {
    var copiedText = (window.doccheckAccessAdmin && window.doccheckAccessAdmin.copiedText) ? window.doccheckAccessAdmin.copiedText : "Copied!";

    $(".nav-tab-wrapper a").on("click", function (e) {
        e.preventDefault();

        var tabId = $(this).attr("href");
        var tabName = $(this).data("tab");

        $(".tab-content").hide();
        $(".nav-tab").removeClass("nav-tab-active");
        $(tabId).show();
        $(this).addClass("nav-tab-active");

        if (history.pushState) {
            var url = new URL(window.location.href);
            url.searchParams.set("tab", tabName);
            window.history.pushState({ path: url.href }, "", url.href);
        }
    });

    function toggleConditionalFields() {
        var $authMode = $("#authentication_mode");
        if (!$authMode.length) {
            return;
        }

        var authMode = $authMode.val();
        if (authMode === "wordpress_user") {
            $(".default-role-field, .scope-property-field").closest("tr").show();
        } else {
            $(".default-role-field, .scope-property-field").closest("tr").hide();
            $(".doccheck-matrix-table input[type=\"checkbox\"]").each(function () {
                var $checkbox = $(this);
                if (($checkbox.attr("name") || "").indexOf("[unique_id]") === -1) {
                    $checkbox.prop("checked", false);
                }
            });
        }
    }

    $(".scope-checkbox input[type=checkbox]").on("change", function () {
        var $row = $(this).closest("tr");
        var isChecked = $(this).is(":checked");

        if (isChecked) {
            $row.find(".property-list").removeClass("disabled");
            $row.find(".property-list input[type=checkbox]").prop("disabled", false);
        } else {
            $row.find(".property-list").addClass("disabled");
            $row.find(".property-list input[type=checkbox]").prop("disabled", true);
        }
    });

    if ($(".scope-row input[type=checkbox]").length) {
        $(".scope-row input[type=checkbox]").trigger("change");
    }

    toggleConditionalFields();
    $("#authentication_mode").on("change", toggleConditionalFields);

    $("#copy-redirect-uri").on("click", function () {
        var copyText = document.getElementById("doccheck_redirect_uri");
        if (!copyText) {
            return;
        }

        copyText.select();
        copyText.setSelectionRange(0, 99999);

        var $btn = $("#copy-redirect-uri");
        var originalText = $btn.text();

        try {
            navigator.clipboard.writeText(copyText.value).then(function () {
                $btn.text(copiedText);
                setTimeout(function () {
                    $btn.text(originalText);
                }, 2000);
            });
        } catch (err) {
            document.execCommand("copy");
            $btn.text(copiedText);
            setTimeout(function () {
                $btn.text(originalText);
            }, 2000);
        }
    });
});
