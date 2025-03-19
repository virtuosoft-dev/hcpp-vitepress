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
        public $supported = ['18','19','20','21','22'];

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
                $html .= '<div class="u-mb10">
                              The VitePress instance lives inside the "nodeapp" folder (next to "public_html"). It can be a
                              standalone instance in the domain root, or in a subfolder using the <b>Install Directory</b> 
                              field above.
                          </div>';
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
            if ( count( $args ) < 0 ) return $args;
            global $hcpp;
            
            // Install VitePress on supported NodeJS versions
            if ( $args[0] == 'vitepress_install' ) {

                // Get list of installed and supported NodeJS versions
                $versions = $hcpp->nodeapp->get_versions();
                $majors = [];

                foreach( $versions as $ver ) {
                    $major = $hcpp->getLeftMost( $ver['installed'], '.' );

                    // Check for supported version
                    if ( in_array( $major, $this->supported ) ) {
                        $cmd = 'nvm use ' . $major . ' && echo "~" && npm show vitepress version --no-color && ';
                        $cmd .= 'npm list -g vitepress --depth=0 --no-color';
                        $parse  = $hcpp->runuser('', $cmd );
                        $latest_pkg = trim( $hcpp->delLeftMost( $parse, '~' ) );
                        $latest_pkg = $hcpp->getLeftMost( $latest_pkg, "\n" );
                        $current_pkg = trim( $hcpp->delLeftMost( $parse . '@', '@' ) );
                        $current_pkg = $hcpp->getLeftMost( $current_pkg, "\n" );


                        // Check if vitepress is missing or outdated
                        if ( $current_pkg !== $latest_pkg ) {
                            $majors[] = $major;
                        }
                    }
                }

                // Install VitePress on supported NodeJS versions
                if ( count( $majors ) > 0 ) {
                    $hcpp->nodeapp->do_maintenance( $majors, function( $stopped ) use( $hcpp, $majors ) {
                        foreach( $majors as $major ) {
                            $cmd = "nvm use $major && ";
                            $cmd .= '(npm list -g vitepress || npm install -g --unsafe-perm vitepress --no-interactive) ';
                            $cmd .= '&& npm update -g vitepress --no-interactive < /dev/null';
                            $hcpp->runuser( '', $cmd );
                        }
                    });
                }
            }
            
            // Uninstall VitePress on supported NodeJS versions
            if ( $args[0] == 'vitepress_uninstall' ) {

                // Get list of installed and supported NodeJS versions
                $versions = $hcpp->nodeapp->get_versions();
                $majors = [];
                foreach( $versions as $ver ) {
                    $major = $hcpp->getLeftMost( $ver['installed'], '.' );
                    if ( in_array( $major, $this->supported ) ) {
                        $majors[] = $major;
                    }
                }

                // Uninstall VitePress on supported NodeJS versions
                $hcpp->nodeapp->do_maintenance( $this->supported, function( $stopped ) use( $hcpp, $majors ) {
                    foreach( $majors as $major ) {
                        $cmd = "nvm use $major && npm uninstall -g vitepress --no-interactive";
                        $hcpp->runuser( '', $cmd );
                    }
                });
            }

            // Setup VitePress with the supported NodeJS on the given domain 
            if ( $args[0] == 'vitepress_setup' ) {
                $options = json_decode( $args[1], true );
                $hcpp->log( $options );
                $user = $options['user'];
                $domain = $options['domain'];
                $nodejs_version = trim( $hcpp->getLeftMost( $options['nodeJS_version'], ':' ), "v \t\n\r\0\x0B" );
                $vitepress_folder = $options['vitepress_folder'];
                if ( $vitepress_folder == '' || $vitepress_folder[0] != '/' ) $vitepress_folder = '/' . $vitepress_folder;
                $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
                
                // Create parent nodeapp folder first this way to avoid CLI permissions issues
                mkdir( $nodeapp_folder, 0755, true );
                chown( $nodeapp_folder, $user );
                chgrp( $nodeapp_folder, $user );
                $vitepress_folder = $nodeapp_folder . $vitepress_folder;
                $vitepress_root = $hcpp->delLeftMost( $vitepress_folder, $nodeapp_folder ); 
                $hcpp->runuser( $user, "mkdir -p $vitepress_folder" );

                // Copy over nodeapp files
                $hcpp->copy_folder( __DIR__ . '/nodeapp', $vitepress_folder, $user );
                chmod( $nodeapp_folder, 0755 );

                // Update the .nvmrc file
                file_put_contents( $vitepress_folder . '/.nvmrc', "v$nodejs_version" );

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
                $hcpp->run( "v-restart-proxy" );
            }

            return $args;
        }       
        
        /**
         * Update the nginx configuration for .vitepress
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

        /** 
         * Update the nginx configuration for .vitepress
         */
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