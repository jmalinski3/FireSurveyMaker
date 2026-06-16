<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap fsm-admin">
	<h1><?php esc_html_e( 'FireSurveyMaker Settings', 'fire-survey-maker' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'fsm_settings', 'fsm_settings_nonce' ); ?>
		<h2><?php esc_html_e( 'Roles with Survey Management Access', 'fire-survey-maker' ); ?></h2>
		<p><?php esc_html_e( 'Select which roles can create, edit, and delete surveys.', 'fire-survey-maker' ); ?></p>
		<table class="form-table">
			<?php foreach ( $all_roles as $slug => $name ) : ?>
				<tr>
					<th><?php echo esc_html( translate_user_role( $name ) ); ?></th>
					<td>
						<?php if ( 'administrator' === $slug ) : ?>
							<input type="checkbox" checked disabled>
							<span class="description"><?php esc_html_e( 'Always granted', 'fire-survey-maker' ); ?></span>
						<?php else : ?>
							<input type="checkbox" name="fsm_roles[<?php echo esc_attr( $slug ); ?>]" value="1"
								<?php checked( (bool) get_role( $slug )?->has_cap( 'manage_surveys' ) ); ?>>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<p class="submit">
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'fire-survey-maker' ); ?>">
		</p>
	</form>
</div>
