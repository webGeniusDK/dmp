<?php
wpbdp_admin_header(
	array(
		'title' => __( 'Import ZIP Code Database', 'wpbdp-zipcodesearch' ),
		'id'    => 'zip-db-import',
		'echo'  => true,
	)
);

wpbdp_admin_notices();
?>

<?php if ( $import ) : ?>
<div class="import-step-2">
<h2><?php esc_html_e( '3. Database Import', 'wpbdp-zipcodesearch' ); ?></h2>
<table class="import-status">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'File', 'wpbdp-zipcodesearch' ); ?></th>
			<td><?php echo esc_html( basename( $import->get_filepath() ) ); ?></td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Database', 'wpbdp-zipcodesearch' ); ?>
			</th>
			<td><?php echo esc_html( $import->get_database_name() ); ?></td>
		</tr>
		<tr>            
			<th scope="row"><?php esc_html_e( 'Revision', 'wpbdp-zipcodesearch' ); ?></th>
			<td><?php echo esc_html( $import->get_database_date() ); ?></td>
		</tr>            
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Items', 'wpbdp-zipcodesearch' ); ?>
			</th>
			<td>
				<?php echo number_format( $import->get_total_items() ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Progress', 'wpbdp-zipcodesearch' ); ?></th>
			<td class="progress">
				<div class="import-progress">
					<span class="progress-text"><?php echo esc_html( $import->get_progress( '%' ) ); ?>%</span>
					<div class="progress-bar"><div class="progress-bar-outer"><div class="progress-bar-inner" style="width: <?php echo esc_attr( round( $import->get_progress( '%' ) ) ); ?>%"></div></div></div>
				</div>
				<div class="import-status-text">
					<?php if ( $import->get_progress( 'n' ) == 0 ) : ?>
						<?php esc_html_e( 'Import has not started.', 'wpbdp-zipcodesearch' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Import paused.', 'wpbdp-zipcodesearch' ); ?>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<tr class="actions">
			<td colspan="2">
				<a href="#" class="resume-import button button-primary">
					<?php if ( $import->get_imported() == 0 ) : ?>
						<?php esc_html_e( 'Start Import', 'wpbdp-zipcodesearch' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Resume Import', 'wpbdp-zipcodesearch' ); ?>                        
					<?php endif; ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'cancel_import', 1 ) ); ?>" class="cancel-import"><?php esc_html_e( 'Cancel', 'wpbdp-zipcodesearch' ); ?></a>
			</td>
		</tr>
	</tbody>
</table>
</div>

<div class="import-step-3" style="display: none;">
<h2><?php esc_html_e( '3. Database imported successfully!', 'wpbdp-zipcodesearch' ); ?></h2>
	<?php if ( strtolower( basename( $import->get_filepath() ) ) == 'zipcodes.db' ) : ?>
<div class="wpbdp-note"><p><?php esc_html_e( 'Please delete the "zipcodes.db" file from your "db/" directory since it is no longer needed.', 'wpbdp-zipcodesearch' ); ?></p></div>
<?php endif; ?>
<p>
	<?php
	printf(
		/* translators: %s database name */
		esc_html__( 'The ZIP code database %s has been imported and is ready to be used.', 'wpbdp-zipcodesearch' ),
		esc_html( $import->get_database_name() . ' ' . $import->get_database_date() )
	);
	?>
	</p>
<p><?php esc_html_e( 'You can now:', 'wpbdp-zipcodesearch' ); ?>
	<ul>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbdp_settings&tab=listings&subtab=search_settings' ) ); ?>">
				<?php esc_html_e( 'Configure ZIP Search options', 'wpbdp-zipcodesearch' ); ?>
			</a>
		</li>
		<li>
			<a href="http://businessdirectoryplugin.com/zip-databases/" target="_blank" rel="noopener">
				<?php esc_html_e( 'Download/install additional databases', 'wpbdp-zipcodesearch' ); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbdp_settings' ) ); ?>">
				<?php esc_html_e( '← Return to Business Directory Admin', 'wpbdp-zipcodesearch' ); ?>
			</a>
		</li>
	</ul>
</p>
</div>


<?php else : ?>
<div class="import-step-1">
	<?php if ( $upgrade_possible ) : ?>
<div class="updated">
	<p>
		<?php esc_html_e( "Business Directory has detected you have an old-style database setup (zipcodes.db). If you wish, Business Directoy can migrate this ZIP code information to the new format so you don't need to download and install a new database.", 'wpbdp-zipcodesearch' ); ?>
	</p>
	<p>
		<a href="<?php echo esc_url( add_query_arg( 'nomigrate', 1 ) ); ?>" class="button">
			<?php esc_html_e( 'Ignore the file. I want to import a new database.', 'wpbdp-zipcodesearch' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'migrate', 1 ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Proceed with migration', 'wpbdp-zipcodesearch' ); ?>
		</a>
	</p>
</div>
<?php endif; ?>
	
<h2>
	<?php
	printf(
		/* translators: %s link */
		esc_html__( '1. Download one or more databases from %s', 'wpbdp-zipcodesearch' ),
		'<a href="https://businessdirectoryplugin.com/zip-databases/" target="_blank" rel="noopener">BusinessDirectoryPlugin.com</a>'
	);
	?>
</h2>
	
<div class="columns-wrapper">
	<div class="col-1">
		<h2><?php esc_html_e( '2. Upload database file(s)', 'wpbdp-zipcodesearch' ); ?></h2>        
		<form action="<?php echo esc_attr( wpbdp_get_server_value( 'REQUEST_URI' ) ); ?>" method="POST" enctype="multipart/form-data">
			<?php wp_nonce_field( 'wpbdp_zipcode_file_upload' ); ?>
			<input type="hidden" name="MAX_FILE_SIZE" value="31457280" />
			<table class="form-table">    
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Database File', 'wpbdp-zipcodesearch' ); ?>
						</th>
						<td class="form-required">
							<input type="file" name="dbfile" /> 
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Upload File', 'wpbdp-zipcodesearch' ); ?>"/>
			</p>
		</form>
	</div>
	<div class="col-2">
		<?php if ( $dbfiles ) : ?>
		<h2><?php esc_html_e( '... or choose a manually uploaded database file', 'wpbdp-zipcodesearch' ); ?></h2>                
			<form action="<?php echo esc_attr( wpbdp_get_server_value( 'REQUEST_URI' ) ); ?>" method="POST" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpbdp_zipcode_manual_upload' ); ?>
				<?php foreach ( $dbfiles as &$dbfile ) : ?>
				<label>
					<input type="radio" name="uploaded_dbfile" value="<?php echo esc_attr( $dbfile['filepath'] ); ?>">
					<b><?php echo esc_html( basename( $dbfile['filepath'] ) ); ?></b> -
					<?php echo esc_html( $dbfile['database'] ); ?>
					(
					<?php
					printf(
						/* translators: %s date */
						esc_html__( 'Version %s', 'wpbdp-zipcodesearch' ),
						esc_html( $dbfile['date'] )
					);
					?>
					)
				</label><br />
				<?php endforeach; ?>          
				<p class="submit">
					<input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Use this file', 'wpbdp-zipcodesearch' ); ?>"/>
				</p>                
			</form>
		<?php else : ?>
			<div class="wpbdp-note">
				<p>
					<?php
					printf(
						/* translators: %s is the path */
						esc_html__( 'You can manually upload database files via FTP or similar if the upload form does not work for you due to upload restrictions on your host. Please use the %s directory to store these files and reload this page.', 'wpbdp-zipcodesearch' ),
						'<strong><code>' . esc_html( $dbpath ) . '</code></strong>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>

</div>
<?php endif; ?>

<?php wpbdp_admin_footer( 'echo' ); ?>
