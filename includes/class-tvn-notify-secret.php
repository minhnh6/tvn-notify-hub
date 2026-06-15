<?php
/**
 * Utility to mask sensitive values (webhook URL, bot token...) when
 * displaying them again in the admin form.
 *
 * Principle: NEVER print the real secret back into HTML. Leave the field empty,
 * only hint at the last few characters via a placeholder. When saving an empty
 * field -> keep the existing value.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Secret {

	/**
	 * Hint in the form "••••••••WXYZ" (reveal the last few characters so the admin can recognize it).
	 *
	 * @param string $value
	 * @param int    $visible Number of trailing characters to display.
	 * @return string Empty if there is no value.
	 */
	public static function hint( $value, $visible = 4 ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		$dots = '••••••••';
		if ( strlen( $value ) <= $visible ) {
			return $dots;
		}
		return $dots . substr( $value, -$visible );
	}

	/**
	 * Placeholder for a secret field: show the hint if a value exists, otherwise use the fallback.
	 *
	 * @param string $value    The saved value.
	 * @param string $fallback Default placeholder when there is nothing.
	 * @return string
	 */
	public static function placeholder( $value, $fallback = '' ) {
		$hint = self::hint( $value );
		if ( '' === $hint ) {
			return $fallback;
		}
		/* translators: %s: the last few characters of the saved value */
		return sprintf( __( 'Saved (%s) — leave blank to keep it', 'tvn-notify-hub' ), $hint );
	}

	/**
	 * Decide the secret value when saving: empty submit -> keep the existing value; otherwise use the submitted one.
	 *
	 * @param string $submitted The value the user just entered.
	 * @param string $existing  The currently saved value.
	 * @return string
	 */
	public static function resolve( $submitted, $existing ) {
		$submitted = is_string( $submitted ) ? trim( $submitted ) : '';
		return ( '' === $submitted ) ? (string) $existing : $submitted;
	}
}
