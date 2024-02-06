<?php echo wpbdp_admin_header( null, 'migrate-export' ); ?>
<?php echo wpbdp_admin_notices(); ?>

<div class="error wpbdp-migrate-error" style="display: none;">
<p><?php _e( 'An unknown error occurred during the export. Please make sure you have enough free disk space and memory available to PHP. Check your error logs for details.',
             'wpbdp-migrate' ); ?></p>
</div>

<div class="canceled-migration" style="display: none;">
    <h3><?php _e( 'Export Canceled', 'wpbdp-migrate' ); ?></h3>
    <p><?php _e( 'An error has occurred while generating the export file. The export has been canceled.', 'wpbdp-migrate' ); ?></p>
    <p><a href="" class="button"><?php _ex( '← Return to Migrate (Export)', 'wpbdp-migrate' ); ?></a></p>
</div>

<div class="wpbdp-note">
    <p><?php _e( 'Please do not abandon this page until the process completes.', 'wpbdp-migrate' ); ?></p>
</div>

<div class="wpbdp-migrate-progress" data-id="<?php echo $run->get_id(); ?>">
    <h3>Progress</h3>

    <ol class="export-parts">
        <?php foreach ( $parts as $p => $desc ): ?>
            <li class="part-<?php echo $p; ?> <?php echo $run->completed( $p ) ? 'done' : ''; ?>">
                <span class="desc"><?php echo $desc; ?></span>

                <div class="status-msg">
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<div class="wpbdp-migrate-done">
    <h3><?php _e( 'Export Complete', 'wpbdp-migrate' ); ?></h3>
    <p><?php _e( 'Your export file has been successfully created and it is now ready for download.', 'wpbdp-migrate' ); ?></p>

    <div class="download-link">
        <a href="" class="button button-primary">
            <?php echo sprintf( _x( 'Download %s (%s)', 'admin csv-export', 'WPBDM' ),
                                '<span class="filename"></span>',
                                '<span class="filesize"></span>' ); ?>
        </a>
    </div>

    <div class="cleanup-link wpbdp-note">
        <p><?php _ex( 'Click "Cleanup" once the file has been downloaded in order to remove all temporary data created by Business Directory during the export process (including the export file).', 'admin csv-export', 'WPBDM' ); ?><br />
        <a href="" class="button"><?php _ex( 'Cleanup', 'admin csv-export', 'WPBDM' ); ?></a></p>
    </div>

</div>

<?php echo wpbdp_admin_footer(); ?>
