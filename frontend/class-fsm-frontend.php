<?php
defined( 'ABSPATH' ) || exit;

class FSM_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets(): void {
		if ( ! $this->is_fsm_page() ) {
			return;
		}

		wp_enqueue_style( 'fsm-frontend', FSM_PLUGIN_URL . 'frontend/css/frontend.css', array(), FSM_VERSION );

		wp_enqueue_script(
			'fsm-survey-form',
			FSM_PLUGIN_URL . 'frontend/js/survey-form.js',
			array(),
			FSM_VERSION,
			true
		);

		wp_enqueue_script(
			'fsm-survey-builder-frontend',
			FSM_PLUGIN_URL . 'frontend/js/survey-builder-frontend.js',
			array(),
			FSM_VERSION,
			true
		);

		wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js', array(), '4', true );
		wp_enqueue_script(
			'fsm-survey-results',
			FSM_PLUGIN_URL . 'frontend/js/survey-results.js',
			array( 'chartjs' ),
			FSM_VERSION,
			true
		);

		wp_localize_script(
			'fsm-survey-form',
			'fsmFrontend',
			array(
				'restUrl'    => rest_url( 'fsm/v1/' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'loginUrl'   => wp_login_url( get_permalink() ),
				'isLoggedIn' => is_user_logged_in(),
				'canManage'  => FSM_Capabilities::current_user_can(),
				'i18n'       => array(
					'submitting'       => __( 'Submitting…', 'fire-survey-maker' ),
					'thankYou'         => __( 'Thank you for your response!', 'fire-survey-maker' ),
					'alreadyResponded' => __( 'You have already responded to this survey.', 'fire-survey-maker' ),
					'loginRequired'    => __( 'You must be logged in to respond.', 'fire-survey-maker' ),
					'surveyClosedMsg'  => __( 'This survey is not currently accepting responses.', 'fire-survey-maker' ),
					'error'            => __( 'An error occurred. Please try again.', 'fire-survey-maker' ),
				),
			)
		);

		wp_localize_script(
			'fsm-survey-builder-frontend',
			'fsmBuilder',
			array(
				'restUrl'  => rest_url( 'fsm/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'siteUrl'  => home_url( '/' ),
				'i18n'     => array(
					'saving'      => __( 'Saving…', 'fire-survey-maker' ),
					'saved'       => __( 'Survey created! Redirecting…', 'fire-survey-maker' ),
					'error'       => __( 'An error occurred. Please try again.', 'fire-survey-maker' ),
					'titleRequired' => __( 'Title is required.', 'fire-survey-maker' ),
				),
			)
		);
	}

	private function is_fsm_page(): bool {
		return (bool) (
			get_query_var( 'fsm_archive' ) ||
			get_query_var( 'fsm_create' ) ||
			get_query_var( 'fsm_survey' ) ||
			get_query_var( 'fsm_results' )
		);
	}

	public static function render_survey_embed( int $survey_id ): string {
		$survey = FSM_Survey::get( $survey_id );
		if ( ! $survey ) {
			return '<p>' . esc_html__( 'Survey not found.', 'fire-survey-maker' ) . '</p>';
		}
		$questions = FSM_Question::get_by_survey( $survey_id );
		ob_start();
		include FSM_PLUGIN_DIR . 'frontend/views/survey-embed.php';
		return ob_get_clean();
	}
}
