<?php
/**
 * Google Map Template
 *
 * @package Google Maps/Templates
 */

$with_directions = ( $settings['listingID'] > 0 && $settings['show_directions'] );
$container_class = $with_directions ? 'wpbdp-map-container-with-directions' : 'wpbdp-map-container';

?>
<div class="<?php echo esc_attr( $container_class ); ?> cf" data-breakpoints='{"small": [0,550]}' data-breakpoints-class-prefix="<?php echo esc_attr( $container_class ); ?>">

<div id="wpbdp-map-<?php echo esc_attr( $settings['map_uid'] ); ?>" class="wpbdp-map wpbdp-google-map <?php echo esc_attr( $settings['map_size'] ); ?>" style="<?php echo esc_attr( $settings['map_style_attr'] ); ?>"></div>

<?php if ( $with_directions ) : ?>
<div class="wpbdp-map-directions-config-container">
  <div class="wpbdp-map-directions-config">
    <input type="hidden" name="listing_title" value="<?php echo esc_attr( get_the_title( $settings['listingID'] ) ); ?>" />
    <h4><?php esc_html_e( 'Directions to listing', 'wpbdp-googlemaps' ); ?></h4>

    <div class="directions-from">
        <label><?php esc_html_e( 'From:', 'wpbdp-googlemaps' ); ?></label>
        <label>
            <input type="radio" name="from_mode" value="current" checked="checked" />
			<?php esc_html_e( 'Current location', 'wpbdp-googlemaps' ); ?>
        </label>
        <label>
            <input type="radio" name="from_mode" value="address" />
			<?php esc_html_e( 'Specific Address', 'wpbdp-googlemaps' ); ?>
        </label>
        <input type="text" name="from_address" class="directions-from-address" />
    </div>

    <div class="directions-travel-mode">
        <label><?php esc_html_e( 'Travel Mode:', 'wpbdp-googlemaps' ); ?></label>
        <select name="travel_mode">
            <option value="driving"><?php esc_html_e( 'Driving', 'wpbdp-googlemaps' ); ?></option>
            <option value="transit"><?php esc_html_e( 'Public Transit', 'wpbdp-googlemaps' ); ?></option>
            <option value="walking"><?php esc_html_e( 'Walking', 'wpbdp-googlemaps' ); ?></option>
            <option value="cycling"><?php esc_html_e( 'Cycling', 'wpbdp-googlemaps' ); ?></option>
        </select>
    </div>

    <input type="submit" value="<?php esc_attr_e( 'Show Directions', 'wpbdp-googlemaps' ); ?>" class="find-route-btn wpbdp-button wpbdp-submit submit" />
  </div>
</div>
<?php endif; ?>

</div>
