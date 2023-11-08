<div class="wpbdp-regions-<?php echo esc_attr( $shortcode ); ?>">

<?php if ( $breadcrumbs ) : ?>
<div class="breadcrumbs">
	<?php echo $breadcrumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
<?php endif; ?>

<?php if ( $regions ) : ?>
	<?php if ( $field ) : ?>
	<h3>
		<?php
		/* translators: %s: field name */
		printf( esc_html_x( 'Select a %s', 'regions browser', 'wpbdp-regions' ), esc_html( $field->get_label() ) );
		?>:
	</h3>
	<?php endif; ?>

	<?php if ( $alphabetically ) : ?>
		<ul class="regions-list alphabetically">
			<?php foreach ( $regions as $l => &$l_regions ) : ?>
			<li class="letter-regions">
				<h4><?php echo esc_html( $l ); ?></h4>
				<ul class="regions-list cf">
				<?php foreach ( $l_regions as &$r ) : ?>
					<li class="region"><a href="<?php echo esc_url( $r->link ); ?>">
						<?php
						echo esc_html( $r->name );
						echo esc_html( wpbdp_get_option( 'regions-show-counts', false ) ? " ($r->count)" : '' );
						?>
</a></li>
				<?php endforeach; ?>
				</ul>
			</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<ul class="regions-list">
		<?php foreach ( $regions as &$r ) : ?>
			<li class="region"><a href="<?php echo esc_url( $r->link ); ?>">
				<?php
				echo esc_html( $r->name );
				echo esc_html( wpbdp_get_option( 'regions-show-counts', false ) ? " ($r->count)" : '' );
				?>
</a></li>
		<?php endforeach; ?>
		</ul>
	<?php endif; ?>
<?php else : ?>
	<p><?php esc_html_e( 'No regions found.', 'wpbdp-regions' ); ?></p>
<?php endif; ?>

</div>
