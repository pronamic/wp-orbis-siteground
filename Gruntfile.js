module.exports = function( grunt ) {
	// Project configuration.
	grunt.initConfig( {
		// Package
		pkg: grunt.file.readJSON( 'package.json' ),
		
		dirs: {
			ignore: [ 'build', 'node_modules', 'vendor' ].join( ',' ) 
		},

		// Check WordPress version.
		checkwpversion: {
			options: {
				readme: 'readme.txt',
				plugin: 'orbis-siteground.php',
			},
			check: {
				version1: 'plugin',
				version2: 'readme',
				compare: '=='
			},
			check2: {
				version1: 'plugin',
				version2: '<%= pkg.version %>',
				compare: '=='
			}
		},

		// Check text domain errors.
		checktextdomain: {
			options: {
				text_domain: 'orbis-siteground',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src: [
					'**/*.php',
					'!deploy/**',
					'!node_modules/**',
					'!vendor/**',
					'!vendor-bin/**',
					'!wordpress/**'
				],
				expand: true
			}
		},

		// Make POT.
		makepot: {
			target: {
				options: {
					cwd: '',
					domainPath: 'languages',
					type: 'wp-plugin',
					updatePoFiles: true,
					updateTimestamp: false,
					exclude: [
						'build/.*',
						'deploy/.*',
						'node_modules/.*',
						'vendor/.*',
						'vendor-bin/.*',
						'wordpress/.*'
					]
				}
			}
		}
	} );

	grunt.loadNpmTasks( 'grunt-checktextdomain' );
	grunt.loadNpmTasks( 'grunt-checkwpversion' );
	grunt.loadNpmTasks( 'grunt-wp-i18n' );

	// Tasks.
	grunt.registerTask( 'pot', [
		'checktextdomain',
		'makepot'
	] );
};
