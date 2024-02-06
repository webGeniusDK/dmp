<?php
global $post;
//$img_sm  = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'small' )[0];
//$img_md  = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'medium' )[0];
$img_lg = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'large' )[0];
//$img_xl  = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'largest' )[0];
//$img_url = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'full' )[0];
?>
<div class="listing-item-image-wrapper">
    <a href="<?= $post->guid ?>" class="listing-item-link">
        <figure class="listing-item-thumbnail profil-image" style="background-image:url('<?= $img_lg ?>')">
            <div class="polaroid-glass"></div>
        </figure>
    </a>
    <div class="listing-item-actions">
        <a href="#link-til-valideret-script" class="button validated-style">Valideret</a>
        <a href="#link-til-favorite-script" class="button favorite-style">Fav</a>
    </div>
    <?php
        $randomNumber = rand(1, 1);
    ?>
</div>
    <img src="<?= get_template_directory_uri() . '/img/image-pin-color-' . $randomNumber . '.png' ?>" alt="Listing Pin" class="listing-item-pin">


<div class="listing-details<?php echo esc_attr( $images->thumbnail ? '' : ' wpbdp-no-thumb' ); ?>">

    <?php

    ?>
	<?php foreach ( $fields->not( 'social' ) as $field ) : ?>
		<?php


		//echo wpbdp_get_form_field( $field->id )->get_label();
		$address = array( 'address', 'address2', 'city', 'state', 'country', 'zip' );
		$titles = array( 'title');
		if ( in_array( $field->tag, $address ) ) :
			if ( empty( $skip_address ) && $field->html ) :
				$skip_address = $address;
				?>
                <div class="address-info wpbdp-field-display wpbdp-field wpbdp-field-value">
                    <div class="omraade-text"><?php echo wp_kses_post( $fields->_h_address ); ?></div>
                </div>
			<?php endif; ?>
		<?php elseif ( in_array( $field->tag, $titles) ) : ?>
        <a href="<?= get_permalink() ?>" class="listing-title-link"><h4 class="excerpt-title equal-box-1"><?php echo mb_strimwidth( strip_tags( $title ), 0, 30, ' ...' );  ?></h4></a>
		<?php else : ?>
			<?php echo $field->html; ?>
		<?php endif; ?>
	<?php endforeach; ?>


	<?php
	$social = $fields->filter( 'social' );
	?>
	<?php if ( $social && $social->html ) : ?>
        <div class="social-fields cf"><?php echo $social->html; ?></div>
	<?php endif; ?>

</div>
