<?php
defined( 'ABSPATH' ) || exit;

class FSM_CSV_Export {

	public static function stream( int $survey_id ): never {
		if ( ! current_user_can( 'manage_surveys' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'fire-survey-maker' ), '', array( 'response' => 403 ) );
		}

		$survey = FSM_Survey::get( $survey_id );
		if ( ! $survey ) {
			wp_die( esc_html__( 'Survey not found.', 'fire-survey-maker' ), '', array( 'response' => 404 ) );
		}

		$questions = FSM_Question::get_by_survey( $survey_id );
		$responses = FSM_Response::get_all_for_survey( $survey_id );

		$filename = sanitize_file_name( $survey['slug'] . '-results-' . gmdate( 'Y-m-d' ) . '.csv' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );

		// Header row
		$header = array( 'response_id', 'user_email', 'submitted_at' );
		foreach ( $questions as $q ) {
			$header[] = 'Q' . $q['id'] . ': ' . $q['question_text'];
		}
		fputcsv( $out, $header );

		// Build option lookup
		$option_map = array();
		foreach ( $questions as $q ) {
			foreach ( $q['options'] as $opt ) {
				$option_map[ (int) $opt['id'] ] = $opt['option_text'];
			}
		}

		// Data rows
		foreach ( $responses as $response ) {
			$answers = FSM_Response::get_answers_for_response( (int) $response['id'] );
			$answer_map = array();
			foreach ( $answers as $a ) {
				$answer_map[ (int) $a['question_id'] ] = $a['answer_value'];
			}

			$row = array(
				$response['id'],
				$response['user_email'],
				$response['submitted_at'],
			);

			foreach ( $questions as $q ) {
				$qid = (int) $q['id'];
				$raw = $answer_map[ $qid ] ?? '';

				if ( 'short_text' === $q['question_type'] ) {
					$row[] = $raw;
				} else {
					$ids   = json_decode( $raw, true );
					if ( ! is_array( $ids ) ) {
						$ids = array( (int) $raw );
					}
					$texts = array_map( fn( $id ) => $option_map[ $id ] ?? $id, $ids );
					$row[] = implode( '; ', $texts );
				}
			}

			fputcsv( $out, $row );
		}

		fclose( $out );
		exit;
	}
}
