<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'fsm_answers',
	$wpdb->prefix . 'fsm_responses',
	$wpdb->prefix . 'fsm_question_options',
	$wpdb->prefix . 'fsm_questions',
	$wpdb->prefix . 'fsm_surveys',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'fsm_db_version' );
delete_option( 'fsm_roles_with_cap' );
