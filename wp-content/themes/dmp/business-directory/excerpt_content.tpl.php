<?php

//$img_sm  = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'small' )[0];
//$img_md  = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'medium' )[0];
$img_lg  = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'large' )[0];
//$img_xl  = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'largest' )[0];
//$img_url = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'full' )[0];
?>

<a href="#link-url" class="listing-item-link">
    <figure class="listing-item-thumbnail profil-image" style="background-image:url('<?= $img_lg ?>')"></figure>
</a>
<figure class="listing-item-polaroid"></figure>
<div class="listing-item-actions">
    <a href="#link-til-valideret-script" class="button validated-style">Valideret</a>
    <a href="#link-til-favorite-script" class="button favorite-style">Fav</a>
</div>
<div class="listing-details<?php echo esc_attr( $images->thumbnail ? '' : ' wpbdp-no-thumb' ); ?>">
	        <?php foreach ( $fields->not( 'social' ) as $field ) : ?>
		        <?php
		        $address = array( 'address', 'address2', 'city', 'state', 'country', 'zip' );
		        if ( in_array( $field->tag, $address ) ) :
			        if ( empty( $skip_address ) && $field->html ) :
				        $skip_address = $address;
				        ?>
                        <div class="address-info wpbdp-field-display wpbdp-field wpbdp-field-value">
					        <?php echo wp_kses_post( $fields->_h_address_label ); ?>
                            <div><?php echo wp_kses_post( $fields->_h_address ); ?></div>
                        </div>
			        <?php endif; ?>
		        <?php else : ?>
			        <?php echo $field->html; ?>
		        <?php endif; ?>
	        <?php endforeach; ?>
        <figure class="listing-item-pin"></figure>




        <?php
        $social = $fields->filter( 'social' );
        ?>
        <?php if ( $social && $social->html ) : ?>
        <div class="social-fields cf"><?php echo $social->html; ?></div>
        <?php endif; ?>

</div>
