<?php
defined( 'ABSPATH' ) || exit;
get_header();

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( home_url( '/surveys/create/' ) ) );
	exit;
}

if ( ! FSM_Capabilities::current_user_can() ) {
	wp_die( esc_html__( 'You do not have permission to create surveys.', 'fire-survey-maker' ), '', array( 'response' => 403 ) );
}
?>
<main class="fsm-page fsm-create">
	<div class="fsm-container">
		<h1><?php esc_html_e( 'Create a New Survey', 'fire-survey-maker' ); ?></h1>

		<div id="fsm-frontend-builder">
			<div class="fsm-field-group">
				<label for="fsm-fe-title"><?php esc_html_e( 'Title', 'fire-survey-maker' ); ?> <span class="fsm-required">*</span></label>
				<input type="text" id="fsm-fe-title" class="fsm-input" required>
			</div>

			<div class="fsm-field-group">
				<label for="fsm-fe-description"><?php esc_html_e( 'Description', 'fire-survey-maker' ); ?></label>
				<textarea id="fsm-fe-description" class="fsm-input" rows="3"></textarea>
			</div>

			<div class="fsm-field-row">
				<div class="fsm-field-group">
					<label for="fsm-fe-status"><?php esc_html_e( 'Status', 'fire-survey-maker' ); ?></label>
					<select id="fsm-fe-status" class="fsm-input">
						<option value="draft"><?php esc_html_e( 'Draft', 'fire-survey-maker' ); ?></option>
						<option value="open"><?php esc_html_e( 'Open', 'fire-survey-maker' ); ?></option>
						<option value="closed"><?php esc_html_e( 'Closed', 'fire-survey-maker' ); ?></option>
					</select>
				</div>
				<div class="fsm-field-group">
					<label for="fsm-fe-visibility"><?php esc_html_e( 'Results Visibility', 'fire-survey-maker' ); ?></label>
					<select id="fsm-fe-visibility" class="fsm-input">
						<option value="after_submit"><?php esc_html_e( 'After submitting', 'fire-survey-maker' ); ?></option>
						<option value="logged_in"><?php esc_html_e( 'Any logged-in user', 'fire-survey-maker' ); ?></option>
						<option value="public"><?php esc_html_e( 'Public', 'fire-survey-maker' ); ?></option>
						<option value="admin_only"><?php esc_html_e( 'Admins only', 'fire-survey-maker' ); ?></option>
					</select>
				</div>
			</div>

			<div class="fsm-field-row">
				<div class="fsm-field-group">
					<label for="fsm-fe-start"><?php esc_html_e( 'Start Date', 'fire-survey-maker' ); ?></label>
					<input type="datetime-local" id="fsm-fe-start" class="fsm-input">
				</div>
				<div class="fsm-field-group">
					<label for="fsm-fe-end"><?php esc_html_e( 'End Date', 'fire-survey-maker' ); ?></label>
					<input type="datetime-local" id="fsm-fe-end" class="fsm-input">
				</div>
			</div>

			<h2><?php esc_html_e( 'Questions', 'fire-survey-maker' ); ?></h2>
			<div id="fsm-fe-questions"></div>

			<button type="button" id="fsm-fe-add-question" class="fsm-btn fsm-btn--secondary">
				+ <?php esc_html_e( 'Add Question', 'fire-survey-maker' ); ?>
			</button>

			<div class="fsm-form__footer" style="margin-top:24px;">
				<button type="button" id="fsm-fe-save" class="fsm-btn fsm-btn--primary">
					<?php esc_html_e( 'Publish Survey', 'fire-survey-maker' ); ?>
				</button>
				<span id="fsm-fe-status" class="fsm-form__status" aria-live="polite"></span>
			</div>
		</div>
	</div>
</main>
<?php get_footer(); ?>
