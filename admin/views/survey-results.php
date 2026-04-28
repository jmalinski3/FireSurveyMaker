<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap fsm-admin">
	<?php if ( ! $survey ) : ?>
		<h1><?php esc_html_e( 'Survey not found.', 'fire-survey-maker' ); ?></h1>
	<?php else : ?>
		<h1>
			<?php
			printf(
				/* translators: %s: survey title */
				esc_html__( 'Results: %s', 'fire-survey-maker' ),
				esc_html( $survey['title'] )
			);
			?>
		</h1>

		<p>
			<a href="<?php echo esc_url( rest_url( 'fsm/v1/surveys/' . $survey['id'] . '/export' ) . '?_wpnonce=' . wp_create_nonce( 'wp_rest' ) ); ?>" class="button">
				<?php esc_html_e( 'Download CSV', 'fire-survey-maker' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( home_url( '/surveys/' . $survey['slug'] . '/results/' ) ); ?>" target="_blank" class="button">
				<?php esc_html_e( 'View Public Results Page', 'fire-survey-maker' ); ?>
			</a>
		</p>

		<div id="fsm-admin-results"
			data-survey-id="<?php echo esc_attr( $survey['id'] ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'fsm/v1/' ) ); ?>">
			<p><?php esc_html_e( 'Loading results…', 'fire-survey-maker' ); ?></p>
		</div>
	<?php endif; ?>
</div>
