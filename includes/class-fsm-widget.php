<?php
defined( 'ABSPATH' ) || exit;

class FSM_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'fsm_survey_widget',
			__( 'FireSurveyMaker Survey', 'fire-survey-maker' ),
			array( 'description' => __( 'Embed a survey in a widget area.', 'fire-survey-maker' ) )
		);
	}

	public static function register(): void {
		register_widget( __CLASS__ );
	}

	public function widget( $args, $instance ): void {
		$survey_id = (int) ( $instance['survey_id'] ?? 0 );
		if ( ! $survey_id ) {
			return;
		}
		$survey = FSM_Survey::get( $survey_id );
		if ( ! $survey ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo FSM_Frontend::render_survey_embed( $survey_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ): void {
		$selected = (int) ( $instance['survey_id'] ?? 0 );
		$title    = esc_attr( $instance['title'] ?? '' );
		$surveys  = FSM_Survey::get_all();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'fire-survey-maker' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo $title; ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'survey_id' ) ); ?>"><?php esc_html_e( 'Survey:', 'fire-survey-maker' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'survey_id' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'survey_id' ) ); ?>">
				<option value="0"><?php esc_html_e( '— Select —', 'fire-survey-maker' ); ?></option>
				<?php foreach ( $surveys as $s ) : ?>
					<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $selected, $s['id'] ); ?>>
						<?php echo esc_html( $s['title'] ); ?> (<?php echo esc_html( $s['status'] ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ): array {
		return array(
			'title'     => sanitize_text_field( $new_instance['title'] ?? '' ),
			'survey_id' => (int) ( $new_instance['survey_id'] ?? 0 ),
		);
	}
}
