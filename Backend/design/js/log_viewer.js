$(function () {
    $(".fn_sviat_log_viewer_tab").on("click", function (e) {
        e.stopImmediatePropagation();
    });

    $(document).on("click", ".fn_log_viewer_delete_open", function () {
        var date = $(this).data("date") || "";
        $("#sviat_log_viewer_delete_date").val(date);
        $("#sviat_log_viewer_delete_label").text(date);
    });

    $(document).on("click", ".fn_sviat_log_viewer_toggle", function (e) {
        e.preventDefault();
        var $link = $(this);
        var $wrap = $($link.data("target"));
        var $label = $link.find(".fn_sviat_log_viewer_toggle_label");
        var opened = $wrap.toggleClass("is-open").hasClass("is-open");
        $label.addClass("is-switching");

        setTimeout(function () {
            $label.text(opened ? $link.data("text-hide") : $link.data("text-show"));
            requestAnimationFrame(function () {
                $label.removeClass("is-switching");
            });
        }, 90);
    });

    $(document).on("click", ".fn_sviat_log_viewer_copy", function () {
        var $btn = $(this);
        var target = $btn.data("target");
        var text = $(target).text();
        var defaultLabel = $btn.data("label-default");
        var copiedLabel = $btn.data("label-copied");

        var done = function () {
            var $label = $btn.find(".fn_sviat_log_viewer_copy_label");
            $label.text(copiedLabel);
            setTimeout(function () {
                $label.text(defaultLabel);
            }, 1200);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, done);
            return;
        }

        var ta = document.createElement("textarea");
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand("copy");
        } catch (e) {}
        document.body.removeChild(ta);
        done();
    });
});
