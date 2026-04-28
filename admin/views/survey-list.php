<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap fsm-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Surveys', 'fire-survey-maker' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=fsm-survey-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'fire-survey-maker' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( empty( $surveys ) ) : ?>
		<p><?php esc_html_e( 'No surveys yet. Click "Add New" to create your first survey.', 'fire-survey-maker' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'fire-survey-maker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'fire-survey-maker' ); ?></th>
					<th><?php esc_html_e( 'Responses', 'fire-survey-maker' ); ?></th>
					<th><?php esc_html_e( 'Created', 'fire-survey-maker' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'fire-survey-maker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $surveys as $survey ) :
					global $wpdb;
					$count = (int) $wpdb->get_var( $wpdb->prepare(
						'SELECT COUNT(*) FROM ' . FSM_Database::responses_table() . ' WHERE survey_id = %d',
						$survey['id']
					) );
				?>
				<tr data-survey-id="<?php echo esc_attr( $survey['id'] ); ?>">
					<td>
						<strong>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=fsm-survey-edit&survey_id=' . $survey['id'] ) ); ?>">
								<?php echo esc_html( $survey['title'] ); ?>
							</a>
						</strong>
					</td>
					<td>
						<span class="fsm-status fsm-status--<?php echo esc_attr( $survey['status'] ); ?>">
							<?php echo esc_html( ucfirst( $survey['status'] ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $count ); ?></td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $survey['created_at'] ) ) ); ?></td>
					<td class="fsm-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fsm-survey-edit&survey_id=' . $survey['id'] ) ); ?>">
							<?php esc_html_e( 'Edit', 'fire-survey-maker' ); ?>
						</a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fsm-survey-results&survey_id=' . $survey['id'] ) ); ?>">
							<?php esc_html_e( 'Results', 'fire-survey-maker' ); ?>
						</a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( home_url( '/surveys/' . $survey['slug'] . '/' ) ); ?>" target="_blank">
							<?php esc_html_e( 'View', 'fire-survey-maker' ); ?>
						</a>
						&nbsp;|&nbsp;
						<a href="#" class="fsm-delete-survey" data-id="<?php echo esc_attr( $survey['id'] ); ?>" style="color:#a00;">
							<?php esc_html_e( 'Delete', 'fire-survey-maker' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
