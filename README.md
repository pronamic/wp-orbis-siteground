# Orbis SiteGround

The Orbis SiteGround plugin compares hosting packages domains against Orbis subscriptions.

## Invoices

SiteGround only provides invoices as PDF. An AI client (for example Claude via the [Orbis MCP server](https://github.com/pronamic/orbis-mcp-server)) reads the PDF, extracts the data according to the [`json-schemas/siteground-invoice.json`](json-schemas/siteground-invoice.json) JSON schema and uploads both with the `orbis-siteground/upload-invoice` ability:

```json
{
	"invoice": { "document_type": "invoice", "invoice_number": "4869562", "…": "…" },
	"pdf": "JVBERi0xLjQK…",
	"file_name": "invoice-4869562.pdf"
}
```

- The data is stored in the `orbis_siteground_invoices` table (main values in columns, the complete JSON in `data`) with an `orbis_sg_invoice` post per invoice (`/siteground/invoices/`).
- The PDF is stored in `wp-content/uploads/orbis-siteground/{year}/{month}/` of the invoice date. Direct access is denied with a `.htaccess` file, logged in users download the PDF via `admin-post.php?action=orbis_siteground_invoice_pdf&invoice_id={id}`.
- Uploading an invoice with an existing invoice number replaces the stored data and PDF.
- The `orbis-siteground/search-invoices` ability searches the invoices, also by domain or product of the invoice lines.

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
