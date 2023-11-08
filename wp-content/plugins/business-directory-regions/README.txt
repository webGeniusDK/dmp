= Business Directory Plugin - Regions Module =
Contributors: businessdirectoryplugin.com
Donate link: http://businessdirectoryplugin.com/premium-modules/
Tags: business directory,classifieds,ads
Requires at least: 4.8
Tested up to: 6.1.1
Last Updated: 2023-Feb-27
Stable tag: 5.4.3

== Description ==
This module adds the ability to filter listings based on location in Business Directory plugin.  Allows you
to define what regions to use and to show/hide them on a sidelist.  Requires Business Directory Plugin 2.2 or higher.

The module works with Business Directory Plugin only (http://businessdirectoryplugin.com).

Full documentation here:  https://businessdirectoryplugin.com/knowledge-base/regions-module/

== Installation ==

   1. Download the ZIP file to your local machine.  DO NOT UNPACK THE ZIP FILE.
   
   2. Go to the Admin section of your website for WordPress.
   
   3. Go to Plugins -> Add New
   
   4. Click the "Upload" link at the top.
   
   5. Click the "Browse" button and find the ZIP file from Step 1 on your local machine.  
   
   6. Click "OK" and then "Install Now".
   
   7. When the installation completes, click then "Activate Plugin" link.
   
   8. Installation is now complete.
   
If you have any problems installing the plugin, please post on the [support forum](http://businessdirectoryplugin.com/support-forum/)

You configure your settings under Directory Admin-> Regions.  For more configuration information, please visit:
https://businessdirectoryplugin.com/knowledge-base/regions-module/

== Credits ==

Copyright 2012-5, D. Rodenbaugh 

This module is not included in the core of Business Directory Plugin.
It is a separate add-on premium module and is not subject to the terms of
the GPL license  used in the core package.

This module cannot be redistributed or resold in any modified versions of
the core Business Directory Plugin product. If you have this
module in your possession but did not purchase it via businessdirectoryplugin.com or otherwise
obtain it through businessdirectoryplugin.com please be aware that you have obtained it
through unauthorized means and cannot be given technical support through businessdirectoryplugin.com.


== Changelog ==
= Version 5.4.3 =
* Fix: Fix fatal error "Illegal offset type" on activating the plugin.

= Version 5.4.2 =
* Update: Better translation support.
* Fix: Clear Filter button was throwing an error.
* Fix: Resolve the PHP 8 errors.

= Version 5.1.6 =
* Fix - textdomain on a translatable text in Regions module. (#4577)
* Fix - Adjust regions sidelist styling for narrow directory excerpt view. (#4607)

= Version 5.1.5 =
* New - Add tooltip to autocompleted possible parent regions with information for each result. (#4471)
* Fix - Make [business-directory-region-subregions] shorcode to use "Hide Empty Regions" setting. (#4473)
* Fix - Create Region fields redundant data to prevent module conflicts with cache plugins. (#4471)
* Fix - Fix region taxonomy search on WP dashboard. (#4471)

= Version 5.1.4 =
* New - Create [businessdirectory-region-subregions] shortcode to display subregions link list of a parent region. (#4390)
* New - Include %%ct_wpbdm-region%% SEO replacement (if available) in category view. (#4372)
* Fix - Validate page including regions shortcode is published before including rewrite rules. (#4390)

= Version 5.1.3 =
* Prevent setting wpbdp_regions incorrectly as current view on [businessdirectory-listings] shortcode. (#4361)

= Version 5.1.2 =
* Display sidelist min level setting only when regions is completely configured. (#4323, #4332, #4339)

= Version 5.1.1 =
* Allow regions filtering with categories when Advanced CTP Integration is disabled. (#4271)
* Allow Regions sidelist filtering in quick search. (#4271)
* Include new setting to control Sidelist top level field in Regions Module. (#4305)


= Version 5.1 =
* New - Include "category", "categories" parameters to [businessdirectory-region] shortcode. (#4279)
* Fix - Prevent Regions sidelist on search results view. (#4271)

= Version 5.0.14 =
* Add wpbdp_regions_filter_url to modify Region filtering form action URL. (#4192)


= Version 5.0.13 =
* Prevent PHP deprecated function use.
* Show regions post count, when enabled, for [businessdirectory-regions-browser] shortcode. (#4150)


= Version 5.0.12 =
* Hide/show "Set Filter" button in regions selector depending on selected regions for filtering. (#4106)
* Return to previous page when regions selector filter was submitted without any region selected. (#4106)

= Version 5.0.11 =
* Remove post meta for regions fields. (#4080)

= Version 5.0.10 =
* Improve Regions sidelist integration with map display. (#3849)
* Remove directory slug from regions canonical URLs.
* Remove directory slug from regions URLs.

= Version 5.0.9 =
* Support regions Yoast SEO variable in category view. (#3783)

= Version 5.0.8 =
* Add support for Regions sidelist filter on tag page. (#3741)
* Prevent render specific region as main page but as an independent page. (#3783)

= Version 5.0.7 =
* Allow quick search work with region module without a main keyword. (#3759)

= Version 5.0.6 =
* Add compatibility with Custom Permalinks plugin. (#3622)
* Allow Regions admin table by levels. (#3666)

= Version 5.0.5 =
* Fix reappearing notification to install version 5.0.4.

= Version 5.0.4 =
* Prevent duplicated table filters on Regions. (#3439)

= Version 5.0.3 =
* Prevent SQL error trying to count number of listings on categories and regions. (#3360)

= Version 5.0.2 =
* Fix Quick Search integration with Regions. (#3146)

= Version 5.0.1 =
* Do not show Regions Selector in Checkout pages. (#3154)

= Version 5.0 =
* Updated for Business Directory Plugin 5.0.

= Version 4.1.5 =
* Empty strings are now not allowed as regions slug. (#2988)
* Fix Add Region tab in Regions admin screen. (#3023)

= Version 4.1.4 =
* Fix region filter issues. (#2879)
* Remove unnecessary option from Regions dropdowns. (#2921)

= Version 4.1.3 =
* Remove use of wp_doing_ajax for compatibility with older versions of WP. (#2827)
* Fix conflict between Regions and Events Manager. (#2819)

= Version 4.1.2 =
* Pass context when computing current Region value to prevent fields that shouldn't be hidden from being hidden. (#2773)
* Remove numeric region search terms passed as keywords. (#2775)
* Mark string as translatable (Regions browser shortcode). (#2777)
* Do not make labels lowercase in Regions browser. (#2777)
* Fix sub-category links with missing region information. (#2796)
* Prevent 404 errors from Regions Sidelist links. (#2792)

= Version 4.1.1 =
* Add German translation
* Add error message to Region Shortcode. (#2752)
* URLencode non-latin characters in Region's rewrite rules. (#2747)

= Version 3.6.1 =
* Performance fix to not flush rewrite rules on plugins page

= Version 3.6 =
* Remove cookie/session filtering and start using Regions URLs for everything.
* Honor listing sort/order settings.
* Fix term_link for filter for new Regions URL scheme.
* Allow sidelist to remain open up to the active region.
* Add a Regions search sidebar widget.
* Improved display on mobile devices.
* Removed MyISAM declaration for table installation.
* Improve integration of new regions pages with Google Maps and other modules.
* Make `term_link()` default to region-filtered listings page.

= Version 3.3 =
* Added shortcode "wpbdp_regions_browser" for Craigslist style region browser. 
* Fix conflict with other taxonomies in tax_query array. 
* Change int columns to bigint for scalability reasons.

= Version 1.2 =
* Do not fail when an expected region form field was manually deleted by the admin.
* Workaround WordPress showing incorrect listing counts for the Regions taxonomy. 
* Make Regions cache regeneration work even if some region fields are not present. 
* Fix display of incorrect regions in the sidelist when no region field was visible. 
* Allow region selector to be completely hidden. 
* Show an admin warning when regions is incorrectly configured. (#366, #392)
* Allow listings to be assigned a region admin-side even before the first save. 
* Reset current page variable when changing the current region. 
* Move all settings to "Manage Options" section. 
* Add a display flag specific for the region selector. 
* Fix region fields display so that the order in which the fields appear doesn't affect functionality. 

= Version 1.1 =
* Fixed incompatibility issues with the Google Maps module. .
* Add ability to change regions slug. .
* Fixed various issues related to region filtering.
* Fixed an issue where the region selector was not displayed in category pages. .
* Perform a cleverer region matching when importing CSV files with region fields on them. .
* Fixed category counts being off when a region filter was active. .
* New configuration option to specify if the region selector should appear open or closed by default. .
* Include correct post type in Region archive pages to keep ordering in line with default BD behavior. .


= Version 1.0 =
* Initial version of Regions