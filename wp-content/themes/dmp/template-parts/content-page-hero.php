<style>
    .hero-placeholder {

    }
</style>
<?php
global $post;

/*if (has_post_thumbnail()) {
    $image_sizes = get_intermediate_image_sizes();
    $image_alt = get_post_meta(get_post_meta($post->ID)['_thumbnail_id'][0], '_wp_attachment_image_alt', true);
    $hero_arr = [];
    $hero_arr['full'] = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full')[0];
    foreach ($image_sizes as $image_size) {
        $image_url = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $image_size)[0];
        $hero_arr[$image_size] = $image_url;
    }
}*/

if(get_field('hero_image_desktop')) {
    sectionInlineCssBg('page-hero', get_field('hero_image_desktop'));
}
?>


<section class="hero-wrapper fade-in-block">
    <div class="hero-placeholder"></div>
    <div class="page-hero">
        <div class="container">
            <div class="page-hero-content">
                <?php if (get_field('hero_image_mobile')) : ?>
                    <div class="page-hero-mobile-bg">
                        <img alt="<?= get_field('hero_image_mobile')['alt'] ?>"
                             src="<?= webp_image(get_field('hero_image_mobile')['sizes']['small']) ?>"
                             srcset="<?= webp_image(get_field('hero_image_mobile')['sizes']['smallest']) ?>  480w,
										     <?= webp_image(get_field('hero_image_mobile')['sizes']['small']) ?>  722w,"
                             sizes="calc(100vw - 32px)"/>
                    </div>
                <?php endif; ?>
                <div class="page-hero-content-left">
                    Content Left
                </div>
                <div class="page-hero-content-right">
                    <h1 class="page-hero-preheader">Hero Preheader</h1>
                    <h2 class="page-hero-title">Hero Title</h2>
                    <div class="page-hero-text">Hero Text</div>
                    <nav class="page-hero-cta-list">
                        <a href="#" class="page-hero-cta button">Hero CTA</a>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>
