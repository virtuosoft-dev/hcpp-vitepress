 /**
 * Get compatible PM2 app config object with automatic support for .nvmrc, 
 * port allocation, and debug mode.
 */
module.exports = {
    apps: (function() {
        let nodeapp = require('/usr/local/hestia/plugins/nodeapp/nodeapp.js')(__filename);
        nodeapp.script = nodeapp.cwd + '/node_modules/vitepress/bin/vitepress.js';
        const fs = require('fs');
        if ( fs.existsSync(nodeapp.cwd + '/.debug') ) {
            nodeapp.args = 'dev docs --port ' + nodeapp._port + ' --host';
        }else{
            nodeapp.args = 'preview docs --port ' + nodeapp._port;
        }
        return [nodeapp];
    })()
}
