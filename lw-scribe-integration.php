<?php
/*
 Plugin Name: LyntonWeb Scribe Integration
 Author: LyntonWeb
 Author URI: http://integratehubspot.com/
 Version: 1.1
 Plugin URI: 
 Description: Scribe Integration plugin developed by LyntonWeb. This plugin handles authentication for the integration user and the WordPress REST API, creating custom meta fields for use in the integration logic, setting values for those meta fields when entities are created or updated, and registering meta fields for the REST API to return.
 */

// AUTHENTICATION SECTION - BEGIN
function lw_scribe_auth_handler( $user ) {
	global $lw_scribe_auth_error;

	$lw_scribe_auth_error = null;

	if ( ! empty( $user ) ) {
		return $user;
	}

	if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
		return $user;
	}

	$username = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];

	remove_filter( 'determine_current_user', 'lw_scribe_auth_handler', 20 );

	$user = wp_authenticate( $username, $password );

	add_filter( 'determine_current_user', 'lw_scribe_auth_handler', 20 );

	if ( is_wp_error( $user ) ) {
		$lw_scribe_auth_error = $user;
		return null;
	}

	$lw_scribe_auth_error = true;

	return $user->ID;
}
add_filter( 'determine_current_user', 'lw_scribe_auth_handler', 20 );

function lw_scribe_auth_error( $error ) {
	if ( ! empty( $error ) ) {
		return $error;
	}

	global $lw_scribe_auth_error;

	return $lw_scribe_auth_error;
}
add_filter( 'rest_authentication_errors', 'lw_scribe_auth_error' );
// AUTHENTICATION SECTION - END



// CREATE INTEGRATION-SPECIFIC META FIELDS - BEGIN
add_action('activate_lw-scribe-integration/lw-scribe-integration.php', 'lw_scribe_create_meta');

function lw_scribe_create_meta() {
	$lw_scribe_time = strtotime( '01/01/2000' );
	$lw_scribe_date = date( 'Y-m-d', $lw_scribe_time );
	
	$users = get_users( array( 'fields' => array( 'ID' ) ) );
	foreach( $users as $user_id ) {
		$lw_scribe_usr_last_modified_meta = get_user_meta($user_id->ID, 'lw_scribe_usr_last_modified', true);
		if ( empty ( $lw_scribe_usr_last_modified_meta ) )
			add_user_meta( $user_id->ID, 'lw_scribe_usr_last_modified', $lw_scribe_date, true );
	
		$lw_scribe_usr_mod_meta = get_user_meta($user_id->ID, 'lw_scribe_usr_mod', true);
		if ( empty ( $lw_scribe_usr_mod_meta ) )
			add_user_meta( $user_id->ID, 'lw_scribe_usr_mod', 'false', true );

        $lw_scribe_ext_system_id = get_user_meta($user_id->ID, 'lw_scribe_ext_system_id', true);
        if ( empty ( $lw_scribe_ext_system_id ) )
            add_user_meta( $user_id->ID, 'lw_scribe_ext_system_id', '-99', true );
	}
}
// CREATE INTEGRATION-SPECIFIC META FIELDS - END

// CREATE INTEGRATION-SPECIFIC META FIELDS FOR NEW USER - BEGIN
add_action('user_register', 'lw_scribe_create_user_meta');

function lw_scribe_create_user_meta( $user_id ) {
	$lw_scribe_time = strtotime( '01/01/2000' );
	$lw_scribe_date = date( 'Y-m-d', $lw_scribe_time );

	$lw_scribe_usr_last_modified_meta = get_user_meta($user_id->ID, 'lw_scribe_usr_last_modified', true);
	if ( empty ( $lw_scribe_usr_last_modified_meta ) )
		add_user_meta( $user_id->ID, 'lw_scribe_usr_last_modified', $lw_scribe_date, true );

	$lw_scribe_usr_mod_meta = get_user_meta($user_id->ID, 'lw_scribe_usr_mod', true);
	if ( empty ( $lw_scribe_usr_mod_meta ) )
		add_user_meta( $user_id->ID, 'lw_scribe_usr_mod', 'false', true );

	$lw_scribe_ext_system_id = get_user_meta($user_id->ID, 'lw_scribe_ext_system_id', true);
	if ( empty ( $lw_scribe_ext_system_id ) )
		add_user_meta( $user_id->ID, 'lw_scribe_ext_system_id', '-99', true );
}
// CREATE INTEGRATION-SPECIFIC META FIELDS FOR NEW USER - END



// ACTION HANDLER FOR UPDATING INTEGRATION-SPECIFIC META FIELDS - BEGIN
add_action( 'profile_update', 'lw_scribe_user_modified_onupdate');
add_action( 'user_register', 'lw_scribe_user_modified_onupdate');

function lw_scribe_user_modified_onupdate( $user_id ) {	
	//update_user_meta( $user_id, 'lw_scribe_usr_last_modified', date( "Y-m-d H:i:s", generate_server_current_timestamp() ) );
    update_user_meta( $user_id, 'lw_scribe_usr_last_modified', date( "Y-m-d H:i:s" ) );
	update_user_meta( $user_id, 'lw_scribe_usr_mod', 'true' );
}
// ACTION HANDLER FOR UPDATING INTEGRATION-SPECIFIC META FIELDS - END



// REGISTER META FIELDS WITH REST API SECTION - BEGIN
add_action( 'rest_api_init', 'lw_scribe_register_rest_fields' );

function lw_scribe_register_rest_fields() {	
	global $wpdb;
	
    $lw_scribe_user_meta = $wpdb->get_results( "SELECT DISTINCT meta_key FROM wp_usermeta ORDER BY meta_key" );	
	
	foreach( $lw_scribe_user_meta as $lsum ) {
		register_meta( 'user', $lsum->meta_key, array('type' => 'string','single' => true,'show_in_rest' => true,) );
	}
}
// REGISTER META FIELDS WITH REST API SECTION - END


// REGISTER CUSTOM REST ROUTE GET ENDPOINTS FOR lw_scribe_usr_mod AND lw_scribe_usr_last_modified - BEGIN
add_action( 'rest_api_init', function () {
	
	register_rest_route(
		'lw_scribe_usr_last_modified/v1',
		'/users',
		array(
			'methods' => 'GET,POST',
			'callback' => 'lw_scribe_usr_last_modified',
			'permission_callback' => function () {
				return current_user_can( 'edit_users' );
			}
		)
	);

	register_rest_route(
		'lw_scribe_usr_last_modified/v1',
		'/user/(?P<id>\d+)',
		array(
			'methods' => 'GET,POST',
			'callback' => 'lw_scribe_usr_last_modified',
			'permission_callback' => function () {
				return current_user_can( 'edit_users' );
			}
		)
	);

    register_rest_route(
        'lw_scribe_usr_last_modified/v1',
        'timezone',
        array(
            'methods' => 'GET',
            'callback' => 'lw_scribe_usr_timezone',
            'permission_callback' => function () {
                return current_user_can( 'edit_users' );
            }
        )
    );
} );

function lw_scribe_usr_last_modified( WP_REST_Request $request ) {
	
	$lw_scribe_usr_last_modified = $request->get_param( 'lw_scribe_usr_last_modified' );
	$id = $request->get_param( 'id' );
	$email = $request->get_param( 'email' );
	$registered_date = $request->get_param( 'registered_date' );
	$lw_scribe_usr_mod = $request->get_param( 'lw_scribe_usr_mod' );
    $lw_scribe_ext_system_id = $request->get_param( 'lw_scribe_ext_system_id' );
    $meta_compare = $request->get_param( 'meta_compare' );

	$respObject = new stdClass();

	$filters = array( 'fields' => array( 'ID' ), 'orderby' => 'ID', 'order' => 'ASC' );
	if ( !is_null( $lw_scribe_usr_last_modified ) && $lw_scribe_usr_last_modified != '' ) {
        $lw_scribe_usr_last_modified = date( "Y-m-d H:i:s", strtotime( $lw_scribe_usr_last_modified ) );
        $filters['meta_key'] = 'lw_scribe_usr_last_modified';
        $filters['meta_compare'] = $meta_compare;
        $filters['meta_value'] = $lw_scribe_usr_last_modified;
	}
    if ( !is_null( $registered_date ) && $registered_date != '' ) {
        $registered_date = date( "Y-m-d H:i:s", strtotime( $registered_date ) );
        if ( !is_null( $meta_compare ) && $meta_compare != '' ) {
            if ( strpos( $meta_compare, '>=' ) !== false ) {
                $filters['date_query'] = ['after' => $registered_date, 'inclusive' => true];
            }
            else if ( strpos( $meta_compare, '>' ) !== false ) {
                $filters['date_query'] = ['after' => $registered_date, 'inclusive' => false];
            }
            else if ( strpos( $meta_compare, '<=' ) !== false ) {
                $filters['date_query'] = ['before' => $registered_date, 'inclusive' => true];
            }
            else if ( strpos( $meta_compare, '<' ) !== false ) {
                $filters['date_query'] = ['before' => $registered_date, 'inclusive' => false];
            }
            else {
                $filters['date_query'] = ['after' => $registered_date, 'inclusive' => false];
            }
        }
        else {
            $filters['date_query'] = ['after' => $registered_date, 'inclusive' => false];
        }
    }
    if ( !is_null( $email ) && $email != '' ) {
        $filters['search'] = $email;
    }
	if ( !is_null( $id ) && $id != '' ) {
        $filters['search'] = $id;
    }
    if ( !is_null( $lw_scribe_usr_mod ) && $lw_scribe_usr_mod != '' ) {
        $filters['meta_key'] = 'lw_scribe_usr_mod';
        $filters['meta_compare'] = '=';
        $filters['meta_value'] = $lw_scribe_usr_mod;
    }
    if ( !is_null( $lw_scribe_ext_system_id ) && $lw_scribe_ext_system_id != '' ) {
        $filters['meta_key'] = 'lw_scribe_ext_system_id';
        $filters['meta_compare'] = '=';
        $filters['meta_value'] = $lw_scribe_ext_system_id;
    }

    $filteredUsers = get_users( $filters );

	$userResps = array();
	foreach ( $filteredUsers as $user ) {
		$userResps[] = $user->ID;
	}

	$respObject->user_ids = $userResps;
	return $respObject;
}
// REGISTER CUSTOM REST ROUTE GET ENDPOINTS FOR lw_scribe_usr_mod AND lw_scribe_usr_last_modified - END


function lw_scribe_usr_timezone( WP_REST_Request $request ) {
    $respObject = new stdClass();

    return $respObject->timezone = get_option( 'gmt_offset' );
}