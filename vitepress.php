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
            $user = $_SESSION["user"];
            if ($_SESSION["look"] != "") {
                $user = $_SESSION["look"];
            }
            $domain = $_GET['domain'];
            $domain = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $domain);
            $shell = $hcpp->run( "v-list-user $user json")[$user]['SHELL'];
            if ( $shell != 'bash' ) {
                $style = '<style>div.u-mb10{display:none;}</style>';
                $html = '<span class="u-mb10">Cannot continue. User "' . $user . '" must have bash login ability.</span>';
            }else{
                $style = '<style>#webapp_php_version, label[for="webapp_php_version"]{display:none;}</style>';
                $html = '<div class="u-mb10">The VitePress instance lives inside the "nodeapp" folder (next to "public_html"). ';
                $html .= 'It can be a standalone instance in the domain root, or in a subfolder using the ';
                $html .= '<b>Install Directory</b> field above.</div>';
            }
            $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $html );
            $xpath = $hcpp->insert_html( $xpath, '/html/head', $style );

            // Remove existing public_html related alert if present
            $alert_div = $xpath->query('//div[@role="alert"][1]');
            if ( $alert_div->length > 0 ) {
                $alert_div = $alert_div[0];
                $alert_div->parentNode->removeChild( $alert_div );
            }

            // Insert our own alert about non-empty nodeapp folder
            $folder = "/home/$user/web/$domain/nodeapp";
            if ( file_exists( $folder ) && iterator_count(new \FilesystemIterator( $folder, \FilesystemIterator::SKIP_DOTS)) > 0 ) {
                $html = '<div class="alert alert-info u-mb10" role="alert">
                        <i class="fas fa-info"></i>
                        <div>
                            <p class="u-mb10">Data Loss Warning!</p>
                            <p class="u-mb10">Your nodeapp folder already has files uploaded to it. The installer will overwrite your files and/or the installation might fail.</p>
                            <p>Please make sure ~/web/' . $domain . '/nodeapp is empty or an empty subdirectory is specified!</p>
                        </div>
                    </div>';
                $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $html, true );
            }
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

            // Create parent nodeapp folder first this way to avoid CLI permissions issues
            mkdir( $nodeapp_folder, 0755, true );
            chown( $nodeapp_folder, $user );
            chgrp( $nodeapp_folder, $user );

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
                $ext = $hcpp->run( "v-list-web-domain '$user' '$domain' json" )[$domain]['PROXY_EXT'];
                $ext = str_replace( ' ', ',', $ext );
                $hcpp->run( "v-change-web-domain-proxy-tpl '$user' '$domain' 'NodeApp' '$ext' 'no'" );
            }else{
                $hcpp->nodeapp->generate_nginx_files( $nodeapp_folder );
                $hcpp->nodeapp->startup_apps( $nodeapp_folder );
            }
            //$hcpp->run( "v-restart-proxy" );
        }       

        /**
         * Update the nginx configuration .vitepress
         */
        public function update_nginx( $file ) {
            global $hcpp;
            if ( file_exists( $file ) ) {
                $contents = file_get_contents( $file );
                $contents = str_replace( 
                    'location ~ /\.(?!well-known\/|file) {',
                    'location ~ /\.(?!well-known\/|file|vitepress) {',
                    $contents
                );
                file_put_contents( $file, $contents );
                $hcpp->log("Modified $file for VitePress");
            }else{
                $hcpp->log("Could not find $file for VitePress");
            }
        }

        public function nodeapp_nginx_confs_written( $folders ) {
            global $hcpp;
            foreach ( $folders as $folder ) {
                if ( file_exists( $folder . '/nginx.conf_nodeapp' ) ) {
                    $content = file_get_contents( $folder . '/nginx.conf_nodeapp' );                    
                    if ( strpos( $content, ':$vitepress_port;') !== false ) {
                        $this->update_nginx( $folder . '/nginx.conf' );
                        $this->update_nginx( $folder . '/nginx.ssl.conf' );
                    }
                }
            }
            return $folders;
        }
    }
    global $hcpp;
    $hcpp->register_plugin( VitePress::class );
}