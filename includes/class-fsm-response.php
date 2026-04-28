<?php
defined( 'ABSPATH' ) || exit;

class FSM_Response {

	public static function get_survey_ids_for_user( int $user_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT survey_id FROM ' . FSM_Database::responses_table() . ' WHERE user_id = %d',
				$user_id
			)
		);
		return array_map( 'intval', $ids ?: array() );
	}

	public static function has_responded( int $survey_id, int $user_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . FSM_Database::responses_table() . ' WHERE survey_id = %d AND user_id = %d',
				$survey_id,
				$user_id
			)
		);
	}

	/**
	 * Save a full submission. $answers is keyed by question_id.
	 * Returns response_id or WP_Error.
	 */
	public static function submit( int $survey_id, int $user_id, array $answers ): int|WP_Error {
		global $wpdb;

		if ( self::has_responded( $survey_id, $user_id ) ) {
			return new WP_Error( 'already_responded', __( 'You have already responded to this survey.', 'fire-survey-maker' ), array( 'status' => 409 ) );
		}

		$wpdb->insert(
			FSM_Database::responses_table(),
			array(
				'survey_id' => $survey_id,
				'user_id'   => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'db_error', __( 'Could not save response.', 'fire-survey-maker' ), array( 'status' => 500 ) );
		}

		$response_id = (int) $wpdb->insert_id;

		foreach ( $answers as $question_id => $value ) {
			$answer_value = is_array( $value ) ? wp_json_encode( array_map( 'intval', $value ) ) : sanitize_textarea_field( (string) $value );
			$wpdb->insert(
				FSM_Database::answers_table(),
				array(
					'response_id' => $response_id,
					'question_id' => (int) $question_id,
					'answer_value' => $answer_value,
				),
				array( '%d', '%d', '%s' )
			);
		}

		return $response_id;
	}

	public static function get_aggregate( int $survey_id ): array {
		global $wpdb;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . FSM_Database::responses_table() . ' WHERE survey_id = %d', $survey_id )
		);

		$questions = FSM_Question::get_by_survey( $survey_id );
		$result    = array();

		foreach ( $questions as $q ) {
			$qid  = (int) $q['id'];
			$type = $q['question_type'];
			$item = array(
				'id'   => $qid,
				'text' => $q['question_text'],
				'type' => $type,
			);

			$raw_answers = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT a.answer_value FROM ' . FSM_Database::answers_table() . ' a
					 JOIN ' . FSM_Database::responses_table() . ' r ON a.response_id = r.id
					 WHERE r.survey_id = %d AND a.question_id = %d',
					$survey_id,
					$qid
				)
			);

			if ( 'short_text' === $type ) {
				$item['answers'] = $raw_answers;
			} else {
				$option_counts = array();
				foreach ( $q['options'] as $opt ) {
					$option_counts[ (int) $opt['id'] ] = array(
						'text'  => $opt['option_text'],
						'count' => 0,
					);
				}
				foreach ( $raw_answers as $val ) {
					$ids = json_decode( $val, true );
					if ( ! is_array( $ids ) ) {
						$ids = array( (int) $val );
					}
					foreach ( $ids as $oid ) {
						if ( isset( $option_counts[ $oid ] ) ) {
							$option_counts[ $oid ]['count']++;
						}
					}
				}
				$resp_count    = max( count( $raw_answers ), 1 );
				$item['options'] = array_values(
					array_map(
						function ( $o ) use ( $resp_count ) {
							$o['pct'] = (int) round( $o['count'] / $resp_count * 100 );
							return $o;
						},
						$option_counts
					)
				);
			}

			$result[] = $item;
		}

		return array(
			'total_responses' => $total,
			'questions'       => $result,
		);
	}

	public static function get_all_for_survey( int $survey_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.*, u.user_email FROM ' . FSM_Database::responses_table() . ' r
				 LEFT JOIN ' . $wpdb->users . ' u ON r.user_id = u.ID
				 WHERE r.survey_id = %d ORDER BY r.submitted_at ASC',
				$survey_id
			),
			ARRAY_A
		) ?: array();
	}

	public static function get_answers_for_response( int $response_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . FSM_Database::answers_table() . ' WHERE response_id = %d',
				$response_id
			),
			ARRAY_A
		) ?: array();
	}
}
