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
            //$hcpp->add_action( 'hcpp_render_body', [ $this, 'hcpp_render_body' ] );
        }

        /**
         * Setup VitePress with the given options
         */
        public function setup( $args ) {
            if ( $args[0] != 'vitepress_install' ) return $args;
            $options = json_decode( $args[1], true );
            $user = $options['user'];
            $domain = $options['domain'];
            $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
            $vitepress_folder = $options['vitepress_folder'];
            global $hcpp;
            $vitepress_root = $hcpp->delLeftMost( $vitepress_folder, $nodeapp_folder );

            // Create the nodeapp folder and install vitepress
            $cmd = "mkdir -p " . escapeshellarg( $vitepress_folder ) . " && ";
            $cmd .= "chown -R $user:$user " . escapeshellarg( $vitepress_folder );
            $cmd .= 'runuser -l ' . $user . ' -c "cd ' . escapeshellarg( $vitepress_folder ) . ' && ';
            $cmd .= 'export NVM_DIR=/opt/nvm && source /opt/nvm/nvm.sh && nvm use v18 && ';
            $cmd .= 'npm install vitepress"';
            shell_exec( $cmd );

            // Copy over nodeapp files
            $hcpp->copy_folder( __DIR__ . '/nodeapp', $vitepress_folder, $user );

            // Cleanup, allocate ports, prepare nginx and start services
            $hcpp->nodeapp->shutdown_apps( $nodeapp_folder );
            $hcpp->nodeapp->allocate_ports( $nodeapp_folder );

            // TODO: write base definition with $vitepress_root
            
            // TODO: Add vitepress to nginx.conf and nginx.ssl.conf, change existing
            // location block to include vitepress
            // location ~ /\.(?!well-known\/|file|vitepress) {
            //     deny all;
            //     return 404;
            // }

            // Update proxy and restart nginx
            if ( $nodeapp_folder . '/' == $vitepress_folder ) {
                $hcpp->run( "change-web-domain-proxy-tpl $user $domain NodeApp" );
            }else{
                $hcpp->nodeapp->generate_nginx_files( $nodeapp_folder );
                $hcpp->nodeapp->startup_apps( $nodeapp_folder );
                $hcpp->run( "restart-proxy" );
            }
        }
    }
    new VitePress();
}