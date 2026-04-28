<?php
defined( 'ABSPATH' ) || exit;

class FSM_Database {

	const DB_VERSION = '1.0.0';

	public static function surveys_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'fsm_surveys';
	}

	public static function questions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'fsm_questions';
	}

	public static function options_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'fsm_question_options';
	}

	public static function responses_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'fsm_responses';
	}

	public static function answers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'fsm_answers';
	}

	public static function get_schema(): string {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$s  = self::surveys_table();
		$q  = self::questions_table();
		$o  = self::options_table();
		$r  = self::responses_table();
		$a  = self::answers_table();

		return "
CREATE TABLE {$s} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  slug varchar(200) NOT NULL,
  title varchar(255) NOT NULL,
  description text,
  status enum('draft','open','closed') NOT NULL DEFAULT 'draft',
  created_by bigint(20) unsigned NOT NULL,
  start_date datetime DEFAULT NULL,
  end_date datetime DEFAULT NULL,
  results_visibility enum('admin_only','after_submit','logged_in','public') NOT NULL DEFAULT 'after_submit',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) {$charset};
CREATE TABLE {$q} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  survey_id bigint(20) unsigned NOT NULL,
  question_text text NOT NULL,
  question_type enum('multiple_choice','checkbox','short_text') NOT NULL,
  sort_order int NOT NULL DEFAULT 0,
  required tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY survey_id (survey_id)
) {$charset};
CREATE TABLE {$o} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  question_id bigint(20) unsigned NOT NULL,
  option_text varchar(255) NOT NULL,
  sort_order int NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY question_id (question_id)
) {$charset};
CREATE TABLE {$r} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  survey_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_response (survey_id, user_id)
) {$charset};
CREATE TABLE {$a} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  response_id bigint(20) unsigned NOT NULL,
  question_id bigint(20) unsigned NOT NULL,
  answer_value text NOT NULL,
  PRIMARY KEY (id),
  KEY response_id (response_id),
  KEY question_id (question_id)
) {$charset};
";
	}
}
