<?php
define('ABSPATH', __DIR__ . '/');
function plugin_dir_path($f){return dirname($f).'/';}
function plugin_dir_url($f){return 'https://example.test/plugin/';}
function add_action(...$a){}
function add_filter(...$a){}
function register_activation_hook(...$a){}
function register_deactivation_hook(...$a){}
require_once dirname(__DIR__) . '/ideasdi-redaccion-gerizim.php';
if (!defined('IDG_VERSION') || IDG_VERSION !== '0.4.0-RC1.6.3') { fwrite(STDERR,"FAIL version\n"); exit(1); }
foreach(['IDG_Traceability','IDG_Traceability_Outbox','IDG_Traceability_Client','IDG_Traceability_Admin','IDG_Traceability_Recapture'] as $class){ if(!class_exists($class)){fwrite(STDERR,"FAIL $class\n");exit(1);} }
echo "PASS plugin load smoke\n";
