<?php

    $video = FLBuilderPhoto::get_attachment_data( $settings->video );
    
    // extract youtube id
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $settings->youtube, $youtubeId);

    if( 'media_library' === $settings->video_type && isset($video->url) ) {
        $swarmify_url = $video->url;
    } else if( 'youtube' === $settings->video_type && isset($youtubeId[1]) ){
        $swarmify_url = 'https://www.youtube.com/embed/' . $youtubeId[1];
    } else if( 'vimeo' === $settings->video_type && $settings->vimeo ){
        $swarmify_url = $settings->vimeo;
    } else if( 'other_source' === $settings->video_type && $settings->other_source ) {
        $swarmify_url = $settings->other_source;
    } 

    if ( empty( $swarmify_url ) ) {
        return;
    }

    $responsive = $settings->responsive ? 'class="swarm-fluid"':'';
    $poster_url  = 'media_library' === $settings->poster ? $settings->poster_internal_src : $settings->poster_external;
    $poster = 'none' !== $settings->poster && !empty($poster_url) ? sprintf('poster="%s"', $poster_url) : '';
    $autoplay = $settings->autoplay ? 'autoplay':'';
    $muted  = $settings->muted ? 'muted':'';
    $loop = $settings->loop ? 'loop':'';
    $controls = $settings->controls ?'controls':'';
    $video_inline  = $settings->inline ?'playsinline':'';

    printf('<smartvideo src="%s" width="%s" height="%s" %s %s %s %s %s %s %s></smartvideo>', $swarmify_url, $settings->width, $settings->height, $poster, $muted, $responsive, $autoplay, $loop, $controls, $video_inline);
?>
