<?php echo wpbdp_admin_header( null, 'migrate-import' ); ?>
<?php echo wpbdp_admin_notices(); ?>

<div class="error wpbdp-migrate-error" style="display: none;">
<p><?php _e( 'An unknown error occurred during the import. Please make sure you have enough free disk space and memory available to PHP. Check your error logs for details.',
             'wpbdp-migrate' ); ?></p>
</div>

<div class="canceled-migration" style="display: none;">
    <h3><?php _e( 'Import Canceled', 'wpbdp-migrate' ); ?></h3>
    <p><?php _e( 'An error has occurred while importing the Migration Pack. The import has been canceled.', 'wpbdp-migrate' ); ?></p>
    <p><a href="" class="button"><?php _ex( '← Return to Migrate (Import)', 'wpbdp-migrate' ); ?></a></p>
</div>

<div class="wpbdp-note">
    <p><?php _e( 'Please do not abandon this page until the process completes.', 'wpbdp-migrate' ); ?></p>
</div>

<div class="wpbdp-migrate-progress" data-id="<?php echo esc_attr( $pack->get_id() ); ?>">
    <h3>Progress</h3>

    <div class="status-msg updated below-h2">
    <p>Working...</p>
    </div>

    <ol class="export-parts">
        <?php foreach ( $parts as $p => $desc ): ?>
            <li class="part-<?php echo $p; ?> <?php echo $pack->completed( $p ) ? 'done' : ''; ?>">
                <span class="desc"><?php echo $desc; ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

</div>

<div class="wpbdp-migrate-pack-info">
    <h3><?php _e( 'Migration Pack Info', 'wpbdp-migrate' ); ?></h3>

    <dl>
        <dt><?php _e( 'Filename', 'wpbdp-migrate' ); ?></dt>
        <dd><?php echo $pack->get_filename(); ?></dd>

        <dt><?php _e( 'File size (compressed)', 'wpbdp-migrate' ); ?></dt>
        <dd><?php echo $pack->get_file_size(); ?></dd>

        <dt><?php _e( 'Business Directory Plugin Version', 'wpbdp-migrate' ); ?></dt>
        <dd><?php echo $pack->get_bd_version(); ?></dd>

        <dt><?php _e( 'Created on', 'wpbdp-migrate' ); ?></dt>
        <dd><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                                  $pack->get_date() ); ?></dd>

    </dl>
</div>


<?php echo wpbdp_admin_footer(); ?>
