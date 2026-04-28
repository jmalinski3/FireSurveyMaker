<?php
/**
 * Plugin Name: FireSurveyMaker
 * Plugin URI:  https://github.com/jmalinski3/firesurveymaker
 * Description: Create and publish surveys on the WordPress frontend with a block, widget, and dedicated pages.
 * Version:     1.0.0
 * Author:      FireSurveyMaker Contributors
 * License:     GPL-2.0-or-later
 * Text Domain: fire-survey-maker
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'FSM_VERSION', '1.0.0' );
define( 'FSM_PLUGIN_FILE', __FILE__ );
define( 'FSM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FSM_PLUGIN_DIR . 'includes/class-fsm-database.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-activator.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-capabilities.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-survey.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-question.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-response.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-rest-api.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-block.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-widget.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-template.php';
require_once FSM_PLUGIN_DIR . 'includes/class-fsm-csv-export.php';
require_once FSM_PLUGIN_DIR . 'admin/class-fsm-admin.php';
require_once FSM_PLUGIN_DIR . 'frontend/class-fsm-frontend.php';

register_activation_hook( __FILE__, array( 'FSM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FSM_Activator', 'deactivate' ) );

add_action( 'init', array( 'FSM_Template', 'register_rewrite_rules' ) );
add_action( 'widgets_init', array( 'FSM_Widget', 'register' ) );
add_action( 'rest_api_init', array( 'FSM_REST_API', 'register_routes' ) );
add_action( 'init', array( 'FSM_Block', 'register' ) );

if ( is_admin() ) {
	new FSM_Admin();
} else {
	new FSM_Frontend();
}
