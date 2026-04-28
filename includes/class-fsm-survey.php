<?php
defined( 'ABSPATH' ) || exit;

class FSM_Survey {

	public static function create( array $data ): int|false {
		global $wpdb;
		$slug    = self::unique_slug( $data['title'] );
		$fields  = array(
			'slug'               => $slug,
			'title'              => sanitize_text_field( $data['title'] ),
			'description'        => wp_kses_post( $data['description'] ?? '' ),
			'status'             => $data['status'] ?? 'draft',
			'created_by'         => get_current_user_id(),
			'results_visibility' => $data['results_visibility'] ?? 'after_submit',
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s' );

		// Only include date fields when a real value is provided; omitting them
		// lets MySQL use the column DEFAULT (NULL) and avoids strict-mode errors
		// caused by wpdb converting null to '' for datetime columns.
		if ( ! empty( $data['start_date'] ) ) {
			$fields['start_date'] = $data['start_date'];
			$formats[]            = '%s';
		}
		if ( ! empty( $data['end_date'] ) ) {
			$fields['end_date'] = $data['end_date'];
			$formats[]          = '%s';
		}

		$result = $wpdb->insert( FSM_Database::surveys_table(), $fields, $formats );
		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$fields = array();
		$formats = array();

		$allowed = array(
			'title'              => array( 'sanitize_text_field', '%s' ),
			'description'        => array( 'wp_kses_post', '%s' ),
			'status'             => array( null, '%s' ),
			'start_date'         => array( null, '%s' ),
			'end_date'           => array( null, '%s' ),
			'results_visibility' => array( null, '%s' ),
		);

		$date_keys = array( 'start_date', 'end_date' );

		foreach ( $allowed as $key => $config ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$val = $data[ $key ];
			if ( $config[0] ) {
				$val = call_user_func( $config[0], $val );
			}
			// Skip date fields when empty to avoid wpdb converting null to ''
			// which MySQL strict mode rejects for datetime columns.
			if ( in_array( $key, $date_keys, true ) && empty( $val ) ) {
				continue;
			}
			$fields[ $key ] = $val;
			$formats[]      = $config[1];
		}

		if ( empty( $fields ) ) {
			return true;
		}

		$result = $wpdb->update(
			FSM_Database::surveys_table(),
			$fields,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);
		return false !== $result;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$questions = FSM_Question::get_by_survey( $id );
		foreach ( $questions as $q ) {
			FSM_Question::delete( (int) $q['id'] );
		}
		$responses = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM ' . FSM_Database::responses_table() . ' WHERE survey_id = %d', $id )
		);
		foreach ( $responses as $rid ) {
			$wpdb->delete( FSM_Database::answers_table(), array( 'response_id' => $rid ), array( '%d' ) );
		}
		$wpdb->delete( FSM_Database::responses_table(), array( 'survey_id' => $id ), array( '%d' ) );
		return (bool) $wpdb->delete( FSM_Database::surveys_table(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function get( int $id ): array|null {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . FSM_Database::surveys_table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function get_by_slug( string $slug ): array|null {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . FSM_Database::surveys_table() . ' WHERE slug = %s', $slug ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$status = $args['status'] ?? null;
		$sql    = 'SELECT * FROM ' . FSM_Database::surveys_table();
		if ( $status ) {
			$sql .= $wpdb->prepare( ' WHERE status = %s', $status );
		}
		$sql .= ' ORDER BY created_at DESC';
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function is_accepting_responses( array $survey ): bool {
		if ( 'open' !== $survey['status'] ) {
			return false;
		}
		$now = current_time( 'mysql' );
		if ( $survey['start_date'] && $now < $survey['start_date'] ) {
			return false;
		}
		if ( $survey['end_date'] && $now > $survey['end_date'] ) {
			return false;
		}
		return true;
	}

	private static function unique_slug( string $title ): string {
		global $wpdb;
		$base = sanitize_title( $title );
		$slug = $base;
		$i    = 1;
		while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . FSM_Database::surveys_table() . ' WHERE slug = %s', $slug ) ) ) {
			$slug = $base . '-' . $i++;
		}
		return $slug;
	}
}
