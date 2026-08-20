<?php
/**
 * Plugin Name: Chilexpress OT en ficha de pedido
 * Version: 1.0.0
 * Description: Botones Generar OT / Imprimir OT en el detalle HPOS. No modifica chilexpress-oficial.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Order_Ot_Ui {
	public static function boot() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'woocommerce_order_actions_end', array( __CLASS__, 'actions_end' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'item_buttons' ) );
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'order_actions' ), 20, 2 );
		add_action( 'woocommerce_order_action_cxp_generar_ot', array( __CLASS__, 'handle_generar' ) );
		add_action( 'woocommerce_order_action_cxp_imprimir_ot', array( __CLASS__, 'handle_imprimir' ) );
		add_filter( 'woocommerce_admin_order_actions', array( __CLASS__, 'list_actions' ), 110, 2 );
		add_action( 'admin_head', array( __CLASS__, 'styles' ) );
		add_action( 'admin_notices', array( __CLASS__, 'list_notices' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'bulk_to_form' ), 1, 3 );
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ) );
	}

	public static function metabox() {
		if ( ! function_exists( 'wc_get_page_screen_id' ) ) {
			return;
		}
		$screen = wc_get_page_screen_id( 'shop-order' );
		if ( ! $screen ) {
			$screen = 'shop_order';
		}
		add_meta_box(
			'cxp-chilexpress-ot',
			'Chilexpress — Orden de transporte',
			array( __CLASS__, 'render_metabox' ),
			$screen,
			'side',
			'high'
		);
	}

	public static function render_metabox( $post_or_order ) {
		$order = self::as_order( $post_or_order );
		if ( ! $order ) {
			echo '<p>No hay pedido.</p>';
			return;
		}
		self::render_buttons( $order, 'metabox' );
	}

	public static function actions_end( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		echo '<li class="wide cxp-ot-actions">';
		self::render_buttons( $order, 'sidebar' );
		echo '</li>';
	}

	public static function item_buttons( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		self::render_buttons( $order, 'items' );
	}

	public static function order_actions( $actions, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}
		$actions['cxp_generar_ot'] = 'Generar OT Chilexpress';
		if ( self::can_print( $order ) ) {
			$actions['cxp_imprimir_ot'] = 'Imprimir OT Chilexpress';
		}
		return $actions;
	}

	public static function handle_generar( $order ) {
		self::redirect_ot( $order, 'generar_ot' );
	}

	public static function handle_imprimir( $order ) {
		self::redirect_ot( $order, 'imprimir_ot' );
	}

	public static function list_actions( $actions, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}
		if ( ! self::looks_like_chilexpress( $order ) ) {
			return $actions;
		}
		if ( empty( $actions['generar_ot'] ) ) {
			$actions['generar_ot'] = array(
				'url'    => self::ot_url( $order->get_id(), 'generar_ot' ),
				'name'   => 'Generar OT',
				'action' => 'generar_ot',
			);
		}
		if ( self::can_print( $order ) && empty( $actions['imprimir_ot'] ) ) {
			$actions['imprimir_ot'] = array(
				'url'    => self::ot_url( $order->get_id(), 'imprimir_ot' ),
				'name'   => 'Imprimir OT',
				'action' => 'imprimir_ot',
			);
		}
		return $actions;
	}

	public static function styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id = $screen ? (string) $screen->id : '';
		if ( false === strpos( $id, 'wc-orders' ) && 'shop_order' !== $id ) {
			return;
		}
		echo '<style>
			.cxp-ot-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0 0!important}
			.cxp-ot-btn{display:inline-flex!important;align-items:center;gap:6px}
			a.button.cxp-ot-btn-generar,button.cxp-ot-btn-generar{
				background:#854d0e!important;border-color:#ca8a04!important;color:#fef08a!important;font-weight:700
			}
			a.button.cxp-ot-btn-generar:hover{background:#a16207!important;color:#fff!important}
			a.button.cxp-ot-btn-print{font-weight:700}
			#cxp-chilexpress-ot .inside{padding:10px 12px}
			.cxp-ot-meta{margin:8px 0 0;color:#646970;font-size:12px;line-height:1.4}
			.cxp-ot-meta strong{color:#1d2327}
			.cxp-ot-warn{margin:8px 0 0;color:#b32d2e}
			.wc-orders-list-table .wc-action-button-generar_ot,
			.wc-orders-list-table .wc-action-button-imprimir_ot{
				width:auto!important;padding:0 8px!important;overflow:visible!important;
				text-indent:0!important;font-size:11px!important;font-weight:700
			}
			.wc-orders-list-table .wc-action-button-generar_ot::after,
			.wc-orders-list-table .wc-action-button-imprimir_ot::after{content:none!important}
			.wc-orders-list-table .wc-action-button-generar_ot::before{content:"Generar OT";font-family:inherit!important}
			.wc-orders-list-table .wc-action-button-imprimir_ot::before{content:"Imprimir OT";font-family:inherit!important}
			.wc-orders-list-table .wc-action-button-generar_ot{
				background:#854d0e!important;border-color:#ca8a04!important;color:#fef08a!important
			}
		</style>';
	}

	public static function list_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id ) {
			return;
		}
		if ( ! empty( $_GET['cxp_ot_none'] ) || ( isset( $_GET['generar_multiples_ot'] ) && isset( $_GET['processed_count'] ) && '0' === (string) $_GET['processed_count'] ) ) {
			echo '<div class="notice notice-error"><p><strong>No se generó ninguna OT.</strong> En esta lista hay que <em>marcar el checkbox</em> del pedido (el de la izquierda de #29) y recién ahí Acciones masivas → <em>Generar Multiples OT</em> → Aplicar. Más simple: entra al pedido con <em>Editar</em> y pulsa el botón amarillo <em>Generar OT</em>.</p></div>';
		}
	}

	public static function bulk_to_form( $redirect, $action, $ids ) {
		if ( 'generar_multiples_ot' !== $action ) {
			return $redirect;
		}
		$order_id = 0;
		foreach ( (array) $ids as $id ) {
			$id    = absint( $id );
			$order = $id ? wc_get_order( $id ) : null;
			if ( $order instanceof WC_Order ) {
				$order_id = $id;
				break;
			}
		}
		if ( ! $order_id ) {
			return add_query_arg( 'cxp_ot_none', '1', $redirect );
		}
		wp_safe_redirect( self::ot_url( $order_id, 'generar_ot' ) );
		exit;
	}

	public static function console_panel() {
		$order = self::current_edit_order();
		?>
		<div id="cxp-dbg-ot">
			<p><strong>Chilexpress OT</strong> — el plugin oficial solo pone el icono en la lista. Aquí está también el detalle.</p>
			<?php if ( $order ) : ?>
				<p>Pedido actual #<?php echo esc_html( (string) $order->get_order_number() ); ?> · <?php echo esc_html( self::status_label( $order ) ); ?></p>
				<div class="cxp-dbg-ot-actions">
					<?php self::render_buttons( $order, 'console' ); ?>
				</div>
			<?php else : ?>
				<p>Abre un pedido (Editar) para ver Generar OT / Imprimir OT aquí, o usa los botones de la ficha.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_buttons( WC_Order $order, $context ) {
		$plugin_ok = self::chilexpress_active();
		$generar   = self::ot_url( $order->get_id(), 'generar_ot' );
		$imprimir  = self::ot_url( $order->get_id(), 'imprimir_ot' );
		$can_print = self::can_print( $order );
		$class_g   = 'console' === $context ? 'cxp-dbg-btn cxp-dbg-btn-ot' : 'button button-primary cxp-ot-btn cxp-ot-btn-generar';
		$class_p   = 'console' === $context ? 'cxp-dbg-btn cxp-dbg-btn-ot' : 'button cxp-ot-btn cxp-ot-btn-print';

		if ( ! $plugin_ok ) {
			echo '<p class="cxp-ot-warn">Chilexpress Oficial no está activo. Actívalo para generar la OT.</p>';
		}

		echo '<a class="' . esc_attr( $class_g ) . '" href="' . esc_url( $generar ) . '">Generar OT</a> ';
		if ( $can_print ) {
			echo '<a class="' . esc_attr( $class_p ) . '" href="' . esc_url( $imprimir ) . '">Imprimir OT</a>';
		}

		if ( in_array( $context, array( 'items', 'console' ), true ) ) {
			return;
		}

		echo '<p class="cxp-ot-meta">';
		echo 'Estado OT: <strong>' . esc_html( self::status_label( $order ) ) . '</strong>';
		$cert = (string) $order->get_meta( 'certificateNumber' );
		if ( $cert ) {
			echo ' · Certificado: <strong>' . esc_html( $cert ) . '</strong>';
		}
		$nums = self::transport_numbers( $order );
		if ( $nums ) {
			echo ' · Tracking: <strong>' . esc_html( implode( ', ', $nums ) ) . '</strong>';
		}
		echo '</p>';
	}

	private static function redirect_ot( $order, $action ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		wp_safe_redirect( self::ot_url( $order->get_id(), $action ) );
		exit;
	}

	private static function ot_url( $order_id, $action ) {
		$action = 'imprimir_ot' === $action ? 'imprimir_ot' : 'generar_ot';
		return wp_nonce_url(
			admin_url( 'admin.php?page=chilexpress_woo_oficial_generar_ot&action=' . $action . '&order_id=' . absint( $order_id ) ),
			'generar-ot'
		);
	}

	private static function can_print( WC_Order $order ) {
		if ( 'created' !== (string) $order->get_meta( 'ot_status' ) ) {
			return false;
		}
		if ( self::transport_numbers( $order ) ) {
			return true;
		}
		if ( absint( $order->get_meta( 'labelsCount' ) ) > 0 ) {
			return true;
		}
		return (string) $order->get_meta( 'certificateNumber' ) !== '';
	}

	private static function transport_numbers( WC_Order $order ) {
		$nums = $order->get_meta( 'transportOrderNumbers' );
		if ( is_array( $nums ) ) {
			return array_values( array_filter( array_map( 'strval', $nums ) ) );
		}
		if ( is_string( $nums ) && $nums !== '' ) {
			return array( $nums );
		}
		return array();
	}

	private static function status_label( WC_Order $order ) {
		$status = (string) $order->get_meta( 'ot_status' );
		return $status !== '' ? $status : 'sin OT';
	}

	private static function looks_like_chilexpress( WC_Order $order ) {
		if ( $order->has_shipping_method( 'chilexpress_woo_oficial' ) || $order->has_shipping_method( 'cxp_debug_cxp' ) ) {
			return true;
		}
		if ( $order->get_meta( 'ot_status' ) || $order->get_meta( 'certificateNumber' ) ) {
			return true;
		}
		foreach ( $order->get_shipping_methods() as $item ) {
			$id    = (string) $item->get_method_id();
			$title = (string) $item->get_method_title();
			if ( false !== stripos( $id, 'chilexpress' ) || false !== stripos( $title, 'chilexpress' ) || false !== stripos( $title, 'CHEX' ) || false !== stripos( $title, 'PREX' ) ) {
				return true;
			}
			if ( $item->get_meta( 'serviceTypeCode' ) ) {
				return true;
			}
		}
		return false;
	}

	private static function chilexpress_active() {
		return defined( 'CHILEXPRESS_WOO_OFICIAL_VERSION' ) || function_exists( 'is_plugin_active' ) && is_plugin_active( 'chilexpress-oficial/chilexpress-woo-oficial.php' );
	}

	private static function as_order( $post_or_order ) {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}
		if ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
			return wc_get_order( $post_or_order->ID );
		}
		return null;
	}

	private static function current_edit_order() {
		if ( ! is_admin() ) {
			return null;
		}
		$page   = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		$action = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );
		$id     = absint( $_GET['id'] ?? $_GET['post'] ?? 0 );
		if ( 'wc-orders' === $page && 'edit' === $action && $id ) {
			$order = wc_get_order( $id );
			return $order instanceof WC_Order ? $order : null;
		}
		if ( $id && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'shop_order' === $screen->id ) {
				$order = wc_get_order( $id );
				return $order instanceof WC_Order ? $order : null;
			}
		}
		return null;
	}
}

Cxp_Order_Ot_Ui::boot();
