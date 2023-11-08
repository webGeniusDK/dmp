<div class="wpbdp-license-key-activation-ui wpbdp-main-license wpbdp-license-status-<?php echo esc_attr( $license_status ); ?>" data-licensing="<?php echo esc_attr( $licensing_info_attr ); ?>">
	<div class="wpbdp-license-key-license-input">
		<input type="<?php echo esc_attr( $license_status === 'valid' ? 'hidden' : 'text' ); ?>" id="<?php echo esc_attr( 'license-key-module-' . $item_id ); ?>" class="wpbdp-license-key-input" name="wpbdp_settings[<?php echo esc_attr( $setting['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" <?php echo ( 'valid' === $license_status ? 'readonly="readonly"' : '' ); ?> placeholder="<?php esc_attr_e( 'Enter License Key here', 'business-directory-plugin' ); ?>"/>
		<input type="button" value="<?php esc_attr_e( 'Authorize', 'business-directory-plugin' ); ?>" data-working-msg="<?php esc_attr_e( 'Please wait...', 'business-directory-plugin' ); ?>" class="button button-primary wpbdp-license-key-activate-btn" />
	</div>
	<?php if ( $license_status === 'valid' ) : ?>
	<p>You're using Business Directory Plugin Premium. Enjoy! 🙂</p>
	<?php endif; ?>
	<a href="javascript:void(0)" data-working-msg="<?php esc_attr_e( 'Please wait...', 'business-directory-plugin' ); ?>" class="wpbdp-license-key-deactivate-btn">
		<?php esc_attr_e( 'Deauthorize', 'business-directory-plugin' ); ?>
	</a>
	<div class="wpbdp-license-key-activation-status-msg wpbdp-hidden inline notice"></div>
</div>
<style>#save-changes{display:none}</style>
