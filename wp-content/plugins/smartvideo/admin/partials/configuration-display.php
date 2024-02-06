<div class="col-md-12">
  <form method="post" class="form-horizontal" action="options.php">
  <?php
    settings_fields( 'swarmify_config_settings' );
    do_settings_sections( 'swarmify_config_settings' );
  ?>
    <h1>Let's get you set up! 👍</h1>
    <ul class="list-group">
      <li class="list-group-item">Visit <a target="_blank" href="https://dash.swarmify.com">dash.swarmify.com</a></li>
      <li class="list-group-item">Copy your <b>Swarm CDN Key</b> to your clipboard like so:
        <br><br>
        <img class="img-responsive" src="<?php echo plugin_dir_url( __DIR__ ) .'images/screen1.gif' ?>" alt="">
      </li>
      <li class="list-group-item">
        Paste your <b>Swarm CDN Key</b> into the field below:
        <br><br>
        <input type="text" name="swarmify_cdn_key" value="<?php echo esc_attr(get_option('swarmify_cdn_key')); ?>" placeholder="Swarm CDN Key" class="form-control swarmify_cdn_key">
      </li>
      <li class="list-group-item">
      Click the button below:
      <br><br>
        <input class="swarmify-button cdn_key_button" type="submit" value="Enable SmartVideo">
      </li>
    </ul>
  </form>

  <hr>
  <hr>

  <h1>How do I add a SmartVideo to my website?</h1>
  <p class="paragraph">
    After clicking the <b>Enable SmartVideo</b> button, SmartVideo will begin scanning your site for YouTube and Vimeo videos.
  </p>

  <p class="paragraph">
    <b>If you have YouTube or Vimeo videos on your site</b>, they will be converted to SmartVideo and be displayed in a clean, fast-loading player automatically, requiring no extra work on your part. 
  </p>

  <p class="paragraph">
    <b>If you want to add a video to your site directly</b>, simply use our included SmartVideo block. After enabling SmartVideo, this block will be visible in your page editor <i>(current supported editors: Classic WordPress Editor, Gutenberg, Beaver Builder, Divi, and Elementor)</i>.
  </p>
  
  <img class="img-responsive" src="<?php echo plugin_dir_url( __DIR__ ) .'images/widgetdemo.gif' ?>" alt="">

  <p class="paragraph">
    When a page with a video loads for the first time, SmartVideo fetches that video, encodes it, and stores it on our network. Depending on the resolution of the video file, <b>a video typically takes one to two times the length of the video to process</b> <i>(a 10-minute video should take 10-20 minutes). </i>
  </p>

  <p class="paragraph">
    You will know that a video has been fully converted by SmartVideo when, while hovering over the <i>Video Acceleration</i> icon on the player, the popup box says <b>Video Acceleration: On</b>
  </p>

  <img class="img-responsive" src="<?php echo plugin_dir_url( __DIR__ ) .'images/accelon.gif' ?>" alt="">
  
  <p class="paragraph">
    If the popup box says <b>Video Acceleration: Off</b>, the video is still being processed.
  </p>

  <p class="paragraph">
  After the conversion process is complete, the video is hosted on our global delivery network and served via our accelerated playback technology. This means you can keep uploading your videos to YouTube and placing them on your site, as SmartVideo will continuously look for new videos and convert them automatically.
  </p>

  <p class="paragraph">
    <b>If you have questions</b>, take a look at the Frequently Asked Questions collection in our Help Center.
    <br>
    <a class="swarmify-button" target="_blank" href="https://support.swarmify.com/hc/en-us/sections/360007392954-Have-a-question-Your-answer-is-probably-here">FAQs</a>
  </p>

  <hr>

  <p class="paragraph">
    <b>If you are not using a supported builder or editor</b>, YouTube and Vimeo videos should be auto-converted just fine. However, if you want to add a SmartVideo directly to your site, you’ll have to make use of a SmartVideo tag. Click the button below to learn about SmartVideo tags.
  </p>
  <a class="swarmify-button" target="_blank" href="https://support.swarmify.com/hc/en-us/articles/360043738653-How-to-add-a-video-to-your-non-WordPress-website">SmartVideo tags</a>

  
  <hr>

  <?php
    $name ='configuration';
    require('footer-display.php'); 
  ?>

</div>