<?php
defined( 'ABSPATH' ) || exit;

class FSM_Activator {

	public static function activate(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( FSM_Database::get_schema() );
		update_option( 'fsm_db_version', FSM_Database::DB_VERSION );
		FSM_Capabilities::grant_to_role( 'administrator' );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
