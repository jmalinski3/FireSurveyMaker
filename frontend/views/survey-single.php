<?php
defined( 'ABSPATH' ) || exit;
get_header();
$survey = $GLOBALS['fsm_current_survey'] ?? null;
if ( ! $survey ) {
	wp_die( esc_html__( 'Survey not found.', 'fire-survey-maker' ), '', array( 'response' => 404 ) );
}
$questions = FSM_Question::get_by_survey( (int) $survey['id'] );
?>
<main class="fsm-page fsm-single">
	<div class="fsm-container">
		<?php include FSM_PLUGIN_DIR . 'frontend/views/survey-embed.php'; ?>
	</div>
</main>
<?php get_footer(); ?>
