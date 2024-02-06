<div class="hero-video-background">
	<video autoplay loop muted>
		<?php $randomNumber = rand(1, 5); ?>
		<source src="<?= get_template_directory_uri() . '/img/video-' . $randomNumber . '.mp4' ?>" type="video/mp4">
		Your browser does not support the video tag.
	</video>
	<div class="bg-video-gradient-fader "></div>
    <?php
    if ( isset( $args['video-inner-content'] ) ) {
	    echo $args['video-inner-content'];
    }
    ?>
</div>