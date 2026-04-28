<?php
defined( 'ABSPATH' ) || exit;
get_header();
$survey = $GLOBALS['fsm_current_survey'] ?? null;
if ( ! $survey ) {
	wp_die( esc_html__( 'Survey not found.', 'fire-survey-maker' ), '', array( 'response' => 404 ) );
}
?>
<main class="fsm-page fsm-results">
	<div class="fsm-container">
		<h1><?php echo esc_html( $survey['title'] ); ?></h1>
		<p>
			<a href="<?php echo esc_url( FSM_Template::survey_page_url( $survey['slug'] ) ); ?>">
				&larr; <?php esc_html_e( 'Back to survey', 'fire-survey-maker' ); ?>
			</a>
		</p>

		<div id="fsm-results-container"
			data-survey-id="<?php echo esc_attr( $survey['id'] ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'fsm/v1/' ) ); ?>">
			<p><?php esc_html_e( 'Loading results…', 'fire-survey-maker' ); ?></p>
		</div>
	</div>
</main>
<?php get_footer(); ?>
