<?php
/**
 * Extend the HestiaCP Pluginable object with our VitePress object for
 * allocating VitePress instances.
 * 
 * @author Virtuosoft/Stephen J. Carnam
 * @license AGPL-3.0, for other licensing options contact support@virtuosoft.com
 * @link https://github.com/virtuosoft-dev/hcpp-vitepress
 * 
 */

if ( ! class_exists( 'VitePress') ) {
    class VitePress extends HCPP_Hooks{

        /**
         * Customize VitePress install screen
         */ 
        public function hcpp_add_webapp_xpath( $xpath ) {
            if ( ! (isset( $_GET['app'] ) && $_GET['app'] == 'VitePress' ) ) return $xpath;
            global $hcpp;

            // Check for bash shell user
            $username = $_SESSION["user"];
            if ($_SESSION["look"] != "") {
                $username = $_SESSION["look"];
            }
            $shell = $hcpp->run( "v-list-user $username json")[$username]['SHELL'];
            if ( $shell != 'bash' ) {
                $style = '<style>div.u-mb10{display:none;}</style>';
                $html = '<span class="u-mb10">Cannot continue. User "' . $username . '" must have bash login ability.</span>';
                // Insert html into div.form-container
            }else{
                $style = '<style>#webapp_php_version, label[for="webapp_php_version"]{display:none;}</style>';
                $html = '<div class="u-mb10">The VitePress instance lives inside the "nodeapp" folder (adjacent to "public_html"). ';
                $html .= 'It can be a standalone instance in the domain root, or in a subfolder using the ';
                $html .= '<b>Install Directory</b> field above.</div>';
            }
            $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $html );
            $xpath = $hcpp->insert_html( $xpath, '/html/head', $style );
            return $xpath;
        }

        /**
         * Setup VitePress with the given options
         */
        public function hcpp_invoke_plugin( $args ) {
            if ( $args[0] != 'vitepress_install' ) return $args;
            global $hcpp;
            $options = json_decode( $args[1], true );
            $user = $options['user'];
            $domain = $options['domain'];
            $vitepress_folder = $options['vitepress_folder'];
            if ( $vitepress_folder == '' || $vitepress_folder[0] != '/' ) $vitepress_folder = '/' . $vitepress_folder;
            $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
            $vitepress_folder = $nodeapp_folder . $vitepress_folder;
            $vitepress_root = $hcpp->delLeftMost( $vitepress_folder, $nodeapp_folder ); 

            // Create the nodeapp folder and install vitepress
            $cmd = "mkdir -p $vitepress_folder ; cd $vitepress_folder && npm install vitepress";
            $hcpp->runuser( $user, $cmd );

            // Copy over nodeapp files
            $hcpp->copy_folder( __DIR__ . '/nodeapp', $vitepress_folder, $user );
            chmod( $nodeapp_folder, 0751 );

            // Update config.mjs base
            $config_mjs = file_get_contents( $vitepress_folder . '/docs/.vitepress/config.mjs' );
            $config_mjs = str_replace( '%base%', $vitepress_root, $config_mjs );
            file_put_contents( $vitepress_folder . '/docs/.vitepress/config.mjs', $config_mjs );

            // Cleanup, allocate ports, prepare nginx and start services
            $hcpp->nodeapp->shutdown_apps( $nodeapp_folder );
            $hcpp->nodeapp->allocate_ports( $nodeapp_folder );

            // Update proxy and restart nginx
            if ( $nodeapp_folder . '/' == $vitepress_folder ) {
                $hcpp->run( "v-change-web-domain-proxy-tpl $user $domain NodeApp" );
            }else{
                $hcpp->nodeapp->generate_nginx_files( $nodeapp_folder );
                $hcpp->nodeapp->startup_apps( $nodeapp_folder );
            }

            // Update nginx.ssl.conf 404 for .vitepress
            $this->update_nginx( "/home/$user/conf/web/$domain/nginx.conf" );
            $this->update_nginx( "/home/$user/conf/web/$domain/nginx.ssl.conf" );
            $hcpp->run( "v-restart-proxy" );
        }

        /**
         * Update the nginx configuration .vitepress
         */
        public function update_nginx( $nginx_conf ) {
            global $hcpp;
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
        }
    }
    global $hcpp;
    $hcpp->register_plugin( VitePress::class );
}