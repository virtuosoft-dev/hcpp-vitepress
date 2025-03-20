# hcpp-vitepress
A plugin for Hestia Control Panel (via [hestiacp-pluginable](https://github.com/virtuosoft-dev/hestiacp-pluginable) and [hcpp-nodeapp](https://github.com/virtuosoft-dev/hcpp-nodeapp)) that enables hosting a [VitePress](https://vitepress.dev) website or add VitePress to an existing site.

With this plugin installed, a new Quick Installer option will appear. *[Note: User account must have SSH/bash set per NodeApp’s instructions for this plugin to work](https://github.com/virtuosoft-dev/hcpp-nodeapp#using-nodeapp-to-host-a-nodejs-website)*. User accounts can host their own VitePress instance either in the root domain or as a subfolder installation. For instance, it is possible to run WordPress in the root domain while having VitePress installed on the same domain in a subfolder (i.e. https://example.com/vitepress-docs); a great way to create lightening fast documentation that lives outside of WordPress’ complexity.

## Installation
HCPP-VitePress requires an Debian based installation of [Hestia Control Panel](https://hestiacp.com) in addition to an installation of [HestiaCP-Pluginable](https://github.com/virtuosoft-dev/hestiacp-pluginable) *and* [HCPP-NodeApp](https://github.com/virtuosoft-dev/hcpp-nodeapp) to function; please ensure that you have first installed both Pluginable and NodeApp on your Hestia Control Panel before proceeding. Switch to a root user and simply clone this project to the /usr/local/hestia/plugins folder. It should appear as a subfolder with the name `vitepress`, i.e. `/usr/local/hestia/plugins/vitepress`.

First, switch to root user:
```
sudo -s
```

Then simply clone the repo to your plugins folder, with the name `vitepress`:

```
cd /usr/local/hestia/plugins
git clone https://github.com/virtuosoft-dev/hcpp-vitepress vitepress
```

Note: It is important that the destination plugin folder name is `vitepress`.

Be sure to logout and login again to your Hestia Control Panel as the admin user or, as admin, visit Server (gear icon) -> Configure -> Plugins -> Save; the plugin will immediately start installing VitePress depedencies in the background. A notification will appear under the admin user account indicating *”VitePress plugin has finished installing”* when complete. This may take awhile before the options appear in Hestia. You can force manual installation via root level SSH:

```
sudo -s
cd /usr/local/hestia/plugins/vitepress
./install
touch "/usr/local/hestia/data/hcpp/installed/vitepress"
```

## Support the creator
You can help this author's open source development endeavors by donating any amount to Stephen J. Carnam @ Virtuosoft. Your donation, no matter how large or small helps pay for essential time and resources to create MIT and GPL licensed projects that you and the world can benefit from. Click the link below to donate today :)
<div>
         

[<kbd> <br> Donate to this Project <br> </kbd>][KBD]


</div>


<!---------------------------------------------------------------------------->

[KBD]: https://virtuosoft.com/donate

https://virtuosoft.com/donate
