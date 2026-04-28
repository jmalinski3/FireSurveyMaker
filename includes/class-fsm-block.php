<?php
defined( 'ABSPATH' ) || exit;

class FSM_Block {

	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type( FSM_PLUGIN_DIR . 'block/block.json' );
	}
}
