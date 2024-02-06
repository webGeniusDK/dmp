<?php echo wpbdp_admin_header( null, 'migrate-export' ); ?>
<?php echo wpbdp_admin_notices(); ?>

<div class="wpbdp-note"><p>
<?php
$notice = _x( "Please note that the export process is a resource intensive task. If your export does not succeed try disabling other plugins first and/or increasing the values of the 'memory_limit' and 'max_execution_time' directives in your server's php.ini configuration file.",
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

<form action="" method="post">
    <input type="hidden" name="action" value="do-import" />

    <table class="form-table">
        <tr>
            <th scope="row">
                <label> <?php _ex('What to export?', 'admin csv-export', 'WPBDM'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" name="" id="parts-check-everything"> <strong><?php _ex( 'Everything', 'admin csv-export', 'WPBDM' ); ?></strong>
                </label><br />
                <?php
                foreach ( $parts as $part_id => $part_info ):
                    if ( 'packaging' == $part_id ) continue;
                ?>
                <label>
                    <input type="checkbox" name="parts[]" value="<?php echo $part_id; ?>" data-deps="<?php echo implode( ',', $part_info['deps'] ); ?>" class="part-checkbox" /> <?php echo $part_info['description']; ?>
                </label><br />
                <?php endforeach; ?>
<!--                <select name="settings[listing_status]">
                    <option value="all"><?php _ex( 'All', 'admin csv-export', 'WPBDM' ); ?></option>
                    <option value="publish"><?php _ex( 'Active Only', 'admin csv-export', 'WPBDM' ); ?></option>
                    <option value="publish+draft"><?php _ex( 'Active + Pending Renewal', 'admin csv-export', 'WPBDM' ); ?></option>
                </select>-->
            </td>
        </tr>
    </table>

    <p class="submit">
        <?php echo submit_button( _x( 'Export!', 'admin csv-export', 'WPBDM' ), 'primary', 'do-export', false ); ?>
    </p>
</form>

<?php echo wpbdp_admin_footer(); ?>
