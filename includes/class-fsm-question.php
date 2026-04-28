<?php
defined( 'ABSPATH' ) || exit;

class FSM_Question {

	public static function create( int $survey_id, array $data ): int|false {
		global $wpdb;
		$result = $wpdb->insert(
			FSM_Database::questions_table(),
			array(
				'survey_id'     => $survey_id,
				'question_text' => sanitize_textarea_field( $data['question_text'] ),
				'question_type' => $data['question_type'],
				'sort_order'    => (int) ( $data['sort_order'] ?? 0 ),
				'required'      => isset( $data['required'] ) ? (int) $data['required'] : 1,
			),
			array( '%d', '%s', '%s', '%d', '%d' )
		);
		if ( ! $result ) {
			return false;
		}
		$question_id = (int) $wpdb->insert_id;
		if ( in_array( $data['question_type'], array( 'multiple_choice', 'checkbox' ), true ) && ! empty( $data['options'] ) ) {
			foreach ( $data['options'] as $i => $option_text ) {
				$wpdb->insert(
					FSM_Database::options_table(),
					array(
						'question_id' => $question_id,
						'option_text' => sanitize_text_field( $option_text ),
						'sort_order'  => $i,
					),
					array( '%d', '%s', '%d' )
				);
			}
		}
		return $question_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$fields  = array();
		$formats = array();

		if ( isset( $data['question_text'] ) ) {
			$fields['question_text'] = sanitize_textarea_field( $data['question_text'] );
			$formats[] = '%s';
		}
		if ( isset( $data['sort_order'] ) ) {
			$fields['sort_order'] = (int) $data['sort_order'];
			$formats[] = '%d';
		}
		if ( isset( $data['required'] ) ) {
			$fields['required'] = (int) $data['required'];
			$formats[] = '%d';
		}

		if ( ! empty( $fields ) ) {
			$wpdb->update( FSM_Database::questions_table(), $fields, array( 'id' => $id ), $formats, array( '%d' ) );
		}

		if ( isset( $data['options'] ) ) {
			$wpdb->delete( FSM_Database::options_table(), array( 'question_id' => $id ), array( '%d' ) );
			foreach ( $data['options'] as $i => $option_text ) {
				$wpdb->insert(
					FSM_Database::options_table(),
					array(
						'question_id' => $id,
						'option_text' => sanitize_text_field( $option_text ),
						'sort_order'  => $i,
					),
					array( '%d', '%s', '%d' )
				);
			}
		}
		return true;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( FSM_Database::options_table(), array( 'question_id' => $id ), array( '%d' ) );
		return (bool) $wpdb->delete( FSM_Database::questions_table(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function get( int $id ): array|null {
		global $wpdb;
		$q = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . FSM_Database::questions_table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		if ( ! $q ) {
			return null;
		}
		$q['options'] = self::get_options( $id );
		return $q;
	}

	public static function get_by_survey( int $survey_id ): array {
		global $wpdb;
		$questions = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . FSM_Database::questions_table() . ' WHERE survey_id = %d ORDER BY sort_order ASC',
				$survey_id
			),
			ARRAY_A
		) ?: array();

		foreach ( $questions as &$q ) {
			$q['options'] = self::get_options( (int) $q['id'] );
		}
		return $questions;
	}

	public static function get_options( int $question_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . FSM_Database::options_table() . ' WHERE question_id = %d ORDER BY sort_order ASC',
				$question_id
			),
			ARRAY_A
		) ?: array();
	}
}
