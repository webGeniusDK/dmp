<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 *
 * @package dmp
 */

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 * @return array
 */
function dmp_body_classes( $classes ) {
	// Adds a class of hfeed to non-singular pages.
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	// Adds a class of no-sidebar when there is no sidebar present.
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'dmp_body_classes' );

/**
 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
 */
function dmp_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'dmp_pingback_header' );

function base64Encoder( $url ) {
    $type = pathinfo( $url, PATHINFO_EXTENSION );

    $username = 'Jeppe';
    $password = '111';
    $context  = stream_context_create( array(
        'http' => array(
            'header' => "Authorization: Basic " . base64_encode( "$username:$password" )
        )
    ) );
    //$image = file_get_contents($url, false, $context);

    $image = file_get_contents( $url );

    return base64_encode( $image );
}

function replace_extension( $filename, $new_extension ) {
    $info = pathinfo( $filename );

    return $info['dirname'] . '/' . $info['filename'] . '.' . $new_extension;
}

function webp_image( $filename ) {
    $info = pathinfo( $filename );
    if ( strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false && $info['extension'] !== 'svg' ) {
        $imgExt  = 'webp';
        $imgType = 'webp';
    } else {
        if ( $info['extension'] = 'jpg' ) {
            $imgExt  = 'jpg';
            $imgType = 'jpeg';
        } else if ( $info['extension'] = 'gif' ) {
            $imgExt  = 'gif';
            $imgType = 'gif';
        } else if ( $info['extension'] = 'png' ) {
            $imgExt  = 'png';
            $imgType = 'png';
        } else if ( $info['extension'] = 'svg' ) {
            $imgExt  = 'svg';
            $imgType = 'svg';
        }
    }
    $returnValue = $info['dirname'] . '/' . $info['filename'] . '.' . $imgExt;

    return $returnValue;
}

function getImageExtension( $arrayImage ) {
    $imgExt = "";

    if ( strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
        $imgExt = 'webp';
    } else {
        if ( pathinfo( $arrayImage )['extension'] = 'jpg' ) {
            $imgExt = 'jpg';
        } else if ( pathinfo( $arrayImage )['extension'] = 'gif' ) {
            $imgExt = 'gif';
        } else if ( pathinfo( $arrayImage )['extension'] = 'png' ) {
            $imgExt = 'png';
        } else if ( pathinfo( $arrayImage )['extension'] = 'svg' ) {
            $imgExt = 'svg';
        }
    }

    return $imgExt;
}

function insertSVG( $svg_name ) {
	return '<svg><use xlink:href="' . get_stylesheet_directory_uri() . '/icons/dmp-icon-sprite.svg#' . $svg_name . '" href="' . get_stylesheet_directory_uri() . '/icons/dmp-icon-sprite.svg#' . $svg_name . '"></use></svg>';
}
function sectionInlineCssBg( $cssContainer, $imgArray, $bgColor = "transparent" ) {
    if ( $imgArray ) {

        $img_sm  = $imgArray['sizes']['small'];
        $img_md  = $imgArray['sizes']['medium'];
        $img_lg  = $imgArray['sizes']['large'];
        $img_xl  = $imgArray['sizes']['largest'];
        $img_url = $imgArray['url'];
        ?>

        <style type="text/css">

            /* Medium screen, non-retina */
            @media only screen and (min-width: 992px) {
                .<?= $cssContainer ?> {
                    background-image: url('<?= webp_image( $img_lg ); ?>');
                <?= $bgColor ? 'background-color: ' . $bgColor : '' ?>
                }
            }

            /* Medium screen, retina, stuff to override above media query */
            @media only screen and (-webkit-min-device-pixel-ratio: 2)
            and (min-width: 992px), only screen and (   min--moz-device-pixel-ratio: 2)
            and (min-width: 992px), only screen and (     -o-min-device-pixel-ratio: 2/1)
            and (min-width: 992px), only screen and (        min-device-pixel-ratio: 2)
            and (min-width: 992px), only screen and (                min-resolution: 192dpi)
            and (min-width: 992px), only screen and (                min-resolution: 2dppx)
            and (min-width: 992px) {
                .<?= $cssContainer ?> {
                    background-image: url('<?= webp_image( $img_xl); ?>');
                <?= $bgColor ? 'background-color: ' . $bgColor : '' ?>
                }
            }

            /* Large screen, non-retina */
            @media only screen and (min-width: 1300px) {
                .<?= $cssContainer ?> {
                    background-image: url('<?= webp_image( $img_xl); ?>');
                <?= $bgColor ? 'background-color: ' . $bgColor : '' ?>
                }
            }

            /* Large screen, retina, stuff to override above media query */
            @media only screen and (-webkit-min-device-pixel-ratio: 2)
            and (min-width: 1200px), only screen and (   min--moz-device-pixel-ratio: 2)
            and (min-width: 1200px), only screen and (     -o-min-device-pixel-ratio: 2/1)
            and (min-width: 1200px), only screen and (        min-device-pixel-ratio: 2)
            and (min-width: 1200px), only screen and (                min-resolution: 192dpi)
            and (min-width: 1200px), only screen and (                min-resolution: 2dppx)
            and (min-width: 1200px) {
                .<?= $cssContainer ?> {
                    background-image: url('<?php echo webp_image( $img_url); ?>');
                <?= $bgColor ? 'background-color: ' . $bgColor : '' ?>
                }
            }
        </style>
        <?php
    } else {
        echo "No image is attached to the post";
    }
}
