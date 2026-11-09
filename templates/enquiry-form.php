<?php
/**
 * Enquiry form block. Variables: $attrs, $trip, $wrapper. Copy to yourtheme/journeyline/enquiry-form.php to change it.
 * The form works without the page's script too (it posts to the same address and shows a plain answer).
 *
 * @package Journeyline
 */

use Journeyline\Departures;
use Journeyline\Enquiries;

defined( 'ABSPATH' ) || exit;

$uid      = wp_unique_id( 'jl-enq-' );
$deps     = $trip ? Departures::upcoming( array( 'trip' => $trip ) ) : array();
$pre      = isset( $_GET['departure'] ) ? absint( $_GET['departure'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only preselects an option.
$button   = '' !== (string) $attrs['button'] ? (string) $attrs['button'] : __( 'Send', 'journeyline' );
$privacy  = get_privacy_policy_url();
$field    = function ( $name, $label, $type = 'text', $req = false, $auto = '' ) use ( $uid ) {
	$id = $uid . '-' . $name;
	printf(
		'<p class="jl-field"><label for="%1$s">%2$s%3$s</label><input id="%1$s" name="%4$s" type="%5$s"%6$s%7$s><span class="jl-error" id="%1$s-e" aria-live="polite"></span></p>',
		esc_attr( $id ),
		esc_html( $label ),
		$req ? '' : ' <span class="jl-optional">' . esc_html__( '(optional)', 'journeyline' ) . '</span>',
		esc_attr( $name ),
		esc_attr( $type ),
		$req ? ' required aria-required="true"' : '',
		$auto ? ' autocomplete="' . esc_attr( $auto ) . '"' : ''
	);
};

echo '<div ' . $wrapper . ' id="enquire">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
echo '<form class="jl-form" method="post" action="' . esc_url( rest_url( Enquiries::ROUTE . '/enquiry' ) ) . '" data-jl-enquiry novalidate>';
echo '<input type="hidden" name="form" value="' . esc_attr( $trip ? 'trip' : 'enquiry' ) . '"><input type="hidden" name="trip" value="' . esc_attr( (string) $trip ) . '"><input type="hidden" name="_t" value="' . esc_attr( (string) time() ) . '">';
echo '<p class="jl-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'journeyline' ) . '<input type="text" name="_hp" tabindex="-1" autocomplete="off"></label></p>';
$field( 'name', __( 'Your name', 'journeyline' ), 'text', true, 'name' );
$field( 'email', __( 'Email', 'journeyline' ), 'email', true, 'email' );
if ( ! empty( $attrs['showTopic'] ) ) {
	$field( 'topic', __( 'What is it about?', 'journeyline' ) );
}
$field( 'country', __( 'Where you live', 'journeyline' ), 'text', false, 'country-name' );
if ( $deps ) {
	$sid = $uid . '-departure';
	echo '<p class="jl-field"><label for="' . esc_attr( $sid ) . '">' . esc_html__( 'Preferred date', 'journeyline' ) . '</label><select id="' . esc_attr( $sid ) . '" name="departure"><option value="">' . esc_html__( 'Another date, or privately', 'journeyline' ) . '</option>';
	foreach ( $deps as $d ) {
		printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $d, selected( $pre, $d, false ), esc_html( wp_date( 'j M Y', strtotime( (string) get_post_meta( $d, 'jrnl_start', true ) ) ) . ' · ' . Departures::status( $d )['label'] ) );
	}
	echo '</select></p>';
}
if ( ! empty( $attrs['showTravellers'] ) ) {
	$tid = $uid . '-travellers';
	echo '<p class="jl-field"><label for="' . esc_attr( $tid ) . '">' . esc_html__( 'Travellers', 'journeyline' ) . '</label><input id="' . esc_attr( $tid ) . '" name="travellers" type="number" min="1" max="50" step="1" value="2" inputmode="numeric"></p>';
}
$mid = $uid . '-message';
echo '<p class="jl-field jl-wide"><label for="' . esc_attr( $mid ) . '">' . esc_html__( 'Your message', 'journeyline' ) . ' <span class="jl-optional">' . esc_html__( '(optional)', 'journeyline' ) . '</span></label><textarea id="' . esc_attr( $mid ) . '" name="message" rows="4"></textarea></p>';
$cid = $uid . '-consent';
echo '<p class="jl-field jl-wide jl-consent"><input id="' . esc_attr( $cid ) . '" name="consent" type="checkbox" value="1" required aria-required="true"><label for="' . esc_attr( $cid ) . '">' . esc_html__( 'I agree that you can use these details to reply to me.', 'journeyline' );
if ( $privacy ) {
	echo ' <a href="' . esc_url( $privacy ) . '">' . esc_html__( 'Privacy policy', 'journeyline' ) . '</a>';
}
echo '</label><span class="jl-error" id="' . esc_attr( $cid ) . '-e" aria-live="polite"></span></p>';
echo '<p class="jl-actions jl-wide"><button type="submit" class="jl-submit wp-element-button">' . esc_html( $button ) . '</button><span class="jl-status" role="status" aria-live="polite"></span></p>';
echo '</form><div class="jl-sent" hidden tabindex="-1"><p class="jl-sent-title">' . esc_html__( 'Thank you. Your message is on its way.', 'journeyline' ) . '</p><p>' . esc_html__( 'We usually reply within 24 hours.', 'journeyline' ) . '</p></div>';
echo '</div>';
