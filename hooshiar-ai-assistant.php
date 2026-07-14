<?php
/**
 * Plugin Name:  هوشیار | دستیار هوشمند و جستجوی وب
 * Plugin URI:   https://github.com/kalakish007-art/hooshiar-ai-assistant
 * Description:  چت هوشمند با هوش مصنوعی رایگان + جستجوی وب — بدون نیاز به هیچ API Key
 * Version:      1.0.0
 * Author:       وبرم (Webrom)
 * Author URI:   https://webram.ir
 * Text Domain:  hooshiar
 * License:      GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HOOSHIAR_VERSION', '1.0.0' );
define( 'HOOSHIAR_DIR',     plugin_dir_path( __FILE__ ) );
define( 'HOOSHIAR_URL',     plugin_dir_url( __FILE__ ) );

require_once HOOSHIAR_DIR . 'includes/class-hooshiar.php';

// Init
add_action( 'plugins_loaded', function() {
    new Hooshiar();
});
