# 10up WordPress Repository Template for CloudFormation One Click Cluster Template

# Using AWS Secret Manager

1. Attach an IAM policy to the EC2 IAM role granting access to the ASM secret with the credentials and secure data
2. Install `aws/aws-sdk-php` with Composer
    ```json
    "require": {
      "aws/aws-sdk-php": "^3"
    }
    ```
3. Add the following lines at the top of the `wp-config.php` file
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
4. Populate the values
    ```php
    /** Database credentials */
    define( 'DB_HOST', $secrets['WP_DB_HOST'] );
    define( 'DB_NAME', $secrets['WP_DB_NAME'] );
    define( 'DB_USER', $secrets['WP_DB_USER'] );
    define( 'DB_PASSWORD', $secrets['WP_DB_PASSWORD'] );
    ```

***Note: You'll need to install the `s3-uploads` plugin with composer instead of using the [build-wp.sh](ci-scripts/build-wp.sh) script, other way it will stop working***