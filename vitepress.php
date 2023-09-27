<?php
/**
 * Extend the HestiaCP Pluginable object with our VitePress object for
 * allocating VitePress instances.
 * 
 * @version 1.0.0
 * @license GPL-3.0
 * @link https://github.com/virtuosoft-dev/hcpp-vitepress
 * 
 */

if ( ! class_exists( 'VitePress') ) {
    class VitePress {
        /**
         * Constructor, listen for the invoke, POST, and render events
         */
        public function __construct() {
            global $hcpp;
            $hcpp->vitepress = $this;
            $hcpp->add_action( 'hcpp_invoke_plugin', [ $this, 'setup' ] );
            $hcpp->add_action( 'hcpp_render_body', [ $this, 'hcpp_render_body' ] );
            $hcpp->add_action( 'hcpp_new_domain_ready', [ $this, 'hcpp_new_domain_ready' ] );
            $hcpp->add_action( 'hcpp_nginx_reload', [ $this, 'hcpp_nginx_reload' ] );
        }

        /**
         * Check for flag and add .vitepress to nginx.conf and nginx.ssl.conf on reload
         */
        public function hcpp_nginx_reload( $cmd ) {
            if ( ! file_exists( '/tmp/vitepress_domains') ) return $cmd;
            $vitepress_domains = json_decode( file_get_contents( '/tmp/vitepress_domains' ), true );
            unlink ( '/tmp/vitepress_domains' );
            foreach ( $vitepress_domains as $vitepress_domain ) {
                $user = $vitepress_domain['user'];
                $domain = $vitepress_domain['domain'];
                global $hcpp;
                $nginx_conf = "/home/$user/conf/web/$domain/nginx.conf";
                if ( file_exists( $nginx_conf ) ) {
                    $contents = file_get_contents( $nginx_conf );
                    $contents = str_replace( 
                        'location ~ /\.(?!well-known\/|file) {',
                        'location ~ /\.(?!well-known\/|file|vitepress) {',
                        $contents
                    );
                    file_put_contents( $nginx_conf, $contents );
                    $hcpp->log("Modified $nginx_conf for VitePress");
                }else{
                    $hcpp->log("Could not find $nginx_conf for VitePress");
                }

                $nginx_ssl_conf = "/home/$user/conf/web/$domain/nginx.ssl.conf";
                if ( file_exists( $nginx_ssl_conf ) ) {
                    $contents = file_get_contents( $nginx_conf );
                    $contents = str_replace( 
                        'location ~ /\.(?!well-known\/|file) {',
                        'location ~ /\.(?!well-known\/|file|vitepress) {',
                        $contents
                    );
                    file_put_contents( $nginx_ssl_conf, $contents );
                    $hcpp->log("Modified $nginx_ssl_conf for VitePress");
                }else{
                    $hcpp->log("Could not find $nginx_ssl_conf for VitePress");
                }
            }
            return $cmd;
        }

        /**
         * Flag to add .vitepress to nginx.conf and nginx.ssl.conf on reload
         */
        public function hcpp_new_domain_ready( $args ) {
            $vitepress_domains = [];
            $user = $args[0];
            $domain = $args[1];
            if ( file_exists( '/tmp/vitepress_domains') ) {
                $vitepress_domains = json_decode( file_get_contents( '/tmp/vitepress_domains' ), true );
            }
            $vitepress_domains[] = [ 'user' => $user, 'domain' => $domain ];
            file_put_contents( '/tmp/vitepress_domains', json_encode( $vitepress_domains ) );
            return $args;
        }

        /**
         * Setup VitePress with the given options
         */
        public function setup( $args ) {
            if ( $args[0] != 'vitepress_install' ) return $args;
            $options = json_decode( $args[1], true );
            $user = $options['user'];
            $domain = $options['domain'];
            $vitepress_folder = $options['vitepress_folder'];
            if ( $vitepress_folder == '' || $vitepress_folder[0] != '/' ) $vitepress_folder = '/' . $vitepress_folder;
            $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
            $vitepress_folder = $nodeapp_folder . $vitepress_folder;

            global $hcpp;
            $vitepress_root = $hcpp->delLeftMost( $vitepress_folder, $nodeapp_folder );
            $hcpp->log( "VitePress root: $vitepress_root" );
            $hcpp->log( "VitePress folder: $vitepress_folder" );
            $hcpp->log( "NodeApp folder: $nodeapp_folder" );

            // Create the nodeapp folder and install vitepress
            $cmd = "mkdir -p " . escapeshellarg( $vitepress_folder ) . " && ";
            $cmd .= "chown -R $user:$user " . escapeshellarg( $vitepress_folder ) . " && ";
            $cmd .= 'runuser -l ' . $user . ' -c "cd ' . escapeshellarg( $vitepress_folder ) . ' && ';
            $cmd .= 'export NVM_DIR=/opt/nvm && source /opt/nvm/nvm.sh && npm install vitepress"';
            $hcpp->log( $cmd );
            $hcpp->log( shell_exec( $cmd ) );

            // Copy over nodeapp files
            $hcpp->copy_folder( __DIR__ . '/nodeapp', $vitepress_folder, $user );

            // Update config.mjs base
            $config_mjs = file_get_contents( $vitepress_folder . '/docs/.vitepress/config.mjs' );
            $config_mjs = str_replace( '%base%', $vitepress_root, $config_mjs );
            file_put_contents( $vitepress_folder . '/docs/.vitepress/config.mjs', $config_mjs );

            // Cleanup, allocate ports, prepare nginx and start services
            $hcpp->nodeapp->shutdown_apps( $nodeapp_folder );
            $hcpp->nodeapp->allocate_ports( $nodeapp_folder );
            
            // Update proxy and restart nginx
            if ( $nodeapp_folder . '/' == $vitepress_folder ) {
                $hcpp->run( "change-web-domain-proxy-tpl $user $domain NodeApp" );
            }else{
                $hcpp->nodeapp->generate_nginx_files( $nodeapp_folder );
                $hcpp->nodeapp->startup_apps( $nodeapp_folder );
                $hcpp->run( "restart-proxy" );
            }
        }

        /**
         * Customize the install page
         */
        public function hcpp_render_body( $args ) {
            global $hcpp;
            if ( $args['page'] !== 'setup_webapp') return $args;
            if ( strpos( $_SERVER['REQUEST_URI'], '?app=VitePress' ) === false ) return $args;
            $content = $args['content'];
            $user = trim($args['user'], "'");
            $shell = $hcpp->run( "list-user $user json")[$user]['SHELL'];

            // Suppress Data loss alert
            $content = '<style>.alert.alert-info{display:none;}</style>' . $content;
            $msg = "";
            if ( $shell != 'bash' ) {

                // Display bash requirement
                $content = '<style>.form-group{display:none;}</style>' . $content;
                $msg = '<div style="margin-top:-20px;width:75%;"><span>';
                $msg .= 'Cannot contiue. User "' . $user . '" must have bash login ability.</span>';
                $msg .= '<script>$(function(){$(".l-unit-toolbar__buttonstrip.float-right a").css("display", "none");});</script>';
            }elseif ( !is_dir('/usr/local/hestia/plugins/nodeapp') ) {
        
                // Display missing nodeapp requirement
                $content = '<style>.form-group{display:none;}</style>' . $content;
                $msg = '<div style="margin-top:-20px;width:75%;"><span>';
                $msg .= 'Cannot contiue. The VitePress Quick Installer requires the NodeApp plugin.</span>';
                $msg .= '<script>$(function(){$(".l-unit-toolbar__buttonstrip.float-right a").css("display", "none");});</script>';
            }
            // Remove PHP version selector
            $msg .= '<script>
                document.addEventListener("DOMContentLoaded", function() { 
                    $("label[for=webapp_php_version]").parent().css("display", "none");
                });
            </script>';
            if ( strpos( '<div class="app-form">', $content ) !== false ) {
                $content = str_replace( '<div class="app-form">', '<div class="app-form">' . $msg, $content ); // Hestia 1.6.X
            }else{
                $content = str_replace( '<h1 ', $msg . '<h1 style="padding-bottom:0;" ', $content ); // Hestia 1.7.X
            }
            $args['content'] = $content;
            return $args;
        }
    }
    new VitePress();
}