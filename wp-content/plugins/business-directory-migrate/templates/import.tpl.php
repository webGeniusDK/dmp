<?php echo wpbdp_admin_header( null, 'migrate-import' ); ?>
<?php echo wpbdp_admin_notices(); ?>

<div class="wpbdp-note"><p>
<?php
$notice = _x( "Please note that the import process is a resource intensive task. If your import does not succeed try disabling other plugins first and/or increasing the values of the 'memory_limit' and 'max_execution_time' directives in your server's php.ini configuration file.",
              'admin csv-export',
              'WPBDM' );
$notice = str_replace( array( 'memory_limit', 'max_execution_time' ),
                       array( '<a href="http://www.php.net/manual/en/ini.core.php#ini.memory-limit" target="_blank" rel="noopener">memory_limit</a>',
                              '<a href="http://www.php.net/manual/en/info.configuration.php#ini.max-execution-time" target="_blank" rel="noopener">max_execution_time</a>' ),
                       $notice );
echo $notice;
?>
</p>
</div>

<div class="cf">

<div class="import-file-selection">
    <form action="" method="post">
        <input type="hidden" name="action" value="import" />

            <h3><?php _e( 'Migration Pack Selection', 'wpbdp-migrate' ); ?></h3>

            <?php if ( $uploaded ): ?>
                <p><?php _e( 'Packs uploaded:', 'wpbdp-migrate' ); ?></p>

                <?php foreach ( $uploaded as $f ): ?>
                <div class="choice-data">
                    <label><input type="radio" name="uploaded" value="<?php echo $f['filename']; ?>" />
                        <b><?php echo $f['filename']; ?> (<?php echo size_format( $f['filesize'] ); ?>)</b>
                    </label>

                    <div class="choice-meta">
                    <?php _e( 'Date:', 'wpbdp-migrate' ); ?> <b><?php echo date( 'Y-M-d', $f['date'] ); ?></b> | <?php _e( 'BD Version:', 'wpbdp-migrate' ); ?> <b><?php echo $f['bd_version']; ?></b>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php echo __( 'No migration packs are available for import.', 'wpbdp-migrate' ); ?></p>
            <?php endif; ?>

        <?php if ( $uploaded ): ?>
        <p class="submit">
            <input type="submit" class="submit button button-primary" value="<?php _e( 'Begin Import', 'wpbdp-migrate' ); ?>" />
        </p>
        <?php endif; ?>
    </form>
</div>

<div class="import-upload-file">
    <h3><?php _e( 'Upload Migration Pack', 'wpbdp-migrate' ); ?></h3>
    <p>
        <?php _e( 'A <i>Migration Pack</i> is a ZIP file generated by the migration <a>export procedure</a>.', 'wpbdp-migrate' ); ?><br />
        <?php echo str_replace( '<dir>',
                                '<tt> ' . $upload_dir . '</tt>',
                                __( 'You can upload migration packs directly via FTP to the <dir> directory or via the form below (only for small files).', 'wpbdp-migrate' ) ); ?>
    </p>

    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="upload_file" />
        <input type="submit" class="submit button" value="<?php _e( 'Upload Pack', 'wpbdp-migrate' ); ?>" />
    </form>
</div>

</div>
<?php echo wpbdp_admin_footer(); ?>
