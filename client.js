jQuery(document).ready(function($) {
    var $select = $("#hc-snapshot");
    $select.change(function() {
        rcmail.http_post("plugin.hc_select_snapshot", {
            snapshot: $select.val()
        });
    });
});
rcmail.addEventListener('plugin.hc_select_snapshot_callback', function() {
    location.href = location.href;
});
