/**
 * Get compatible PM2 app config object with automatic support for .nvmrc, 
 * and port allocation.
 */
 module.exports = {
    apps: (function() {
        let nodeapp = require('/usr/local/hestia/plugins/nodeapp/nodeapp.js')(__filename);
        nodeapp.linkGlobalModules( ['vitepress'] );
        nodeapp.script = nodeapp.cwd + '/node_modules/vitepress/bin/vitepress.js';
        const fs = require('fs');
        let args = ' docs --port ' + nodeapp._port + ' --host 127.0.0.1';
        if ( fs.existsSync(nodeapp.cwd + '/.debug') ) {
            args = 'dev' + args;
        }else{
            args = 'preview' + args;
        }
        nodeapp.args = args;
        return [nodeapp];
    })()
}
