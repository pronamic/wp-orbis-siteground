# Orbis SiteGround

The Orbis SiteGround plugin compares hosting packages domains against Orbis subscriptions.

## Deploy

The plugin can be deployed with [Deployer](https://deployer.org/). The hosts are not part of this repository, they are imported from a local file via the `DEPLOYER_IMPORT` environment variable:

```sh
DEPLOYER_IMPORT=~/deployer-orbis.php vendor/bin/dep deploy
```

Or use the Composer script, which defaults to `~/deployer-orbis.php`:

```sh
composer deploy
```

The imported file should define the host(s) and the `deploy_path` and `plugins_dir` settings, for example:

```php
<?php

namespace Deployer;

host( 'orbis.example.com' )
	->set( 'remote_user', 'deployer' )
	->set( 'deploy_path', '~/deployments/orbis-siteground' )
	->set( 'plugins_dir', '~/public_html/wp-content/plugins' );
```

The deploy builds the plugin locally (`composer build`), uploads the build to a new release in `deploy_path` and symlinks `{{deploy_path}}/current` to `{{plugins_dir}}/orbis-siteground`.
