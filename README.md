<table width="100%">
	<tr>
		<td align="left" width="70">
			<strong>Infinite Uploads</strong><br />
			Lightweight "drop-in" for storing WordPress uploads on our cloud instead of the local filesystem.
		</td>
		<td align="right" width="20%">
			<a href="https://travis-ci.org/humanmade/our cloud-Uploads">
				<img src="https://travis-ci.org/humanmade/our cloud-Uploads.svg?branch=master" alt="Build status">
			</a>
			<a href="http://codecov.io/github/humanmade/our cloud-Uploads?branch=master">
				<img src="http://codecov.io/github/humanmade/our cloud-Uploads/coverage.svg?branch=master" alt="Coverage via codecov.io" />
			</a>
		</td>
	</tr>
	<tr>
		<td>
			A <strong><a href="https://uglyrobot.com/">UglyRobot</a></strong> project.
		</td>
		<td align="center">
			<img src="https://miro.medium.com/fit/c/256/256/0*wLMglTa464-sq7Im.png" width="100" />
		</td>
	</tr>
</table>

Infinite Uploads is a WordPress plugin to transparently and simply store uploads on our cloud. Infinite Uploads aims to be a lightweight "drop-in" for storing uploads on our cloud instead of the local filesystem.

It's focused on providing a highly robust cloud interface with no "bells and whistles", WP-Admin UI or much otherwise. It comes with some helpful WP-CLI commands for migrating your existing library to or from our cloud in the most efficient way possible.


Getting Set Up
==========

**Install Using Composer**

```
composer require uglyrobot/infinite-uploads
```

**Install Manually**

If you do not use Composer to manage plugins or other dependencies, you can install the plugin manually. Download the `manual-install.zip` file from the [Releases page](https://github.com/uglyrobot/infinite-uploads/releases) and extract the ZIP file to your `plugins` directory.

You can also `git clone` this repository, and run `composer install` in the plugin folder to pull in it's dependencies.

---

Once you've installed the plugin, add the following constants to your `wp-config.php`:

```PHP
define( 'INFINITE_UPLOADS_BUCKET', 'my-bucket' );
```

You must then enable the plugin. To do this via WP-CLI use command:

```
wp plugin activate infinite-uploads
```

The plugin name must match the directory you have cloned Infinite Uploads into;
If you're using Composer, use
```
wp plugin activate infinite-uploads
```


The next thing that you should do is to verify your setup. You can do this using the `verify` command
like so:

```
wp infinite-uploads verify
```

Once you have migrated your media to our cloud with any of the below methods, you'll want to enable Infinite Uploads: `wp infinite-uploads enable`.

Listing files on our cloud
==========

Infinite Uploads comes with a WP-CLI command for listing your files in our cloud for debugging etc.

```
wp infinite-uploads ls [<path>]
```

Uploading files to our cloud
==========

If you have an existing media library with attachment files, use the below command to copy them all to our cloud from local disk.

```
wp infinite-uploads upload-directory <from> <to> [--verbose]
```

Passing `--sync` will only upload files that are newer in `<from>` or that don't exist on our cloud already. Use `--dry-run` to test.

For example, to migrate your whole uploads directory to our cloud, you'd run:

```
wp infinite-uploads upload-directory /path/to/uploads/ uploads
```

There is also an all purpose `cp` command for arbitrary copying to and from our cloud.

```
wp infinite-uploads cp <from> <to>
```

Note: as either `<from>` or `<to>` can be our cloud or local locations, you must specify the full our cloud location via `iu://mybucket/mydirectory` for example `cp ./test.txt iu://mybucket/test.txt`.

Cache Control
==========

You can define the default HTTP `Cache-Control` header for uploaded media using the
following constant:

```PHP
define( 'INFINITE_UPLOADS_HTTP_CACHE_CONTROL', 30 * 24 * 60 * 60 );
	// will expire in 30 days time
```

Default Behaviour
==========

As Infinite Uploads is a plug and play plugin, activating it will start rewriting image URLs to our cloud, and also put
new uploads on our cloud. Sometimes this isn't required behaviour as a site owner may want to upload a large
amount of media to our cloud using the `wp-cli` commands before enabling Infinite Uploads to direct all uploads requests
to our cloud. In this case one can define the `INFINITE_UPLOADS_AUTOENABLE` to `false`. For example, place the following
in your `wp-config.php`:

```PHP
define( 'INFINITE_UPLOADS_AUTOENABLE', false );
```

To then enable Infinite Uploads rewriting, use the wp-cli command: `wp infinite-uploads enable` / `wp infinite-uploads disable`
to toggle the behaviour.

URL Rewrites
=======
By default, Infinite Uploads will use the canonical CDN URIs for referencing the uploads, i.e. `cdn.infiniteuploads.com/[user]/[site]/[file path]`.

Infinite Uploads' URL rewriting feature can be disabled if the current website does not require it, nginx proxy to our CDN etc. In this case the plugin will only upload files to our cloud bucket.
```PHP
// disable URL rewriting alltogether
define( 'INFINITE_UPLOADS_DISABLE_REPLACE_UPLOAD_URL', true );
```

Offline Development
=======

While it's possible to use Infinite Uploads for local development (this is actually a nice way to not have to sync all uploads from production to development),
if you want to develop offline you have a couple of options.

1. Just disable the Infinite Uploads plugin in your development environment.
2. Define the `INFINITE_UPLOADS_USE_LOCAL` constant with the plugin active.

Option 2 will allow you to run the Infinite Uploads plugin for production parity purposes, it will essentially mock
our cloud with a local stream wrapper and actually store the uploads in your WP Upload Dir `/iu/` subdirectory.

Credits
=======
Inspired by and heavily adapted from "S3-Uploads" created by Human Made and Written and maintained by [Joe Hoyle](https://github.com/joehoyle).
