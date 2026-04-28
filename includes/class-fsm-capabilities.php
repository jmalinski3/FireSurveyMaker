<?php
defined( 'ABSPATH' ) || exit;

class FSM_Capabilities {

	const CAP = 'manage_surveys';

	public static function grant_to_role( string $role_slug ): void {
		$role = get_role( $role_slug );
		if ( $role ) {
			$role->add_cap( self::CAP );
			$roles = get_option( 'fsm_roles_with_cap', array() );
			if ( ! in_array( $role_slug, $roles, true ) ) {
				$roles[] = $role_slug;
				update_option( 'fsm_roles_with_cap', $roles );
			}
		}
	}

	public static function revoke_from_role( string $role_slug ): void {
		if ( 'administrator' === $role_slug ) {
			return;
		}
		$role = get_role( $role_slug );
		if ( $role ) {
			$role->remove_cap( self::CAP );
			$roles = get_option( 'fsm_roles_with_cap', array() );
			$roles = array_values( array_filter( $roles, fn( $r ) => $r !== $role_slug ) );
			update_option( 'fsm_roles_with_cap', $roles );
		}
	}

	public static function current_user_can(): bool {
		return current_user_can( self::CAP );
	}
}
