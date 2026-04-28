<?php
defined( 'ABSPATH' ) || exit;
/**
 * Reusable survey form embed — used by the single page, block, and widget.
 * Expects $survey and $questions to be in scope.
 */
$is_open   = FSM_Survey::is_accepting_responses( $survey );
$responded = is_user_logged_in() && FSM_Response::has_responded( (int) $survey['id'], get_current_user_id() );
?>
<div class="fsm-survey"
	data-survey-id="<?php echo esc_attr( $survey['id'] ); ?>"
	data-results-url="<?php echo esc_url( FSM_Template::results_page_url( $survey['slug'] ) ); ?>">

	<h2 class="fsm-survey__title"><?php echo esc_html( $survey['title'] ); ?></h2>

	<?php if ( $survey['description'] ) : ?>
		<div class="fsm-survey__description"><?php echo wp_kses_post( $survey['description'] ); ?></div>
	<?php endif; ?>

	<?php if ( $responded ) : ?>
		<div class="fsm-notice fsm-notice--info">
			<?php esc_html_e( 'You have already responded to this survey.', 'fire-survey-maker' ); ?>
			<a href="<?php echo esc_url( FSM_Template::results_page_url( $survey['slug'] ) ); ?>">
				<?php esc_html_e( 'View Results', 'fire-survey-maker' ); ?>
			</a>
		</div>
	<?php elseif ( ! $is_open ) : ?>
		<div class="fsm-notice fsm-notice--warning">
			<?php esc_html_e( 'This survey is not currently accepting responses.', 'fire-survey-maker' ); ?>
		</div>
	<?php elseif ( ! is_user_logged_in() ) : ?>
		<div class="fsm-notice fsm-notice--warning">
			<?php
			printf(
				/* translators: %s: login link */
				wp_kses( __( 'Please <a href="%s">log in</a> to respond.', 'fire-survey-maker' ), array( 'a' => array( 'href' => array() ) ) ),
				esc_url( wp_login_url( get_permalink() ) )
			);
			?>
		</div>
	<?php else : ?>
		<form class="fsm-form" data-survey-id="<?php echo esc_attr( $survey['id'] ); ?>" novalidate>
			<?php foreach ( $questions as $q ) : ?>
				<div class="fsm-form__question" data-question-id="<?php echo esc_attr( $q['id'] ); ?>" data-type="<?php echo esc_attr( $q['question_type'] ); ?>">
					<p class="fsm-form__question-text">
						<?php echo esc_html( $q['question_text'] ); ?>
						<?php if ( $q['required'] ) : ?>
							<span class="fsm-required" aria-label="required">*</span>
						<?php endif; ?>
					</p>

					<?php if ( 'multiple_choice' === $q['question_type'] ) : ?>
						<?php foreach ( $q['options'] as $opt ) : ?>
							<label class="fsm-form__option">
								<input type="radio" name="q_<?php echo esc_attr( $q['id'] ); ?>" value="<?php echo esc_attr( $opt['id'] ); ?>" <?php echo $q['required'] ? 'required' : ''; ?>>
								<?php echo esc_html( $opt['option_text'] ); ?>
							</label>
						<?php endforeach; ?>

					<?php elseif ( 'checkbox' === $q['question_type'] ) : ?>
						<?php foreach ( $q['options'] as $opt ) : ?>
							<label class="fsm-form__option">
								<input type="checkbox" name="q_<?php echo esc_attr( $q['id'] ); ?>[]" value="<?php echo esc_attr( $opt['id'] ); ?>">
								<?php echo esc_html( $opt['option_text'] ); ?>
							</label>
						<?php endforeach; ?>

					<?php elseif ( 'short_text' === $q['question_type'] ) : ?>
						<input type="text" class="fsm-form__text-input" name="q_<?php echo esc_attr( $q['id'] ); ?>" <?php echo $q['required'] ? 'required' : ''; ?> maxlength="500">
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="fsm-form__footer">
				<button type="submit" class="fsm-btn fsm-btn--primary">
					<?php esc_html_e( 'Submit', 'fire-survey-maker' ); ?>
				</button>
				<span class="fsm-form__status" aria-live="polite"></span>
			</div>
		</form>
	<?php endif; ?>
</div>
