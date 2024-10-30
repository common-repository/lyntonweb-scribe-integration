<?php

lw_usr_meta_cleanup();

function lw_usr_meta_cleanup() {
    global $wpdb;

    $sql = "DELETE FROM wp_usermeta WHERE meta_key IN ('lw_scribe_usr_last_modified', 'lw_scribe_usr_mod', 'lw_scribe_ext_system_id')";
    $wpdb->query( $sql );
}