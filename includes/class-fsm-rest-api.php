<?php
defined( 'ABSPATH' ) || exit;

class FSM_REST_API {

	const NAMESPACE = 'fsm/v1';

	public static function register_routes(): void {
		$ns = self::NAMESPACE;

		register_rest_route( $ns, '/surveys', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_surveys' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_survey' ),
				'permission_callback' => array( __CLASS__, 'require_manage_surveys' ),
				'args'                => self::survey_args( true ),
			),
		) );

		register_rest_route( $ns, '/surveys/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_survey' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_survey' ),
				'permission_callback' => array( __CLASS__, 'require_manage_surveys' ),
				'args'                => self::survey_args( false ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_survey' ),
				'permission_callback' => array( __CLASS__, 'require_manage_surveys' ),
			),
		) );

		register_rest_route( $ns, '/surveys/(?P<id>\d+)/submit', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'submit_response' ),
			'permission_callback' => array( __CLASS__, 'require_logged_in' ),
		) );

		register_rest_route( $ns, '/surveys/(?P<id>\d+)/results', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_results' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/surveys/(?P<id>\d+)/export', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'export_csv' ),
			'permission_callback' => array( __CLASS__, 'require_manage_surveys' ),
		) );

		register_rest_route( $ns, '/roles', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_roles' ),
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_roles' ),
				'permission_callback' => 'is_super_admin',
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	public static function require_manage_surveys(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'fire-survey-maker' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'manage_surveys' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to manage surveys.', 'fire-survey-maker' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public static function require_logged_in(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to respond to surveys.', 'fire-survey-maker' ), array( 'status' => 401 ) );
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Survey endpoints
	// -------------------------------------------------------------------------

	public static function list_surveys( WP_REST_Request $request ): WP_REST_Response {
		$status   = $request->get_param( 'status' ) ?: 'open';
		$surveys  = FSM_Survey::get_all( array( 'status' => $status ) );
		return rest_ensure_response( $surveys );
	}

	public static function get_survey( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$survey = FSM_Survey::get( (int) $request['id'] );
		if ( ! $survey ) {
			return new WP_Error( 'not_found', __( 'Survey not found.', 'fire-survey-maker' ), array( 'status' => 404 ) );
		}
		$survey['questions'] = FSM_Question::get_by_survey( (int) $survey['id'] );
		return rest_ensure_response( $survey );
	}

	public static function create_survey( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$data = $request->get_json_params();
		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'fire-survey-maker' ), array( 'status' => 400 ) );
		}

		$questions = ( ! empty( $data['questions'] ) && is_array( $data['questions'] ) ) ? $data['questions'] : array();
		$validation = self::validate_question_types( $questions );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$wpdb->query( 'START TRANSACTION' );

		$survey_id = FSM_Survey::create( $data );
		if ( ! $survey_id ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db_error', __( 'Could not create survey.', 'fire-survey-maker' ), array( 'status' => 500 ) );
		}

		foreach ( $questions as $i => $q ) {
			unset( $q['id'] ); // every question on create is new; ignore any client-supplied id
			$q['sort_order'] = $i;
			if ( ! FSM_Question::create( $survey_id, $q ) ) {
				$wpdb->query( 'ROLLBACK' );
				FSM_Survey::delete( $survey_id );
				return new WP_Error( 'db_error', __( 'Could not create question.', 'fire-survey-maker' ), array( 'status' => 500 ) );
			}
		}

		$wpdb->query( 'COMMIT' );

		$survey              = FSM_Survey::get( $survey_id );
		$survey['questions'] = FSM_Question::get_by_survey( $survey_id );
		return new WP_REST_Response( $survey, 201 );
	}

	public static function update_survey( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id = (int) $request['id'];
		if ( ! FSM_Survey::get( $id ) ) {
			return new WP_Error( 'not_found', __( 'Survey not found.', 'fire-survey-maker' ), array( 'status' => 404 ) );
		}

		$data         = $request->get_json_params();
		$has_questions = isset( $data['questions'] ) && is_array( $data['questions'] );
		$existing_ids = array();

		if ( $has_questions ) {
			$validation = self::validate_question_types( $data['questions'] );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$existing_ids = array_map( 'intval', array_column( FSM_Question::get_by_survey( $id ), 'id' ) );
			$validation   = self::validate_question_ids( $data['questions'], $existing_ids );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		}

		if ( $has_questions ) {
			$wpdb->query( 'START TRANSACTION' );
		}

		if ( ! FSM_Survey::update( $id, $data ) ) {
			if ( $has_questions ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return new WP_Error( 'db_error', __( 'Could not update survey.', 'fire-survey-maker' ), array( 'status' => 500 ) );
		}

		if ( $has_questions ) {
			$incoming_ids = array_map( 'intval', array_filter( array_column( $data['questions'], 'id' ) ) );

			foreach ( array_diff( $existing_ids, $incoming_ids ) as $del_id ) {
				FSM_Question::delete( (int) $del_id );
			}
			foreach ( $data['questions'] as $i => $q ) {
				$q['sort_order'] = $i;
				if ( ! empty( $q['id'] ) ) {
					FSM_Question::update( (int) $q['id'], $q );
				} else {
					if ( ! FSM_Question::create( $id, $q ) ) {
						$wpdb->query( 'ROLLBACK' );
						return new WP_Error( 'db_error', __( 'Could not create question.', 'fire-survey-maker' ), array( 'status' => 500 ) );
					}
				}
			}
			$wpdb->query( 'COMMIT' );
		}

		$survey              = FSM_Survey::get( $id );
		$survey['questions'] = FSM_Question::get_by_survey( $id );
		return rest_ensure_response( $survey );
	}

	/**
	 * Validate that every incoming question id belongs to the survey being updated.
	 * Prevents a PUT to survey A from referencing a question id that lives in
	 * survey B, which would otherwise cause cross-survey data corruption: A's
	 * questions would be deleted (because they're absent from the payload) and
	 * B's question would be mutated (because the foreign id is treated as
	 * legitimate by FSM_Question::update).
	 */
	private static function validate_question_ids( array $questions, array $existing_ids ): ?WP_Error {
		$existing_ids = array_map( 'intval', $existing_ids );
		foreach ( $questions as $i => $q ) {
			if ( empty( $q['id'] ) ) {
				continue;
			}
			$qid = (int) $q['id'];
			if ( ! in_array( $qid, $existing_ids, true ) ) {
				return new WP_Error(
					'invalid_question_id',
					sprintf(
						/* translators: 1: question position (1-indexed), 2: question id */
						__( 'Question %1$d (id %2$d) does not belong to this survey.', 'fire-survey-maker' ),
						$i + 1,
						$qid
					),
					array( 'status' => 400 )
				);
			}
		}
		return null;
	}

	/**
	 * Validate the question_type of every question in a payload.
	 *
	 * Always validate, regardless of any client-supplied `id`:
	 *   - On create, every question is new and an incoming `id` is meaningless,
	 *     but a malicious client can include one to attempt to bypass checks.
	 *   - On update, accepting an unknown type for any question is wrong on
	 *     principle and would let a client smuggle invalid data into the
	 *     payload roundtrip.
	 */
	private static function validate_question_types( array $questions ): ?WP_Error {
		foreach ( $questions as $i => $q ) {
			$type = $q['question_type'] ?? '';
			if ( ! in_array( $type, FSM_Question::ALLOWED_TYPES, true ) ) {
				return new WP_Error(
					'invalid_question_type',
					sprintf(
						/* translators: 1: question position (1-indexed), 2: invalid type */
						__( 'Question %1$d has an invalid type "%2$s".', 'fire-survey-maker' ),
						$i + 1,
						(string) $type
					),
					array( 'status' => 400 )
				);
			}
		}
		return null;
	}

	public static function delete_survey( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request['id'];
		if ( ! FSM_Survey::get( $id ) ) {
			return new WP_Error( 'not_found', __( 'Survey not found.', 'fire-survey-maker' ), array( 'status' => 404 ) );
		}
		FSM_Survey::delete( $id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	// -------------------------------------------------------------------------
	// Submission endpoint
	// -------------------------------------------------------------------------

	public static function submit_response( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$survey_id = (int) $request['id'];
		$survey    = FSM_Survey::get( $survey_id );

		if ( ! $survey ) {
			return new WP_Error( 'not_found', __( 'Survey not found.', 'fire-survey-maker' ), array( 'status' => 404 ) );
		}
		if ( ! FSM_Survey::is_accepting_responses( $survey ) ) {
			return new WP_Error( 'survey_closed', __( 'This survey is not currently accepting responses.', 'fire-survey-maker' ), array( 'status' => 403 ) );
		}

		$data    = $request->get_json_params();
		$answers = $data['answers'] ?? array();

		$questions = FSM_Question::get_by_survey( $survey_id );
		foreach ( $questions as $q ) {
			if ( $q['required'] && ! isset( $answers[ $q['id'] ] ) ) {
				return new WP_Error(
					'missing_answer',
					sprintf( __( 'Question "%s" is required.', 'fire-survey-maker' ), $q['question_text'] ),
					array( 'status' => 400 )
				);
			}
		}

		$result = FSM_Response::submit( $survey_id, get_current_user_id(), $answers );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'response_id' => $result ), 201 );
	}

	// -------------------------------------------------------------------------
	// Results endpoint
	// -------------------------------------------------------------------------

	public static function get_results( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$survey_id = (int) $request['id'];
		$survey    = FSM_Survey::get( $survey_id );

		if ( ! $survey ) {
			return new WP_Error( 'not_found', __( 'Survey not found.', 'fire-survey-maker' ), array( 'status' => 404 ) );
		}

		$visibility = $survey['results_visibility'];

		if ( 'admin_only' === $visibility && ! current_user_can( 'manage_surveys' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Results are not public for this survey.', 'fire-survey-maker' ), array( 'status' => 403 ) );
		}
		if ( 'logged_in' === $visibility && ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to view results.', 'fire-survey-maker' ), array( 'status' => 401 ) );
		}
		if ( 'after_submit' === $visibility ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'rest_forbidden', __( 'You must submit a response before viewing results.', 'fire-survey-maker' ), array( 'status' => 401 ) );
			}
			if ( ! current_user_can( 'manage_surveys' ) && ! FSM_Response::has_responded( $survey_id, get_current_user_id() ) ) {
				return new WP_Error( 'rest_forbidden', __( 'You must submit a response before viewing results.', 'fire-survey-maker' ), array( 'status' => 403 ) );
			}
		}

		return rest_ensure_response( FSM_Response::get_aggregate( $survey_id ) );
	}

	// -------------------------------------------------------------------------
	// CSV export (handled by dedicated class, streamed outside REST)
	// -------------------------------------------------------------------------

	public static function export_csv( WP_REST_Request $request ): never {
		$survey_id = (int) $request['id'];
		FSM_CSV_Export::stream( $survey_id );
	}

	// -------------------------------------------------------------------------
	// Roles management
	// -------------------------------------------------------------------------

	public static function get_roles(): WP_REST_Response {
		global $wp_roles;
		$result = array();
		foreach ( $wp_roles->get_names() as $slug => $name ) {
			$role = get_role( $slug );
			$result[] = array(
				'slug'    => $slug,
				'name'    => $name,
				'granted' => $role ? $role->has_cap( 'manage_surveys' ) : false,
			);
		}
		return rest_ensure_response( $result );
	}

	public static function update_roles( WP_REST_Request $request ): WP_REST_Response {
		$data   = $request->get_json_params();
		$grant  = $data['grant'] ?? array();
		$revoke = $data['revoke'] ?? array();
		foreach ( $grant as $role_slug ) {
			$role = get_role( sanitize_key( $role_slug ) );
			if ( $role ) {
				$role->add_cap( 'manage_surveys' );
			}
		}
		foreach ( $revoke as $role_slug ) {
			$slug = sanitize_key( $role_slug );
			if ( 'administrator' === $slug ) {
				continue;
			}
			$role = get_role( $slug );
			if ( $role ) {
				$role->remove_cap( 'manage_surveys' );
			}
		}
		return rest_ensure_response( array( 'updated' => true ) );
	}

	// -------------------------------------------------------------------------
	// Arg definitions
	// -------------------------------------------------------------------------

	private static function survey_args( bool $required ): array {
		return array(
			'title'              => array( 'type' => 'string', 'required' => $required ),
			'description'        => array( 'type' => 'string' ),
			'status'             => array( 'type' => 'string', 'enum' => array( 'draft', 'open', 'closed' ) ),
			'start_date'         => array( 'type' => 'string' ),
			'end_date'           => array( 'type' => 'string' ),
			'results_visibility' => array( 'type' => 'string', 'enum' => array( 'admin_only', 'after_submit', 'logged_in', 'public' ) ),
			'questions'          => array( 'type' => 'array' ),
		);
	}
}
