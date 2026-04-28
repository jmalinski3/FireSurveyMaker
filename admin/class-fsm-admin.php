<?php
defined( 'ABSPATH' ) || exit;

class FSM_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu_pages(): void {
		add_menu_page(
			__( 'FireSurveyMaker', 'fire-survey-maker' ),
			__( 'Surveys', 'fire-survey-maker' ),
			FSM_Capabilities::CAP,
			'fsm-surveys',
			array( $this, 'render_survey_list' ),
			'dashicons-feedback',
			30
		);

		add_submenu_page(
			'fsm-surveys',
			__( 'All Surveys', 'fire-survey-maker' ),
			__( 'All Surveys', 'fire-survey-maker' ),
			FSM_Capabilities::CAP,
			'fsm-surveys',
			array( $this, 'render_survey_list' )
		);

		add_submenu_page(
			'fsm-surveys',
			__( 'Add New Survey', 'fire-survey-maker' ),
			__( 'Add New', 'fire-survey-maker' ),
			FSM_Capabilities::CAP,
			'fsm-survey-new',
			array( $this, 'render_survey_edit' )
		);

		add_submenu_page(
			'fsm-surveys',
			__( 'Edit Survey', 'fire-survey-maker' ),
			'',
			FSM_Capabilities::CAP,
			'fsm-survey-edit',
			array( $this, 'render_survey_edit' )
		);

		add_submenu_page(
			'fsm-surveys',
			__( 'Survey Results', 'fire-survey-maker' ),
			'',
			FSM_Capabilities::CAP,
			'fsm-survey-results',
			array( $this, 'render_survey_results' )
		);

		add_submenu_page(
			'fsm-surveys',
			__( 'Settings', 'fire-survey-maker' ),
			__( 'Settings', 'fire-survey-maker' ),
			'manage_options',
			'fsm-settings',
			array( $this, 'render_settings' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		$fsm_hooks = array(
			'surveys_page_fsm-survey-new',
			'surveys_page_fsm-survey-edit',
			'toplevel_page_fsm-surveys',
			'surveys_page_fsm-survey-results',
			'surveys_page_fsm-settings',
		);
		if ( ! in_array( $hook, $fsm_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'fsm-admin', FSM_PLUGIN_URL . 'admin/css/admin.css', array(), FSM_VERSION );
		wp_enqueue_script(
			'fsm-admin-builder',
			FSM_PLUGIN_URL . 'admin/js/admin-builder.js',
			array(),
			FSM_VERSION,
			true
		);
		wp_localize_script(
			'fsm-admin-builder',
			'fsmAdmin',
			array(
				'restUrl' => rest_url( 'fsm/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this survey and all its responses?', 'fire-survey-maker' ),
					'saving'        => __( 'Saving…', 'fire-survey-maker' ),
					'saved'         => __( 'Saved!', 'fire-survey-maker' ),
					'error'         => __( 'An error occurred. Please try again.', 'fire-survey-maker' ),
				),
			)
		);
	}

	public function render_survey_list(): void {
		$surveys = FSM_Survey::get_all();
		require FSM_PLUGIN_DIR . 'admin/views/survey-list.php';
	}

	public function render_survey_edit(): void {
		$survey    = null;
		$questions = array();
		$id        = isset( $_GET['survey_id'] ) ? (int) $_GET['survey_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id ) {
			$survey    = FSM_Survey::get( $id );
			$questions = FSM_Question::get_by_survey( $id );
		}
		require FSM_PLUGIN_DIR . 'admin/views/survey-edit.php';
	}

	public function render_survey_results(): void {
		$id     = isset( $_GET['survey_id'] ) ? (int) $_GET['survey_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$survey = $id ? FSM_Survey::get( $id ) : null;
		require FSM_PLUGIN_DIR . 'admin/views/survey-results.php';
	}

	public function render_settings(): void {
		global $wp_roles;
		if ( isset( $_POST['fsm_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fsm_settings_nonce'] ) ), 'fsm_settings' ) ) {
			$granted = get_option( 'fsm_roles_with_cap', array() );
			$all_roles = array_keys( $wp_roles->get_names() );
			foreach ( $all_roles as $role ) {
				if ( 'administrator' === $role ) {
					continue;
				}
				$checked = isset( $_POST['fsm_roles'][ $role ] );
				if ( $checked && ! in_array( $role, $granted, true ) ) {
					FSM_Capabilities::grant_to_role( $role );
				} elseif ( ! $checked && in_array( $role, $granted, true ) ) {
					FSM_Capabilities::revoke_from_role( $role );
				}
			}
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'fire-survey-maker' ) . '</p></div>';
		}

		$roles_with_cap = get_option( 'fsm_roles_with_cap', array() );
		$all_roles      = $wp_roles->get_names();
		require FSM_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
