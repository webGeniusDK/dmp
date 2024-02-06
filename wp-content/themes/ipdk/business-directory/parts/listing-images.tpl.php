<?php
/**
 * Extra images template
 *
 * @package BDP/Templates/parts
 */

if ( ! isset( $extra_images ) ) {
	$extra_images = ( isset( $images ) && $images->extra ) ? $images->extra : false;
}

if ( ! $extra_images ) {
	return;
}

if(count($extra_images) === 1) {
    $count_class = 'single';
} elseif(count($extra_images) === 2) {
	$count_class = 'double';
} elseif(count($extra_images) === 3) {
	$count_class = 'triple';
} elseif(count($extra_images) === 4) {
    $count_class = 'quad';
} elseif(count($extra_images) >= 5) {
    $count_class = 'more';
}
?>

<div class="single-listing-image-thumbnail-list img-count-<?= $count_class ?>">
	<?php
	$count = 1;
	foreach ( $extra_images as $img ) :
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$image = wp_get_attachment_image_src( $img->id, 'large' )[0];
		$image_html = $img->html;
		?>
        <div class="single-listing-image image-<?= $count ?>"  style="background-image: url('<?= $image ?>')"></div>
		<?php $count ++; ?>
	<?php endforeach; ?>
</div>

