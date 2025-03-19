<?php

namespace Hestia\WebApp\Installers\VitePress;
use Hestia\WebApp\Installers\BaseSetup as BaseSetup;

class VitePressSetup extends BaseSetup {
	protected $appInfo = [
		"name" => "VitePress",
		"group" => "ssg",
		"enabled" => true,
		"version" => "latest",
		"thumbnail" => "vp-thumb.png",
	];
 
	protected $appname = "vitepress";
	protected $config = [
		"form" => [
			"vitepress_folder" => ["type" => "text", "value" => "", "placeholder" => "/", "label" => "Install Directory"],
			"nodeJS_version" => [
				"type" => "select",
				"options" => [
					"v18: LTS Hydrogen",
					"v20: LTS Iron",
					"v22: LTS Jod",
				],
			],
		],
		"database" => false,
		"resources" => [
		],
		"server" => [
			"nginx" => [],
			"php" => [
				"supported" => ["7.3", "7.4", "8.0", "8.1", "8.2"],
			]
		],
	];

	public function install(array $options = null) {
		global $hcpp;
		$parse = explode( '/', $this->getDocRoot() );
		$options['user'] = $parse[2];
		$options['domain'] = $parse[4];
		$hcpp->run( 'v-invoke-plugin vitepress_setup ' . escapeshellarg( json_encode( $options ) ) );
		return true;
	}
}
