# Orbis SiteGround

The Orbis SiteGround plugin compares hosting packages domains against Orbis subscriptions.

## Invoices

SiteGround only provides invoices as PDF. Invoices are processed in two steps:

1. **Upload** – upload the PDFs in the WordPress admin via *SiteGround → Upload invoices*. Each PDF is stored in `wp-content/uploads/orbis-siteground/{year}/{month}/` (upload date), its text is extracted with [`smalot/pdfparser`](https://github.com/smalot/pdfparser) and an unprocessed invoice is created in the `orbis_siteground_invoices` table, keyed by the SHA-256 hash of the PDF. A PDF that was uploaded before is skipped.
2. **Process** – an AI client (for example Claude via the [Orbis MCP server](https://github.com/pronamic/orbis-mcp-server)) finds the unprocessed invoices with `orbis-siteground/search-invoices` (`"status": "unprocessed"`), reads the PDF text with `orbis-siteground/get-invoice` and registers the data, valid against [`json-schemas/siteground-invoice.json`](json-schemas/siteground-invoice.json), with `orbis-siteground/update-invoice`:

```json
{
	"id": 12,
	"invoice": { "document_type": "invoice", "invoice_number": "4869562", "…": "…" }
}
```

- The main values are stored in columns, the complete JSON in `data`. Updating an invoice again replaces the data, an invoice number can only be registered once.
- Every invoice has an `orbis_sg_invoice` post (`/siteground/invoices/`).
- Direct access to the PDFs is denied with a `.htaccess` file, logged in users download the PDF via `admin-post.php?action=orbis_siteground_invoice_pdf&invoice_id={id}`. `get-invoice` can also return the PDF base64 encoded (`include_pdf`).

### AI processing (WP-CLI)

With the WordPress AI client and an AI provider configured, invoices can also be processed from the command line. The stored PDF text is sent to the AI provider with the `siteground-invoice` JSON schema, the response is validated against the schema and the invoice is updated the same way as with `update-invoice`.

```sh
# Process one invoice, processed invoices are processed again.
wp orbis siteground process-invoice 12

# Print the extracted JSON without updating the invoice.
wp orbis siteground process-invoice 12 --dry-run

# Process the queue of unprocessed invoices, oldest first.
wp orbis siteground process-invoices --limit=10
```

The argument is the ID in the SiteGround invoices table, not the WordPress post ID. Invoices that fail in the queue remain unprocessed and are reported, the command continues with the next invoice and exits with an error when one or more invoices failed.

### Invoice lines

The lines of a processed invoice are also stored in the `orbis_siteground_invoice_lines` table, upserted on every `update-invoice`.

- **Account** – SiteGround invoice lines charge a hosting account: `[Renewal: ]<period> <product> - <account name>`, for example `Renewal: 1 Year GrowBig Hosting - example.com`. The account name is often a domain name, but can be any name (for example `Lentis`). The line is linked to the SiteGround account with that name (`account_id`), or `NULL` when there is no such account.
- **Period** – the period a line applies to (`start_date`, `end_date`) is not on the invoice, it is derived from the account data:
  - A renewal starts at the expiration date of the account closest to the invoice date, in the series of the current expiration date of the account plus or minus multiples of the line period. SiteGround bills renewals some days before the account expires.
  - Other lines (new, upgrade, add-on, other), and renewals without account, start at the invoice date.
  - The end date is the start date plus the line period (`1 Year`, `1 month`).

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
