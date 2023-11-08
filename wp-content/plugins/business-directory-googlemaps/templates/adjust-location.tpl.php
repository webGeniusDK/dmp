<?php
/**
 * Google Maps Adjust Location Template
 *
 * @package Google Maps/Templates/Adjust Location
 */

$editing = 'edit_listing' === wpbdp_current_view();
?>
<label for="wpbdp-googlemaps-enable-location-override">
    <input id="wpbdp-googlemaps-enable-location-override" type="checkbox" name="enable_location_override" <?php echo $location_override ? esc_attr( 'checked' ) : ''; ?>/>
    <?php esc_html_e( 'Enable manual listing location', 'wpbdp-googlemaps' ); ?>
</label>

<div class="wpbdp-googlemaps-place-chooser-container" style="<?php echo ! $location_override ? 'display:none;' : ''; ?>">
    <p>
        <?php esc_attr_e( 'If you want to adjust your listing\'s location, move the pin below to the correct position. You can also use "Search nearby place" or "Enter coordinates" buttons to set listing\'s location.', 'wpbdp-googlemaps' ); ?>
        <br />
    </p>
    <div class="wpbdp-widget-place-chooser">
        <div class="map"></div>
        <div class="actions">
            <input type="button" value="<?php esc_attr_e( 'Search nearby place', 'wpbdp-googlemaps' ); ?>" class="search-nearby-toggle" />
            <input type="button" value="<?php esc_attr_e( 'Enter coordinates', 'wpbdp-googlemaps' ); ?>" class="enter-coordinates-toggle" />
            <input type="button" value="<?php esc_attr_e( 'Done', 'wpbdp-googlemaps' ); ?>" class="done" />
        </div>
        <div class="action-area-wrapper">
            <div class="action-area"></div>
        </div>
    </div>
</div>

<input type="hidden" name="location_override[lat]" value="" />
<input type="hidden" name="location_override[lng]" value="" />
<input type="hidden" name="done_location_override" value="<?php echo $auto_located ? '1' : ''; ?>" />

<script type="text/javascript">
jQuery(function($) {
    var settings = {
        'initial_value'    : <?php echo wp_json_encode( $location ); ?>,
        'done_after_drag'  : true,
        'show_done_button' : false,
        'debug': true,
        'auto_located' : <?php echo $auto_located || $editing ? 'true' : 'false'; ?>
    };
    var chooser  = new wpbdp.googlemaps.PlaceChooser( $( '.wpbdp-googlemaps-place-chooser-container' ).get( 0 ), settings );
    chooser.when_done(function(res) {
        if ( ! res.success )
            return;

        $( 'input[name="location_override[lat]"]' ).val(res.lat);
        $( 'input[name="location_override[lng]"]' ).val(res.lng);
    });
    chooser.init();
});
</script>
