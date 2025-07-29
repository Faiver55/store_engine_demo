<?php
/**
 * Admin notice handler.
 */

namespace StoreEngine\Admin;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notices {
	use Singleton;

	const TYPE_INFO = 'info';

	const TYPE_SUCCESS = 'success';

	const TYPE_WARNING = 'warning';

	const TYPE_ERROR = 'error';

	private static array $notices = [];

	private static string $action = 'storeengine/hide-notice';

	private static string $option_name = 'storeengine_notices';

	protected function __construct() {
		add_action( 'init', [ $this, 'dispatch_notices' ] );
		add_filter( 'storeengine/backend_scripts_data', [ $this, 'add_notice_data' ] );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
		add_action( 'wp_ajax_' . self::$action, [ $this, 'handle_admin_request' ] );
	}

	public function dispatch_notices() {
		$permalink_structure = get_option( 'permalink_structure' );

		if ( empty( $permalink_structure ) ) {
			$this->add_permalink_notice();
		}

		if ( Helper::get_addon_active_status( 'subscription' ) && ! Helper::get_payment_gateways()->one_gateway_supports( 'subscriptions' ) ) {
			self::add_notice( 'subscription_stripe_dependency_notice', [
				'type'    => 'info',
				'large'   => true,
				'message' =>
					sprintf(
						__( 'No payment gateways capable of processing automatic subscription payments are enabled. If you would like to process automatic payments, we recommend the %1$sfree Stripe addon%2$s.', 'storeengine' ),
						'<strong><a href="' . esc_url( admin_url( 'admin.php?page=storeengine-addons' ) ) . '">',
						'</a></strong>'
					),
			] );
		}
	}

	public function handle_admin_request() {
		if (
			isset( $_REQUEST['action'], $_REQUEST['security'], $_REQUEST['notice'] ) &&
			self::$action === sanitize_text_field( $_REQUEST['action'] ) &&
			wp_verify_nonce( $_REQUEST['security'], 'storeengine_nonce' ) &&
			! empty( $_REQUEST['notice'] )
		) {
			self::remove_notice( sanitize_text_field( $_REQUEST['notice'] ) );
			wp_send_json_success();
		}
	}

	public function add_notice_data( array $data ): array {
		return array_merge( $data, [ 'admin_notices' => array_values( self::$notices ) ] );
	}

	public function render_notices() {
		global $pagenow;
		if ( ! empty( self::$notices ) ) {
			foreach ( self::$notices as $notice ) {
				$classes = array_merge(
					[
						'storeengine-notice',
						'storeengine-notice-' . $notice['type'],
						'notice',
						str_replace( [ '.' ], '-', $pagenow ),
						$notice['key'],

					],
					$notice['classes']
				);

				switch ( $notice['type'] ) {
					case 'error':
						$classes[] = 'notice-error';
						break;
					case 'warning':
						$classes[] = 'notice-warning';
						break;
					case 'success':
						$classes[] = 'notice-success';
						break;
					case 'info':
					default:
						$classes[] = 'notice-info';
						break;
				}

				if ( $notice['large'] ) {
					$classes[] = 'notice-large';
				}

				if ( $notice['alt'] ) {
					$classes[] = 'notice-alt';
				}

				if ( $notice['dismissible'] ) {
					$classes[] = 'is-dismissible';
				}

				$classes = implode( ' ', $classes );

				include __DIR__ . '/notice-html.php';
			}

			add_action( 'admin_footer', [ $this, 'add_notice_script' ], 100 );
		}
	}

	public function add_notice_script() {
		?>
		<script>
			(
				( $ ) => {
					$( document ).on( 'click', '.storeengine-notice-close.notice-dismiss', function( event ) {
						event.preventDefault();
						const notice = $( this ).data( 'notice' );
						$.post( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
							notice: notice,
							action: '<?php echo esc_js( self::$action ); ?>',
							security: '<?php echo esc_js( wp_create_nonce( 'storeengine_nonce' ) ); ?>',
						}, function( response ) {
							$( '#storeengine-notice-' + notice ).remove();
						} );
					} );
				}
			)( jQuery );
		</script>
		<?php
	}

	/**
	 * Add notice.
	 *
	 * @param string $notice_name
	 * @param array $args {
	 *
	 * @type string $type Notice type.
	 * @type string $icon Icon.
	 * @type bool $alt Render alt style (wp notice-alt class)
	 * @type string|string[] $classes Extra class.
	 * @type string $title Optional Title.
	 * @type string $message The Message.
	 * @type string $button_text Extra button text/label
	 * @type string $button_action Extra button action url
	 * @type bool $dismissible Show dismissible button.
	 * }
	 *
	 * @return void
	 */
	public static function add_notice( string $notice_name, array $args ) {
		$args = wp_parse_args( $args, [
			'key'           => $notice_name,
			'type'          => self::TYPE_INFO,
			'icon'          => 'info',
			'alt'           => false,
			'large'         => false,
			'classes'       => '',
			'title'         => '',
			'message'       => '',
			'button_text'   => '',
			'button_action' => '',
			'dismissible'   => false,
		] );

		$types = [ self::TYPE_INFO, self::TYPE_SUCCESS, self::TYPE_WARNING, self::TYPE_ERROR ];

		if ( ! in_array( $args['type'], $types, true ) ) {
			$args['type'] = 'info';
		}

		$args['alt']           = (bool) $args['alt'];
		$args['large']         = (bool) $args['large'];
		$args['dismissible']   = (bool) $args['dismissible'];
		$args['classes']       = $args['classes'] && ! is_array( $args['classes'] ) ? explode( ' ', $args['classes'] ) : [];
		$args['title']         = esc_html( $args['title'] );
		$args['message']       = wp_kses_post( wpautop( $args['message'] ) );
		$args['button_text']   = esc_html( $args['button_text'] );
		$args['button_action'] = sanitize_url( $args['button_action'] );
		$args['has_buttons']   = $args['dismissible'] || ( $args['button_text'] && $args['button_action'] );

		self::$notices[ $notice_name ] = $args;
	}

	public static function remove_notice( string $notice_name ) {
		if ( isset( self::$notices[ $notice_name ] ) ) {
			unset( self::$notices[ $notice_name ] );
		}
	}

	public static function get_notices(): array {
		return self::$notices;
	}

	public static function remove_all_notices() {
		self::$notices = [];
	}

	public static function has_notice( string $notice_name ): bool {
		return isset( self::$notices[ $notice_name ] );
	}

	public function add_permalink_notice() {
		if ( self::has_notice( 'update_permalink_settings' ) ) {
			return;
		}

		self::add_notice( 'update_permalink_settings', [
			'type'          => 'warning',
			'message'       => __( 'Your permalink settings is set to <code>plain</code>. Please update your permalink settings. StoreEngine works better with search engine friendly permalink.', 'storeengine' ),
			'button_text'   => __( 'Update Permalink', 'storeengine' ),
			'button_action' => admin_url( 'options-permalink.php' ),
		] );
	}
}
