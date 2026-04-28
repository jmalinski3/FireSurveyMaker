<?php
defined( 'ABSPATH' ) || exit;

class FSM_Template {

	public static function register_rewrite_rules(): void {
		add_rewrite_rule( '^surveys/create/?$', 'index.php?fsm_create=1', 'top' );
		add_rewrite_rule( '^surveys/([^/]+)/results/?$', 'index.php?fsm_results=$matches[1]', 'top' );
		add_rewrite_rule( '^surveys/([^/]+)/?$', 'index.php?fsm_survey=$matches[1]', 'top' );
		add_rewrite_rule( '^surveys/?$', 'index.php?fsm_archive=1', 'top' );

		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'load_template' ) );
	}

	public static function add_query_vars( array $vars ): array {
		$vars[] = 'fsm_archive';
		$vars[] = 'fsm_create';
		$vars[] = 'fsm_survey';
		$vars[] = 'fsm_results';
		return $vars;
	}

	public static function load_template( string $template ): string {
		if ( get_query_var( 'fsm_archive' ) ) {
			return FSM_PLUGIN_DIR . 'frontend/views/survey-archive.php';
		}
		if ( get_query_var( 'fsm_create' ) ) {
			return FSM_PLUGIN_DIR . 'frontend/views/survey-create.php';
		}
		if ( $slug = get_query_var( 'fsm_results' ) ) {
			$GLOBALS['fsm_current_survey'] = FSM_Survey::get_by_slug( sanitize_title( $slug ) );
			return FSM_PLUGIN_DIR . 'frontend/views/survey-results.php';
		}
		if ( $slug = get_query_var( 'fsm_survey' ) ) {
			$GLOBALS['fsm_current_survey'] = FSM_Survey::get_by_slug( sanitize_title( $slug ) );
			return FSM_PLUGIN_DIR . 'frontend/views/survey-single.php';
		}
		return $template;
	}

	public static function survey_page_url( string $slug ): string {
		return home_url( '/surveys/' . $slug . '/' );
	}

	public static function results_page_url( string $slug ): string {
		return home_url( '/surveys/' . $slug . '/results/' );
	}
}
