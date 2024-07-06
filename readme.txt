=== WP Attachments ===
Contributors: Milmor
Tags: attachments, media, file, list, classicpress
Donate link: https://www.paypal.me/milesimarco
Requires at least: 4.4
Tested up to: 6.6
Version: 5.0.12
Stable tag: 5.0.12
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Powerful solution to manage and show your WordPress media in posts and pages

== Description ==

WP Attachments is a plugin that enhance the download experience and file managing in WordPress. It adds some features for attachments and **automagically** shows them in posts and pages.
When you upload a file, the download link will be automatically shown after the content without manual insert of the html link in the content.
Includes utilities for **attaching, unattaching or reattaching** assets in the media library.

https://www.youtube.com/watch?v=J7gf0hxl_z8

Demo: [www.sanpellegrinoterme.gov.it](http://www.sanpellegrinoterme.gov.it/documenti/regolamenti/)

= Main Features =
🤖 Automatic function to show your attachments
ℹ️ Backend writing **metabox**
🔃 Fast **Attach**, **Unattach** and **Reattach** files in the "Media" menu
🔢 Download **counter** with anti-spamming system and logged users filter
🧑‍💻 Developer hooks and filters
🛍️ WooCommerce compatibile
🎨 **5 icon packs** to choose from
📜 Support for **pages**, **posts** and **custom post types**
🎢 Customizable themes with many options (title, date, size, caption...)

= Contributions =

* WP Attachments is part of the project [WPGov.it](http://www.wpgov.it), that aims to give Italian Public Government powerful open source solutions to make complete and law-compatible websites.
* Italian community [Porte Aperte sul Web](http://www.porteapertesulweb.it) for beta-testing and ideas.
* Metabox based on [IJ Post Attachments](http://wordpress.org/plugins/ij-post-attachments/)
* Some icons by [Yusuke Kamiyamane](http://p.yusukekamiyamane.com/).

== Installation ==

1. Install the plugin either via the WordPress.org plugin directory, or by uploading the files to your server

2. After activating, you've all done. If you want to customize it, please have a look to Settings -> WP Attachments

3. You will also notice a new metabox while editing a post, page or whatever custom post type. In addition you can find new features in the media page: "Attach"+"Reattach"

== Frequently Asked Questions ==

= How can i hide the list for a certain page? =
While in the edit screen, you will see the plugin metabox that lists every file uploaded to the content. At the bottom-right of this metabox you can easily turn off the automatic listing by checking **Disable**. 

= How can i avoid double listing? =
When you upload a file, you usually click to insert the link in the content. Please note that this action is not required with WP Attachments. When you upload a file, WordPress assigns it to the content ID (even if its link is not inserted as html) and WP Attachments will show it. Instead of clicking "Insert in this page", just click "X" in the upper right corner of the media popup. The file will still be there, and this plugin will show it!

= How can i reorder files? =
Just drag them while editing the page (in WP Attachments metabox or Media Popup)

= Developer Filters =
WP Attachments includes many filters to allow developers easily change its behaviour :)

* **wpatt_list_html** ~ list output ($html > $html)
* **wpatt_before_entry_html** ~ single entry output (before %TAG% parsing) ($html > $html)
* **wpatt_after_entry_html** ~ single entry output (after %TAG% parsing) ($html > $html)
* **wpatt_accepted_formats** ~ alter files to shows ($mime > $boolean)

Examples:

`function my_custom_function( $html ) { //Alter final html
    return $new_html;
}
add_filter( 'wpatt_list_html', 'my_custom_function' );`

`function my_custom_function( $mime ) { //This snippet shows only PDF in the list
    if ( $mime == 'applicationpdf') {
        return true;
    }
	return false;
}
add_filter( 'wpatt_accepted_formats', 'my_custom_function' );`


== Screenshots ==

1. The list generated

2. Simple and intuitive options

3. Demo from [www.sanpellegrinoterme.gov.it](http://www.sanpellegrinoterme.gov.it)

4. Metabox (back-end)

5. WP Attachments allows you to attach, unattach and reattach files in "Media" screen


== Changelog ==

= 5.0.6 20230215 =
* Compatibility check
* Security fixes
* Minor changes

= 5.0.4 20211020 =
* Compatibility check
* Linked development workflows on Github - https://github.com/WPGov/wp-attachments
* Minor changes

= 5.0 20201128 =
* **WooCommerce** compatibile: add files to your clients' orders
* Huge improvements and technical changes under the hood
* Rewritten add_media popup, with custom behaviour
* Removed various files, including old translation .po translation files
* Added support for native translate.wordpress.org translation system. Help us!
* Minor changes

= 4.4.2 20200429 =
* Minor improvements

= 4.4.1 20200220 =
* Compatibility check

= Version 4.4 18/11/2017 =
* **Fixed** critical bug for missing icons in WP 4.9
* Some problems may occur in previous WP versions for icons. Please update!

= Version 4.3.6 10/01/2017 =
* **Tested** with WP 4.7
* **Fixed** php warning in custom post types with capabilities mapped

= Version 4.3.4 06/07/2016 =
* 4.6 compatibility check

= Version 4.3.3 02/04/2016 =
* Fixed bug in metabox date
* Tested with WP 4.5

= Version 4.3.2 19/02/2016 =
* Fixed php notice error when debug active

= Version 4.3.1 18/02/2016 =
* Auto exclusion of dropdown if attachments < 2

= Version 4.3 23/12/2015 =
* Added option to show a dropdown for ordering
* Various improvements and bugfix
* Added support for translate.wordpress.org

= Version 4.2 02/08/2015 =
* Added developer functions and filters
* Minor improvements
* ReadMe changes (FAQS added)

= Version 4.1.2 6/07/2015 =
* Added es_ES translations by Joaquín Alejandro Duro Arribas
* ReadMe changes

= Version 4.1.1 1/06/2015 =
* Tested with latest beta version
* Readme changes

= Version 4.1 26/04/2015 =
* **Added** option to exlude logged-in users from download counter
* **Fixed** "extended" template
* Minor bugfixes
* Minor readme.txt changes

= Version 4.0.2 =
* Correct bug of 404 error when file title contains special characters (download counter only)
* Minor improvements

= Version 4.0.1 =
* Fixed download error 404 for some permalinks when counter enable

= Version 4.0 =
* New and better metabox
* Added download counter function
* Added icon themes
* Added multiple schemes
* Redesigned options
* Performance improvements (2x faster)
* Minor improvements

= Version 3.7 05/03/2015 =
* Added filter and option to restrict the plugin to single and page views
* Minor performance improvements
* Fixed wrong version in settings panel
* Better style for attachments list

= Version 3.6.1 28/02/2015 =
* Added check for password protected posts

= Version 3.6 28/02/2015 =
* Improved performance
* Improved metabox (faster & nicer)
* Improved option panel
* Added opton to deactivate the plugin on certain pages

= Version 3.5.6 21/10/2014 =
* **Fixed** css conflict with italian schools WordPress theme "pasw2015"

= Version 3.5.5 05/09/2014 =
* **Added** plugin icon
* **Added** serbian translation sr_RS
* readme.txt changes

= Version 3.5.4 26/07/2014 =
* **Fixed** possible conflict with other plugins (ex. Members)
* **Fixed** missing translation of "Update" button in the options panel (world-wide)

= Version 3.5.3 20/07/2014 =
* **Added** Brazilian Portuguese translations by Henrique Avila Vianna

= Version 3.5.2 15/07/2014 =
* **Fixed** add media button not being displayed when no file attached
* **Fixed** metabox not showing up for add-new "admin" pages
* **Upgraded** uploader to be the same as WordPress' integrated
* **Improved** add media button style to exactly match the WordPress' standard

= Version 3.5.1 12/07/2014 =
* **Tested** and working on WordPress 4.0 (beta)

= Version 3.5 09/07/2014 =
* Added **unattach** link in media admin page
* Added **unattach** link in page/post/cpt editor
* **Improved** back-end metabox
* **Improved** capability handling for attach/reattach/unattach functions
* **Improved** some variables handling
* **Added** function to check if a file doesn't exist and must skip filesize calculation (in order to avoid front-end errors)

= Version 3.4 08/07/2014 =
* Added **reattach** link in media admin page
* Added **attach** link for unattached file in media admin page

= Version 3.3 28/03/2014 =
* **Added** option to open files in a new tab [in Settings -> WP Attachments]
* **Fixed** conflict with "Members" plugin causing the metabox appearing in its options page
* **Added** an "eye" icon in the editor metabox showing that the file will be listed front-end

= Version 3.2.3 26/03/2014 =
* **Solved** conflict with some newsletter plugins by addind code for avoiding the attachments list if the given post id is null

= Version 3.2.2 12/03/2014 =
* Added support for **MP3**, **ODT**, **ODS**
* Changed size text for small files: now showing "< 1KB" instead of "n B"
* Better compatibility for Internet Explorer

= Version 3.2.1 03/03/2014 =
* Added wp_enqueue_style for loading css style
* Performance improved

= Version 3.2 03/03/2014 =
* New localization system. English & Italian already translations included
* New back-end metabox. That's in beta, but i'm sure you will like it!
* Better option page

= Version 3.1.4 4/11/2013 =
* Fixed missing 'Backend.php' (this function will be available in the next versions (3.2+)

= Version 3.1.3 27/10/2013 =
* Another bugfix

= Version 3.1.2 27/10/2013 =
* Fixed a bug causing content not to be loaded in some cases

= Version 3.1.1 19/10/2013 =
* List title is now hidden correctly

= Version 3.1 15/09/2013 =
* **Fixed** missing icon for images
* **Added** option to exclude images from being listed
* **Improved** settings page layout

= Version 3.0.4 24/08/2013 =
* **Readme** minor changes

= Version 3.0.3 23/08/2013 =
* **Improved** Css appearance

= Version 3.0.2 23/08/2013 =
* **Fixed** activation error: unespected output - 1 charater

= Version 3.0.1 22/08/2013 =
* **Fixed** missing external shortcode rendering
* **Fixed** reduntant css code
* **Improved** loop
* List header doesn't show up anymore for empty attachments

= Version 3.0 22/08/2013 =
* **Added** Css for showing icons
* **Added** file size
* **Added** attachment data
* **Added** options panel

= Version 2.0 04/07/2013 =
* First functional release. Enjoy!

= Version 1.0 07/01/2012 =
* First Release
