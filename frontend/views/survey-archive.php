<?php
defined( 'ABSPATH' ) || exit;
get_header();
$surveys = FSM_Survey::get_all( array( 'status' => 'open' ) );
?>
<main class="fsm-page fsm-archive">
	<div class="fsm-container">
		<h1><?php esc_html_e( 'Surveys', 'fire-survey-maker' ); ?></h1>

		<?php if ( FSM_Capabilities::current_user_can() ) : ?>
			<p>
				<a href="<?php echo esc_url( home_url( '/surveys/create/' ) ); ?>" class="fsm-btn fsm-btn--primary">
					<?php esc_html_e( '+ Create Survey', 'fire-survey-maker' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( empty( $surveys ) ) : ?>
			<p><?php esc_html_e( 'No surveys are currently open.', 'fire-survey-maker' ); ?></p>
		<?php else : ?>
			<ul class="fsm-survey-list">
				<?php foreach ( $surveys as $survey ) : ?>
					<li class="fsm-survey-list__item">
						<a href="<?php echo esc_url( FSM_Template::survey_page_url( $survey['slug'] ) ); ?>" class="fsm-survey-list__link">
							<strong><?php echo esc_html( $survey['title'] ); ?></strong>
						</a>
						<?php if ( $survey['description'] ) : ?>
							<p><?php echo esc_html( wp_trim_words( $survey['description'], 20 ) ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
