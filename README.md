# WordPress Template Repository for AWS CloudFormation Autoscaling Cluster

This template repository follows the 10up way of building, deploying and hosting WordPress sites in a AWS autoscaling group using only native AWS services and solutions.
This repository is used to bootstrap the [WordPress highly available infrastructure with autoscaling](#) AWS Marketplace CloudFormation template.

## Philosophy

As stated in its [mission](https://wordpress.org/about/), WordPress is intended to be simple yet reliable and secure for everyone even without prior development and/or systems experience but WordPress is also powerful enough to run in enterprise level cluster setups with database replication, multiple caching layers, shared storage and many other enterprise features.
At 10up, we have crafted a set of [Best Practices](https://10up.github.io/Engineering-Best-Practices/) and standards that allows engineers to develop, build, deploy and host enterprise level WordPress sites, this repository adapts and packages all this knowledge for AWS autoscaling clusters and other AWS services.

## Database

In WordPress, write database requests happen only when new content is added or updated to the site. E.g. New articles, updates to content, new comments. But read requests happen on every page request, for this reason the database strategy chosen in this repository(since it’s intended to work with AWS services only) is Primary-Replica using RDS which consists of one database node where all the write requests happen and a replica node where only database reads can occur. The Primary-Replica strategy provides a level of high availability, performance and segmentation to WordPress sites.
This repository leverages the [HyperDB](https://github.com/Automattic/HyperDB) plugin to balance the read and write requests to the primary database and its read replica. In the `wp-config.php` file is defined the [DB_SLAVE](wp-config.php?plain=16) constant which is then used in the [db-config.php](db-config.php?plain=231) file to specify the read replica.

## Object Cache

In simple words, object caching is the act of storing database queries results the first time they are called in an in-memory database so the next time the same result is needed it can be served from cache instead of hitting the database again. Since WordPress is heavily dependent on the database, maintaining a good database performance is crucial.
This repository is intended to work with an Amazon ElastiCache instance with Redis leveraging the [redis-cache](https://wordpress.org/plugins/redis-cache/) plugin, configured via the [wp-config.php](wp-config.php?plain=58) file

## Shared Storage

The NFS protocol is often used by the WordPress community to share the `wordpress` directory between the web servers in a cluster, while this is a convenient option it could also introduce performance issues due to network latency. To mitigate this risk, 10up installs the code(`wordpress` directory) directly in each web server and relies on [Object Storage](https://aws.amazon.com/s3/) to share the `uploads` directory only.
[s3-uploads](https://github.com/humanmade/S3-Uploads) is the chosen plugin for this purpose which is also configured via the [wp-config.php](wp-config.php?plain=67) file.

## The Jobs Instance

At 10up, the WordPress jobs server is a key component for a performant cluster setup. It’s a dedicated server in an autoscaling group of one instance(for high availability) with the same code base and connected to the same database used primarily to execute the [WP Cron](https://developer.wordpress.org/plugins/cron/) from the Linux crontab instead of doing so on every page load which is the default WordPress behavior. The jobs server is also used to execute WP-CLI commands and can be used to perform other maintenance tasks. The jobs server doesn’t receive web traffic from the internet or even internal users.
The [WordPress highly available infrastructure with autoscaling](#) AWS Marketplace CloudFormation template already provisions a CodeDeploy job that deploys this repository to a Web autoscaling group as well as a Jobs instance.

## The wp-config.php File

The WordPress configuration file is pre-configured to load data and sensitive information generated by the [WordPress highly available infrastructure with autoscaling](#) AWS Marketplace CloudFormation template in the `/opt/wordpress/credentials.json` file; Inspect this file carefully from the Jobs server before making any changes to the `wp-config.php` file.

### Using AWS Secret Manager

If you want to manage all sensitive information outside the `credentials.json` file generated by the CloudFormation template using AWS Secret Manager instead, follow these steps:

1. Create the secret in AWS Secret Manager console
2. Create an IAM policy granting access to the ASM secret you just created and attach it to the `role-wordpress-ec2` role created by the CloudFormation template.
3. Update the `composer.json` file to install the [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html)
    ```json
    "require": {
      "aws/aws-sdk-php": "^3"
    }
    ```
4. Add the following lines at the top of the `wp-config. php` file to load the AWS SDK for PHP in WordPress and pull the secrets from ASM
    ```php
    /**
     * Load secrets from AWS Secret Manager
    */
    if ( file_exists( __DIR__ . '/wp-content/vendor/autoload.php' )) {
      require __DIR__ . '/wp-content/vendor/autoload.php';
    }

    use Aws\SecretsManager\SecretsManagerClient;

    $client = new SecretsManagerClient([
      'version' => '2017-10-17',
      'region' => 'us-east-1' // Must match the region of the secret
    ]);

    $result = $client->getSecretValue([
      'SecretId' => 'wordpress-ha-stack-secrets' // Name of the secret in ASM
    ]);

    $secrets_data = $result['SecretString'];
    $secrets = json_decode($secrets_data,true);
    /**
     * ========================================
    */
    ```
5. Populate the needed values from ASM
    ```php
    /** Database credentials */
    define( 'DB_HOST', $secrets['WP_DB_HOST'] );
    define( 'DB_NAME', $secrets['WP_DB_NAME'] );
    define( 'DB_USER', $secrets['WP_DB_USER'] );
    define( 'DB_PASSWORD', $secrets['WP_DB_PASSWORD'] );
    ```
**_Note: You'll need to install the s3-uploads plugin with composer instead of using the [build-wp.sh](ci-scripts/build-wp.sh) script, other way it will stop working_**

## CI/CD

At 10up, we aim to keep the code and all configurations under version control, for that reason you’ll normally see the `wp-config.php` file, plugin configuration files and even some PHP and Nginx configuration files in our repositories. At the same time we try to keep our repositories as lightweight as possible; to achieve this we install dependencies in CI via bash scripts and [Composer](https://getcomposer.org/). We strongly advise against installing plugins and themes directly from the WordPress Administrator Dashboard.
In this section we explain how we’ve adapted a reliable CI/CD pipeline for AWS.

### The Build Script

The [ci-scripts/build-wp.sh](ci-scripts/build-wp.sh) script is executed in CI to download WordPress and install plugins and themes with Composer(except for the `s3-uploads` plugin), we strongly recommend you actively maintain the `build-wp.sh` script and the `composer.json` file to update WordPress core, themes and plugins in a regular basis and also to install new dependencies.

### CodeBuild

The [WordPress highly available infrastructure with autoscaling](#) AWS Marketplace CloudFormation template provisions a CodeBuild job that reads the [buildspec.yml](buildspec.yml) file to configure the Continuous Integration process. The build job executes the `build-wp.sh` script and generates the artifact that will be deployed to the Web autoscaling group and the Jobs instance.

### CodeDeploy

The [WordPress highly available infrastructure with autoscaling](#) AWS Marketplace CloudFormation template provisions a CodeDeploy job that reads the [appspec.yml](appspec.yml) file to configure the Continuous Deployment process. The CodeDeploy job deploys the `wordpress` directory and the custom PHP and Nginx configuration files to the Web autoscaling group and the Jobs instance, it also executes the 2 scripts located in the [deploy-scripts](./deploy-scripts) directory to set the correct file permissions of the newly deployed files and reload the Nginx and PHP services.

### Customize the Nginx, PHP and PHP-FPM Configuration

The [configs](./configs) directory contains the Nginx, PHP and PHP-FPM configuration files needed for WordPress, update these files according to your needs; Also, you can add other configuration files in this directory if needed, just  make sure to update the `appspec.yml` file accordingly so they can be copied in the desired location during the deployment process.

## Convert to Multisite(Optional)

By default, this repository is for a WordPress single site but you can easily convert it to a multisite if needed by following these steps:

### Sub-directory

1. SSH into the Jobs instance and convert WordPress to a multisite using the WP-CLI by running the following command from the `/var/www/html/wordpress directory`:
   ```bash
   wp --allow-root core multisite-convert --skip-config
   ```
2. Add the following lines to the `wp-config.php` file in this repository
    ```php
    /** Multisite */
    define('MULTISITE', true);
    define('SUBDOMAIN_INSTALL', false);
    $base = '/';
    define('DOMAIN_CURRENT_SITE', '<your-site>');
    define('PATH_CURRENT_SITE', '/');
    define('SITE_ID_CURRENT_SITE', 1);
    define('BLOG_ID_CURRENT_SITE', 1);
    ```
3. Push or merge your updates into the `main` branch to trigger the CodePipeline to deploy to the Web autoscaling group and Jobs instance
4. Flush the Object Cache by running the following command from the `/var/www/html/wordpress directory`:
    ```bash
    wp --allow-root cache flush --network
    ```

### Sub-domain

1. SSH into the Jobs instance and convert WordPress to a multisite using the WP-CLI by running the following command from the `/var/www/html/wordpress directory`:
    ```bash
   wp --allow-root core multisite-convert --subdomains --skip-config
   ```
2. Add the following lines to the `wp-config.php` file in this repository
    ```php
    /** Multisite */
    define( 'WP_ALLOW_MULTISITE', true );
    define( 'MULTISITE', true );
    define( 'SUBDOMAIN_INSTALL', true );
    $base = '/';
    define( 'DOMAIN_CURRENT_SITE', '<your-site>' );
    define( 'PATH_CURRENT_SITE', '/' );
    define( 'SITE_ID_CURRENT_SITE', 1 );
    define( 'BLOG_ID_CURRENT_SITE', 1 );
    ```
3. Push or merge your updates into the `main` branch to trigger the CodePipeline to deploy to the Web autoscaling group and Jobs instance
4. Flush the Object Cache by running the following command from the `/var/www/html/wordpress directory`:
    ```bash
    wp --allow-root cache flush --network
    ```
