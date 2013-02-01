<script type="text/javascript">
    // si jQuery est chargé
    if (typeof(jQuery) != "undefined") {
        jQuery('body').delegate('#clementine_users_add', 'submit', function() {
            var formname = jQuery(this).attr('id');
            jQuery.ajax({
                async: 1, 
                type: "post", 
                url: jQuery('#' + formname).attr('action'),
                data: jQuery('#' + formname).serialize(),
                success: function(msg) {
                    var retval = msg.substring(0, 1);
                    if (retval == '1') {
                        alert(msg.substring(1));
                    } else if (retval == '2') {
                        document.location = msg.substring(1);
                    } else {
                        alert(msg);
                    }
                }
            });
            return false;
        });
    }
</script>
