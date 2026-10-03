<?php
/**
 * Deploy
 *
 * @package Pronamic\Orbis\SiteGround
 */

declare(strict_types=1);

namespace Deployer;

require 'recipe/common.php';

set( 'plugin_slug', 'orbis-siteground' );

set( 'build_path', './build/' );

$deployer_import = getenv( 'DEPLOYER_IMPORT' );

if ( false !== $deployer_import && '' !== $deployer_import ) {
	import( $deployer_import );
}

task(
	'build',
	function () {
		runLocally( 'composer run-script build' );
	}
);

task(
	'deploy:update_code',
	function () {
		upload( '{{build_path}}/orbis-siteground/', '{{release_path}}' );
	}
);

task(
	'deploy:symlink_plugin',
	function () {
		run( 'ln -sfn {{deploy_path}}/current {{plugins_dir}}/{{plugin_slug}}' );
	}
);

after( 'deploy:symlink', 'deploy:symlink_plugin' );

task(
	'deploy',
	[
		'build',
		'deploy:prepare',
		'deploy:publish',
	]
);
