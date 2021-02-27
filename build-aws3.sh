#!/usr/bin/env bash

set -e

if [ ! -f infinite-uploads.php ]; then
    echo 'This script must be run from the repository root.'
    exit 1
fi

for PROG in composer find sed unzip
do
	which ${PROG}
	if [ 0 -ne $? ]
	then
		echo "${PROG} not found in path."
		exit 1
	fi
done

REPO_ROOT=${PWD}
TMP_ROOT="${REPO_ROOT}/build/Aws3"
TARGET_DIR="${REPO_ROOT}/vendor/Aws3"
SOURCE_ZIP="https://docs.aws.amazon.com/aws-sdk-php/v3/download/aws.zip"

if [ -d "${TMP_ROOT}" ]; then
    rm -rf "${TMP_ROOT}"
fi;

mkdir "${TMP_ROOT}"
cd "${TMP_ROOT}"

function log_step() {
    echo
    echo
    echo ${1}
    echo
    echo
}

log_step "Install the latest v3 of the AWS SDK"
mkdir sdk
(
    cd sdk
    curl -sL ${SOURCE_ZIP} -o aws.zip
    unzip aws.zip
    rm aws.zip

    # Delete everything from the SDK except for S3 and Common files.
    find Aws/ -type d -mindepth 1 -maxdepth 1 \
      ! -name S3 \
      ! -name Api \
      ! -name Credentials \
      ! -name Crypto \
      ! -name data \
      ! -name Endpoint \
      ! -name EndpointDiscovery \
      ! -name Arn \
      ! -name Exception \
      ! -name Handler \
      ! -name Multipart \
      ! -name Retry \
      ! -name Signature \
      ! -name ClientSideMonitoring \
      -exec rm -rf {} +

    # Remove tests & docs
    find . -type d -iname tests -exec rm -rf {} +
    find . -type d -iname docs -exec rm -rf {} +

    # TODO: Remove unused classes from the autoloader's classmap.
)

log_step "Install php-scoper for code prefixing"
mkdir scoper
(
    cd scoper
    composer require humbug/php-scoper:^0.5 --update-no-dev --no-interaction
)

log_step "Run the prefixer, adding our namespace prefix" # Prefixed files are written into the ./sdk_prefixed directory.
scoper/vendor/bin/php-scoper add-prefix --prefix="UglyRobot\\Infinite_Uploads\\" --output-dir=sdk_prefixed sdk/
(
    cd sdk_prefixed
    rm -rf composer

    # Set the locale to prevent sed errors from characters with different encoding.
    export LC_ALL=C
    # Perform regex search replace to clean up any missed replacements in string literals (1 literal backslash = 4 in the command)

    OS_NAME=`uname -s`
    if [ "Darwin" = "${OS_NAME}" ]
    then
		find . -type f -name "*.php" -print0 | xargs -0 sed -i '' -E "s:'(Aws|GuzzleHttp|Psr|JmesPath)\\\\\\\\:'UglyRobot\\\\\\\\Infinite_Uploads\\\\\\\\\1\\\\\\\\:g"
		find . -type f -name "*.php" -print0 | xargs -0 sed -i '' -E "s:'\\\\\\\\(Aws|GuzzleHttp|Psr|JmesPath)\\\\\\\\:'UglyRobot\\\\\\\\Infinite_Uploads\\\\\\\\\1\\\\\\\\:g"
		find . -type f -name "*.php" -print0 | xargs -0 sed -i '' -E "s:\"(Aws|GuzzleHttp|Psr|JmesPath)\\\\\\\\:\"UglyRobot\\\\\\\\Infinite_Uploads\\\\\\\\\1\\\\\\\\:g"
    else
		find . -type f -name "*.php" -print0 | xargs -0 sed -i'' -E "s:'(Aws|GuzzleHttp|Psr|JmesPath)\\\\\\\\:'UglyRobot\\\\\\\\Infinite_Uploads\\\\\\\\\1\\\\\\\\:g"
		find . -type f -name "*.php" -print0 | xargs -0 sed -i'' -E "s:'\\\\\\\\(Aws|GuzzleHttp|Psr|JmesPath)\\\\\\\\:'UglyRobot\\\\\\\\Infinite_Uploads\\\\\\\\\1\\\\\\\\:g"
		find . -type f -name "*.php" -print0 | xargs -0 sed -i'' -E "s:\"(Aws|GuzzleHttp|Psr|JmesPath)\\\\\\\\:\"UglyRobot\\\\\\\\Infinite_Uploads\\\\\\\\\1\\\\\\\\:g"
    fi
)

# Delete the target directory if it exists.
if [ -d "${TARGET_DIR}" ]; then
    rm -rf "${TARGET_DIR}"
fi

# Move the prefixed SDK files to the plugin's vendor directory where they are referenced.
mv sdk_prefixed "${TARGET_DIR}"

# Clean up the temporary working directory.
rm -rf "${TMP_ROOT}"

log_step "Done!"
