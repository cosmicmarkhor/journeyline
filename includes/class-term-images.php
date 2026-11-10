<?php
/**
 * A photo for each destination and trip style, chosen from the media library.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Term photos.
 */
final class Term_Images {

	const META = 'jrnl_image';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		foreach ( array( Content_Types::DESTINATION, Content_Types::STYLE ) as $tax ) {
			add_action( $tax . '_add_form_fields', array( __CLASS__, 'add_field' ) );
			add_action( $tax . '_edit_form_fields', array( __CLASS__, 'edit_field' ) );
			add_action( 'created_' . $tax, array( __CLASS__, 'save' ) );
			add_action( 'edited_' . $tax, array( __CLASS__, 'save' ) );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Register the meta.
	 */
	public static function register() {
		foreach ( array( Content_Types::DESTINATION, Content_Types::STYLE ) as $tax ) {
			register_term_meta( $tax, self::META, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint' ) );
		}
	}

	/**
	 * Image ID for a term.
	 *
	 * @param int $term Term ID.
	 * @return int
	 */
	public static function get( $term ) {
		return (int) get_term_meta( $term, self::META, true );
	}

	/**
	 * Media picker on the term screens.
	 *
	 * @param string $hook Screen.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			"jQuery(function($){ $(document).on('click','.jl-term-pick',function(e){ e.preventDefault(); var box=$(this).closest('.jl-term-image'); var f=wp.media({title:box.data('title'),multiple:false,library:{type:'image'}}); f.on('select',function(){ var a=f.state().get('selection').first().toJSON(); box.find('input').val(a.id); box.find('.jl-term-prev').html('<img src=\"'+(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url)+'\" alt=\"\" style=\"max-width:240px;height:auto;display:block;margin-bottom:8px\">'); box.find('.jl-term-clear').show(); }); f.open(); }); $(document).on('click','.jl-term-clear',function(e){ e.preventDefault(); var box=$(this).closest('.jl-term-image'); box.find('input').val(''); box.find('.jl-term-prev').empty(); $(this).hide(); }); });"
		);
	}

	/**
	 * The picker markup.
	 *
	 * @param int $id Current image.
	 * @return string
	 */
	private static function picker( $id ) {
		$prev = $id ? wp_get_attachment_image( $id, 'medium', false, array( 'style' => 'max-width:240px;height:auto;display:block;margin-bottom:8px' ) ) : '';
		return '<div class="jl-term-image" data-title="' . esc_attr__( 'Choose a photo', 'journeyline' ) . '"><div class="jl-term-prev">' . $prev . '</div>'
			. wp_nonce_field( 'journeyline_term_image', 'journeyline_term_image_nonce', true, false )
			. '<input type="hidden" name="jrnl_image" value="' . esc_attr( $id ? (string) $id : '' ) . '">'
			. '<button type="button" class="button jl-term-pick">' . esc_html__( 'Choose a photo', 'journeyline' ) . '</button> '
			. '<button type="button" class="button-link jl-term-clear"' . ( $id ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove', 'journeyline' ) . '</button>'
			. '<p class="description">' . esc_html__( 'Shown on cards and at the top of its page. Remember to fill in the photo’s alt text in the media library.', 'journeyline' ) . '</p></div>';
	}

	/**
	 * Field on "Add new".
	 */
	public static function add_field() {
		echo '<div class="form-field"><label>' . esc_html__( 'Photo', 'journeyline' ) . '</label>' . self::picker( 0 ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
	}

	/**
	 * Field on "Edit".
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function edit_field( $term ) {
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Photo', 'journeyline' ) . '</th><td>' . self::picker( self::get( $term->term_id ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
	}

	/**
	 * Save.
	 *
	 * @param int $term_id Term.
	 */
	public static function save( $term_id ) {
		if ( ! isset( $_POST['journeyline_term_image_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['journeyline_term_image_nonce'] ) ), 'journeyline_term_image' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$id = isset( $_POST['jrnl_image'] ) ? absint( $_POST['jrnl_image'] ) : 0;
		if ( $id ) {
			update_term_meta( $term_id, self::META, $id );
		} else {
			delete_term_meta( $term_id, self::META );
		}
	}
}
