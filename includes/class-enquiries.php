<?php
/**
 * Enquiries: a public form endpoint, private storage in WordPress, and an email to the company.
 *
 * Spam protection without paid services or puzzles: a hidden honeypot field, a minimum time
 * on the page, and a limit of five enquiries an hour from one address. Works with page caching
 * (no nonce on the public form).
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Enquiry storage and handling.
 */
final class Enquiries {

	const TYPE  = 'jrnl_enquiry';
	const ROUTE = 'journeyline/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'add_meta_boxes_' . self::TYPE, array( __CLASS__, 'box' ) );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Private post type: visible in the admin only.
	 */
	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Enquiries', 'journeyline' ),
					'singular_name' => __( 'Enquiry', 'journeyline' ),
					'edit_item'     => __( 'Enquiry', 'journeyline' ),
					'all_items'     => __( 'Enquiries', 'journeyline' ),
					'not_found'     => __( 'No enquiries yet.', 'journeyline' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . Content_Types::TRIP,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'rewrite'         => false,
			)
		);
	}

	/**
	 * The form posts here.
	 */
	public static function routes() {
		register_rest_route(
			self::ROUTE,
			'/enquiry',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Fields every enquiry may carry: key => [label, sanitiser]. Add-ons can add more.
	 *
	 * @return array
	 */
	public static function fields() {
		$fields = array(
			'name'      => array( __( 'Name', 'journeyline' ), 'sanitize_text_field' ),
			'email'     => array( __( 'Email', 'journeyline' ), 'sanitize_email' ),
			'phone'     => array( __( 'Phone or WhatsApp', 'journeyline' ), 'sanitize_text_field' ),
			'country'   => array( __( 'Lives in', 'journeyline' ), 'sanitize_text_field' ),
			'topic'     => array( __( 'About', 'journeyline' ), 'sanitize_text_field' ),
			'trip'      => array( __( 'Trip', 'journeyline' ), 'absint' ),
			'departure' => array( __( 'Departure', 'journeyline' ), 'absint' ),
			'travellers' => array( __( 'Travellers', 'journeyline' ), 'absint' ),
			'reply_by'  => array( __( 'Reply by', 'journeyline' ), 'sanitize_text_field' ),
			'message'   => array( __( 'Message', 'journeyline' ), 'sanitize_textarea_field' ),
		);
		return apply_filters( 'journeyline_enquiry_fields', $fields );
	}

	/**
	 * Receive one enquiry.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function receive( $req ) {
		$p = (array) $req->get_json_params();
		if ( ! $p ) {
			$p = (array) $req->get_body_params();
		}

		// Spam checks. Bots that fill the hidden field, or post within three seconds, get a quiet "thank you".
		$started = isset( $p['_t'] ) ? (int) $p['_t'] : 0;
		if ( ! empty( $p['_hp'] ) || ( $started && ( time() - $started ) < 3 ) ) {
			return new \WP_REST_Response( array( 'ok' => true ), 200 );
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'jl_enq_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 5 ) {
			return new \WP_Error( 'journeyline_busy', __( 'We have had several messages from you in the last hour. Please try again a little later, or message us on WhatsApp.', 'journeyline' ), array( 'status' => 429 ) );
		}

		$data = array();
		foreach ( self::fields() as $k => $f ) {
			if ( isset( $p[ $k ] ) && '' !== $p[ $k ] ) {
				$data[ $k ] = is_callable( $f[1] ) ? call_user_func( $f[1], is_array( $p[ $k ] ) ? implode( ', ', array_map( 'strval', $p[ $k ] ) ) : (string) $p[ $k ] ) : '';
			}
		}
		$extra = isset( $p['details'] ) && is_array( $p['details'] ) ? self::clean_details( $p['details'] ) : array();

		$errors = array();
		if ( empty( $data['name'] ) ) {
			$errors['name'] = __( 'Enter your name so we know who to reply to.', 'journeyline' );
		}
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			$errors['email'] = __( 'Enter an email address like name@example.com.', 'journeyline' );
		}
		if ( empty( $p['consent'] ) ) {
			$errors['consent'] = __( 'Tick the box so we can reply to you.', 'journeyline' );
		}
		if ( $errors ) {
			return new \WP_Error( 'journeyline_invalid', __( 'Some details need another look.', 'journeyline' ), array( 'status' => 400, 'fields' => $errors ) );
		}

		$form  = isset( $p['form'] ) ? sanitize_key( (string) $p['form'] ) : 'enquiry';
		$about = ! empty( $data['trip'] ) ? get_the_title( (int) $data['trip'] ) : ( $data['topic'] ?? '' );
		$id    = wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				/* translators: 1: person's name, 2: what it is about */
				'post_title'  => trim( sprintf( __( '%1$s · %2$s', 'journeyline' ), $data['name'], $about ? $about : __( 'General enquiry', 'journeyline' ) ), ' ·' ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'journeyline_save', __( 'Sorry, your message could not be saved. Please try again or email us.', 'journeyline' ), array( 'status' => 500 ) );
		}
		update_post_meta( $id, 'jrnl_form', $form );
		update_post_meta( $id, 'jrnl_data', $data );
		update_post_meta( $id, 'jrnl_details', $extra );
		update_post_meta( $id, 'jrnl_page', isset( $p['page'] ) ? esc_url_raw( (string) $p['page'] ) : '' );
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		self::notify( $id, $data, $extra, $form );
		do_action( 'journeyline_enquiry_received', $id, $data, $extra, $form );

		return new \WP_REST_Response( array( 'ok' => true, 'id' => $id ), 201 );
	}

	/**
	 * Clean extra details sent by add-ons (label => value pairs, values may be lists).
	 *
	 * @param array $details Raw details.
	 * @return array
	 */
	private static function clean_details( $details ) {
		$out = array();
		foreach ( array_slice( $details, 0, 60, true ) as $label => $value ) {
			$label = sanitize_text_field( (string) $label );
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', array_map( 'strval', $value ) ) );
			} else {
				$value = sanitize_textarea_field( (string) $value );
			}
			if ( '' !== $label && '' !== $value ) {
				$out[ $label ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Email the company. Reply-To is the guest, so pressing Reply answers them.
	 *
	 * @param int    $id    Enquiry ID.
	 * @param array  $data  Main fields.
	 * @param array  $extra Extra details.
	 * @param string $form  Form name.
	 */
	private static function notify( $id, $data, $extra, $form ) {
		$to    = (string) Settings::get( 'enquiry_email' );
		$to    = is_email( $to ) ? $to : (string) get_option( 'admin_email' );
		$lines = array();
		foreach ( self::fields() as $k => $f ) {
			if ( isset( $data[ $k ] ) && '' !== (string) $data[ $k ] ) {
				$v       = in_array( $k, array( 'trip', 'departure' ), true ) ? get_the_title( (int) $data[ $k ] ) : $data[ $k ];
				$lines[] = $f[0] . ': ' . $v;
			}
		}
		foreach ( $extra as $label => $value ) {
			$lines[] = $label . ': ' . $value;
		}
		$lines[] = '';
		$lines[] = __( 'Open in WordPress:', 'journeyline' ) . ' ' . admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
		/* translators: 1: site name, 2: enquiry title */
		$subject = sprintf( __( '[%1$s] New enquiry: %2$s', 'journeyline' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), get_the_title( $id ) );
		$headers = array( 'Reply-To: ' . sanitize_text_field( $data['name'] ) . ' <' . $data['email'] . '>' );
		wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}

	/**
	 * Read-only view of one enquiry.
	 */
	public static function box() {
		add_meta_box( 'journeyline-enquiry', __( 'What they sent', 'journeyline' ), array( __CLASS__, 'render' ), self::TYPE, 'normal', 'high' );
	}

	/**
	 * Draw the enquiry.
	 *
	 * @param \WP_Post $post Enquiry.
	 */
	public static function render( $post ) {
		$data  = (array) get_post_meta( $post->ID, 'jrnl_data', true );
		$extra = (array) get_post_meta( $post->ID, 'jrnl_details', true );
		echo '<table class="widefat striped"><tbody>';
		foreach ( self::fields() as $k => $f ) {
			if ( isset( $data[ $k ] ) && '' !== (string) $data[ $k ] ) {
				$v = in_array( $k, array( 'trip', 'departure' ), true ) ? get_the_title( (int) $data[ $k ] ) : $data[ $k ];
				echo '<tr><th scope="row" style="width:180px">' . esc_html( $f[0] ) . '</th><td>' . nl2br( esc_html( (string) $v ) ) . '</td></tr>';
			}
		}
		foreach ( $extra as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( (string) $label ) . '</th><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>';
		}
		$page = (string) get_post_meta( $post->ID, 'jrnl_page', true );
		if ( $page ) {
			echo '<tr><th scope="row">' . esc_html__( 'Sent from', 'journeyline' ) . '</th><td><a href="' . esc_url( $page ) . '">' . esc_html( $page ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
		if ( ! empty( $data['email'] ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( 'mailto:' . $data['email'] ) . '">' . esc_html__( 'Reply by email', 'journeyline' ) . '</a></p>';
		}
	}

	/**
	 * List columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function columns( $cols ) {
		return array(
			'cb'        => $cols['cb'] ?? '',
			'title'     => __( 'Enquiry', 'journeyline' ),
			'jrnl_from' => __( 'Email', 'journeyline' ),
			'jrnl_form' => __( 'Form', 'journeyline' ),
			'date'      => __( 'Received', 'journeyline' ),
		);
	}

	/**
	 * Fill list columns.
	 *
	 * @param string $col Column.
	 * @param int    $id  Enquiry ID.
	 */
	public static function column( $col, $id ) {
		$data = (array) get_post_meta( $id, 'jrnl_data', true );
		if ( 'jrnl_from' === $col ) {
			echo esc_html( (string) ( $data['email'] ?? '' ) );
		} elseif ( 'jrnl_form' === $col ) {
			echo esc_html( (string) get_post_meta( $id, 'jrnl_form', true ) );
		}
	}
}
