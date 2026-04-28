<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap fsm-admin">
	<h1><?php echo $survey ? esc_html__( 'Edit Survey', 'fire-survey-maker' ) : esc_html__( 'Add New Survey', 'fire-survey-maker' ); ?></h1>

	<div id="fsm-builder" data-survey='<?php echo $survey ? esc_attr( wp_json_encode( array_merge( $survey, array( 'questions' => $questions ) ) ) ) : 'null'; ?>'>

		<div class="fsm-builder__meta">
			<table class="form-table">
				<tr>
					<th><label for="fsm-title"><?php esc_html_e( 'Title', 'fire-survey-maker' ); ?></label></th>
					<td><input type="text" id="fsm-title" class="regular-text" value="<?php echo esc_attr( $survey['title'] ?? '' ); ?>" required></td>
				</tr>
				<tr>
					<th><label for="fsm-description"><?php esc_html_e( 'Description', 'fire-survey-maker' ); ?></label></th>
					<td><textarea id="fsm-description" rows="3" class="large-text"><?php echo esc_textarea( $survey['description'] ?? '' ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="fsm-status"><?php esc_html_e( 'Status', 'fire-survey-maker' ); ?></label></th>
					<td>
						<select id="fsm-status">
							<?php foreach ( array( 'draft', 'open', 'closed' ) as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $survey['status'] ?? 'draft', $s ); ?>>
									<?php echo esc_html( ucfirst( $s ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="fsm-start-date"><?php esc_html_e( 'Start Date', 'fire-survey-maker' ); ?></label></th>
					<td><input type="datetime-local" id="fsm-start-date" value="<?php echo esc_attr( $survey['start_date'] ? str_replace( ' ', 'T', substr( $survey['start_date'], 0, 16 ) ) : '' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="fsm-end-date"><?php esc_html_e( 'End Date', 'fire-survey-maker' ); ?></label></th>
					<td><input type="datetime-local" id="fsm-end-date" value="<?php echo esc_attr( $survey['end_date'] ? str_replace( ' ', 'T', substr( $survey['end_date'], 0, 16 ) ) : '' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="fsm-visibility"><?php esc_html_e( 'Results Visibility', 'fire-survey-maker' ); ?></label></th>
					<td>
						<select id="fsm-visibility">
							<?php
							$vis_options = array(
								'after_submit' => __( 'After submitting', 'fire-survey-maker' ),
								'logged_in'    => __( 'Any logged-in user', 'fire-survey-maker' ),
								'public'       => __( 'Public (everyone)', 'fire-survey-maker' ),
								'admin_only'   => __( 'Admins only', 'fire-survey-maker' ),
							);
							foreach ( $vis_options as $val => $label ) :
							?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $survey['results_visibility'] ?? 'after_submit', $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<h2><?php esc_html_e( 'Questions', 'fire-survey-maker' ); ?></h2>
		<div id="fsm-questions-list"></div>

		<button type="button" id="fsm-add-question" class="button">
			+ <?php esc_html_e( 'Add Question', 'fire-survey-maker' ); ?>
		</button>

		<p class="submit">
			<button type="button" id="fsm-save" class="button button-primary">
				<?php esc_html_e( 'Save Survey', 'fire-survey-maker' ); ?>
			</button>
			<span id="fsm-save-status" style="margin-left:10px;"></span>
		</p>
	</div>
</div>
