<?php
/**
 * Module Name: Cherry handler
 * Description: Initializes handlers
 * Version: 1.0.0
 * Author: Cherry Team
 * Author URI: http://www.cherryframework.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package    Cherry_Framework
 * @subpackage Modules
 * @version    1.0.0
 * @author     Cherry Team <cherryframework@gmail.com>
 * @copyright  Copyright (c) 2012 - 2016, Cherry Team
 * @link       http://www.cherryframework.com/
 * @license    http://www.gnu.org/licenses/gpl-3.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Handler' ) ) {

	/**
	 * Cherry_Handler class.
	 *
	 * @since 1.0.0
	 */
	class Cherry_Handler {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var   object
		 */
		private static $handlers_list = array();

		/**
		 * Default settings.
		 *
		 * @since 1.0.0
		 * @var array
		 */
		private $settings = array(
			'id'         => '',
			'action'     => '',
			'capability' => '',
			'public'     => false,
			'callback'   => '',
			'type'       => 'post',
			'data_type'  => 'json',
			'sys_messages' => array(
				'invalid_base_data' => 'Unable to process the request without nonce or server error',
				'no_right'          => 'No right for this action',
				'invalid_nonce'     => 'Stop CHEATING!!!',
				'access_is_allowed' => 'Access is allowed',
				'wait_processing'   => 'Please wait, processing the previous request',
			),
		);

		/**
		 * Class constructor.
		 *
		 * @since 1.0.0
		 * @param object $core Core instance.
		 * @param array  $args Class args.
		 */
		public function __construct( $core, $args = array() ) {
			$this->settings = array_merge( $this->settings, $args );

			if ( empty( $this->settings['id'] ) ) {
				echo '<h3>ID is required attr</h3>';
				return false;
			}

			if ( empty( $this->settings['action'] ) ) {
				echo '<h3>Action is required attr</h3>';
				return false;
			}

			// Action empty check
			if ( ! empty( $this->settings['action'] ) ) {
				add_action( 'wp_ajax_' . $this->settings['action'], array( $this, 'handler_init' ) );

				// Public action check
				if ( filter_var( $this->settings['public'], FILTER_VALIDATE_BOOLEAN ) ) {
					add_action( 'wp_ajax_nopriv_' . $this->settings['action'], array( $this, 'handler_init' ) );
				}
			}

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_print_scripts', array( $this, 'localize_script' ) );
		}

		/**
		 * Handler initialization
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function handler_init() {
			if ( ! empty( $_POST ) && array_key_exists( 'nonce', $_POST ) ) {

				$nonce = $_POST['nonce'];

				$nonce_action = ! empty( $this->settings['action'] ) ? $this->settings['action'] : 'cherry_ajax_nonce';

				if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
					$response = array(
						'message' => $this->settings['sys_messages']['invalid_nonce'],
						'type'    => 'error-notice',
					);

					wp_send_json( $response );
				}

				if ( ! empty( $this->settings['capability'] ) && ! current_user_can( $this->settings['capability'] ) ) {
					$response = array(
						'message' => $this->settings['sys_messages']['no_right'],
						'type'    => 'error-notice',
					);

					wp_send_json( $response );
				}

				if ( ! empty( $this->settings['callback'] ) && is_callable( $this->settings['callback'] ) ) {

					ob_start();
					$data = call_user_func( $this->settings['callback'] );

					if ( ! $data ) {
						$data = ob_get_contents();
					}
					ob_end_clean();

					$response = array(
						'message' => $this->settings['sys_messages']['access_is_allowed'],
						'type'    => 'success-notice',
						'data'    => $data,
					);

					wp_send_json( $response );
				}
			} else {
				$response = array(
					'message' => $this->settings['sys_messages']['invalid_base_data'],
					'type'    => 'error-notice',
				);

				wp_send_json( $response );
			}
		}

		/**
		 * Register and enqueue handlers js.
		 *
		 * @since 1.0.0
		 */
		public function enqueue_scripts() {
			wp_enqueue_script(
				'cherry-handler-js',
				esc_url( Cherry_Core::base_url( 'assets/js/min/cherry-handler.min.js', __FILE__ ) ),
				array( 'jquery' ),
				'1.0.0',
				true
			);

			wp_enqueue_style(
				'cherry-handler-css',
				esc_url( Cherry_Core::base_url( 'assets/css/cherry-handler-styles.min.css', __FILE__ ) ),
				array(),
				'1.0.0',
				'all'
			);
		}

		/**
		 * Prepare data for henler script.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function localize_script() {

			$nonce = $this->create_nonce( $this->settings['action'] );

			wp_localize_script( 'cherry-handler-js', $this->settings['id'],
				array(
					'action'       => $this->settings['action'],
					'nonce'        => $nonce,
					'type'         => $this->settings['type'],
					'data_type'    => $this->settings['data_type'],
					'public'       => $this->settings['public'] ? 'true' : 'false',
					'sys_messages' => $this->settings['sys_messages'],
				)
			);

			if ( $this->settings['public'] ) {
				wp_localize_script( 'cherry-handler-js', 'cherryHandlerAjaxUrl', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
			}
		}

		/**
		 * Create nonce by action
		 *
		 * @param  string $action Nonce name.
		 * @return string
		 */
		public function create_nonce( $action = '' ) {
			if ( ! empty( $action ) ) {
				return wp_create_nonce( $action );
			}

			return wp_create_nonce( 'cherry_ajax_nonce' );
		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance( $core, $args ) {
			return new self( $core, $args );
		}
	}
}
