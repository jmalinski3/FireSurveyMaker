<?php
defined( 'ABSPATH' ) || exit;
/**
 * Server-side render for the FireSurveyMaker block.
 * $attributes is provided by the block editor.
 */
$survey_id = (int) ( $attributes['surveyId'] ?? 0 );
if ( ! $survey_id ) {
	echo '<p>' . esc_html__( 'Please select a survey in the block settings.', 'fire-survey-maker' ) . '</p>';
	return;
}
echo FSM_Frontend::render_survey_embed( $survey_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
