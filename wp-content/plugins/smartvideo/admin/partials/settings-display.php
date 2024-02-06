<div class="col-md-12 settings">
  <form method="post" class="form-horizontal" action="options.php">
    <?php
    settings_fields( 'swarmify_settings' );
    do_settings_sections( 'swarmify_settings' );
    wp_enqueue_media();
    ?>

    <div class="form-group">
      <label class="col-sm-12 main_label">
        <?php _e('Toggle SmartVideo on or off',$this->plugin_name); ?>
         <small><?php if(get_option('swarmify_cdn_key') == ''){ echo '(To enable SmartVideo, follow the instructions on the <a href="'.admin_url("options-general.php?page=SmartVideo.php&tab=configuration").'">Configuration page</a>.)';} ?></small>
      </label>
      <div class="col-sm-12 radio_buttons <?php if(get_option('swarmify_cdn_key') == ''){ echo 'low_opacity'; }?>">
        <div>
          <input <?php if(get_option('swarmify_cdn_key') == ''){ echo 'disabled';} ?> id="enable_swarmify" value="on" type="radio" <?php if(get_option('swarmify_status') == 'on'){echo 'checked';} ?> <?php if(get_option('swarmify_cdn_key') != ''){ echo 'name="swarmify_status"';} ?> >
          <label for="enable_swarmify"><span>Enable</span> SmartVideo</label>
        </div>
        <div>
          <input <?php if(get_option('swarmify_cdn_key') == ''){ echo 'disabled';} ?> id="disable_swarmify" value="off" type="radio" <?php if(get_option('swarmify_status') !== 'on'){echo 'checked';} ?> <?php if(get_option('swarmify_cdn_key') != ''){ echo 'name="swarmify_status"';} ?>>
          <label for="disable_swarmify"><span class="red">Disable</span> SmartVideo</label>
        </div>
      </div>
    </div>
    <hr>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><a id="panel-basic-btn" role="button">Basic Options</a></h3>
        </div>
        <div class="panel-body" id="panel-basic-body">
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('Toggle YouTube & Vimeo auto-conversions on or off',$this->plugin_name); ?>
                </label>
                <div class="col-sm-12">
                    <input id="toggle_youtube" value="on"
                        <?php if(get_option('swarmify_toggle_youtube') == 'on'){echo 'checked';} ?> type="checkbox"
                        name="swarmify_toggle_youtube">
                    <label for="toggle_youtube">Auto-convert YouTube & Vimeo videos</label>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('Display closed captions when available from YouTube sources',$this->plugin_name); ?>
                </label>
                <div class="col-sm-12">
                    <input id="toggle_youtube_cc" value="on"
                        <?php if(get_option('swarmify_toggle_youtube_cc') == 'on'){echo 'checked';} ?> type="checkbox"
                        name="swarmify_toggle_youtube_cc">
                    <label for="toggle_youtube_cc">Import & display closed captions from YouTube sources</label>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('Optimize background videos & existing videos',$this->plugin_name); ?>
                </label>
                <div class="col-sm-12">
                    <input <?php if(get_option('swarmify_toggle_bgvideo') == 'on'){echo 'checked';} ?>
                        id="toggle_bgvideo" value="on" type="checkbox" name="swarmify_toggle_bgvideo">
                    <label for="toggle_bgvideo">Optimizes videos that are currently on your website or in the background
                        of your theme. May conflict with some layouts.</label>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('Change the shape of the play button',$this->plugin_name); ?>
                </label>
                <div class="col-sm-12">
                    <select id="select_button" name="swarmify_theme_button">
                        <option value="default"
                            <?php if(get_option('swarmify_theme_button') == 'default'){echo 'selected';} ?>>Default
                        </option>
                        <option value="rectangle"
                            <?php if(get_option('swarmify_theme_button') == 'rectangle'){echo 'selected';} ?>>Rectangle
                        </option>
                        <option value="circle"
                            <?php if(get_option('swarmify_theme_button') == 'circle'){echo 'selected';} ?>>Circle
                        </option>
                    </select>
                    <label for="select_button">Changes the shape of the video play button</label>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('Choose a color to match the video player components with your website colors',$this->plugin_name); ?>
                </label>
                <div class="col-lg-10">
                    <input type="text" id="theme_primarycolor" name="swarmify_theme_primarycolor"
                        value="<?php echo esc_attr(get_option('swarmify_theme_primarycolor')); ?>"
                        class="color-field" />
                    <label for="theme_primarycolor">Customize the video players controls to match the colors of your
                        website.</label>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><a id="panel-advanced-btn" role="button">Advanced Options</a></h3>
        </div>
        <div class="panel-body" id="panel-advanced-body">
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('Toggle alternate layout method on or off',$this->plugin_name); ?>
                </label>
                <div class="col-sm-12">
                    <input <?php if(get_option('swarmify_toggle_layout') == 'on'){echo 'checked';} ?> id="toggle_layout"
                        value="on" type="checkbox" name="swarmify_toggle_layout">
                    <label for="toggle_layout">Alternate layout method (if you are experiencing odd video sizing or
                        full-screen issues, try this)</label>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('Toggle upload acceleration on or off',$this->plugin_name); ?>
                </label>
                <div class="col-sm-12">
                    <input <?php if(get_option( 'swarmify_toggle_uploadacceleration', 'on' ) == 'on'){echo 'checked';} ?> id="toggle_uploadacceleration" value="on" type="checkbox" name="swarmify_toggle_uploadacceleration">
                    <label for="toggle_uploadacceleration">Turn on/off upload acceleration (if you have trouble with uploads, try turning this off)</label>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-12 main_label">
                    <?php _e('(Pro Plan Only) Set a watermark',$this->plugin_name); ?>
                </label>
                <div class="col-sm-12">
                    <!-- value="<?php echo esc_attr(get_option('swarmify_watermark')); ?>" -->
                    <input type="button" id="swarmify_watermark_button" name="swarmify_watermark_button" class="button"
                        value="Select" />
                        <input type="button" id="swarmify_watermark_remove_btn" class="button" value="Remove" />
                        <input type='hidden' name='swarmify_watermark' id='swarmify_watermark' 
                            <?php echo(' value="'.esc_attr(get_option('swarmify_watermark', '')).'" '); ?> >
                        <label for="swarmify_watermark_button">Set an image/logo to watermark on the video
                            player</label>
                        <div class='image-preview-wrapper' style='min-height: 10px;margin:1em;'>
                            <img id='swarmify_watermark_preview'
                                <?php if( get_option( 'swarmify_watermark', '' ) !== '' ) { echo(' src="'.esc_attr(get_option('swarmify_watermark')).'" ');} ?>
                                style='max-height: 100px; width: 100px;'>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="form-group">
                    <label class="col-sm-12 main_label">
                        <?php _e('(Pro Plan Only) Add VAST Ad URL',$this->plugin_name); ?>
                    </label>
                    <div class="col-sm-12">
                        <input type="text" id="ads_vasturl" name="swarmify_ads_vasturl"
                            value="<?php echo esc_attr(get_option('swarmify_ads_vasturl')); ?>" class="" />
                        <label for="ads_vasturl">Set the VAST URL from your ad management platform (Adsense for Video,
                            DFP,
                            SpotX, etc)</label>
                    </div>
                </div>
        </div>
    </div>
    <hr>
    <input class="swarmify-button" type="submit" value="Save Settings">
  </form>
  <hr>
  <?php
    $name ='settings';
    require('footer-display.php'); 
  ?>
</div>