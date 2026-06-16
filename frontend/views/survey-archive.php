<?php
defined( 'ABSPATH' ) || exit;
get_header();

$surveys       = FSM_Survey::get_all( array( 'status' => 'open' ) );
$responded_ids = is_user_logged_in()
	? FSM_Response::get_survey_ids_for_user( get_current_user_id() )
	: array();
?>
<main class="fsm-page fsm-archive">
	<div class="fsm-container">
		<h1><?php esc_html_e( 'Surveys', 'fire-survey-maker' ); ?></h1>

		<?php if ( current_user_can( 'manage_surveys' ) ) : ?>
			<p>
				<a href="<?php echo esc_url( home_url( '/surveys/create/' ) ); ?>" class="fsm-btn fsm-btn--primary">
					<?php esc_html_e( '+ Create Survey', 'fire-survey-maker' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( empty( $surveys ) ) : ?>
			<p><?php esc_html_e( 'No surveys are currently available.', 'fire-survey-maker' ); ?></p>
		<?php else : ?>
			<ul class="fsm-survey-list">
				<?php foreach ( $surveys as $survey ) :
					$submitted = in_array( (int) $survey['id'], $responded_ids, true );
				?>
					<li class="fsm-survey-list__item <?php echo $submitted ? 'fsm-survey-list__item--submitted' : ''; ?>">
						<div class="fsm-survey-list__header">
							<strong class="fsm-survey-list__title">
								<?php echo esc_html( $survey['title'] ); ?>
							</strong>
							<?php if ( $submitted ) : ?>
								<span class="fsm-badge fsm-badge--submitted">
									<?php esc_html_e( 'Submitted', 'fire-survey-maker' ); ?>
								</span>
							<?php endif; ?>
						</div>

						<?php if ( $survey['description'] ) : ?>
							<p class="fsm-survey-list__desc">
								<?php echo esc_html( wp_trim_words( $survey['description'], 20 ) ); ?>
							</p>
						<?php endif; ?>

						<div class="fsm-survey-list__actions">
							<?php if ( $submitted ) : ?>
								<a href="<?php echo esc_url( FSM_Template::results_page_url( $survey['slug'] ) ); ?>" class="fsm-btn fsm-btn--secondary">
									<?php esc_html_e( 'View Results', 'fire-survey-maker' ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( FSM_Template::survey_page_url( $survey['slug'] ) ); ?>" class="fsm-btn fsm-btn--primary">
									<?php esc_html_e( 'Take Survey', 'fire-survey-maker' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
