= This branch... =

This branch contains a fork of Catalyst' moodle-local_aws repository, originally
from https://github.com/catalyst/moodle-local_aws. We used to have the AWS SDK
in the FEATURE-filestorage branch, but we changed when we started using
librelambda (also from Catalyst), which takes advantages of local_aws.

= Upgrade the AWS PHP SDK =

If needed, you can upgrade the AWS PHP SDK (for PHP compatibility, to fix a
bug, ...), copy the following into a new file and run it (or do it manually):

SDKDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
find $SDKDIR -mindepth 1 -maxdepth 1 | grep -v readme_moodle.txt | xargs rm -rf
wget -O $SDKDIR/sdk.zip "http://docs.aws.amazon.com/aws-sdk-php/v3/download/aws.zip"
unzip $SDKDIR/sdk.zip -d $SDKDIR
sed -i -e 's/require/require_once/g' $SDKDIR/aws-autoloader.php
sed -i -e 's#GuzzleHttp/functions.php#GuzzleHttp/functions_include.php#g' $SDKDIR/aws-autoloader.php
rm $SDKDIR/sdk.zip

Then update version.php accordingly.

(That's all from Jordan's script: https://git.in.moodle.com/hosting/moodle/-/blob/8741fbb2069bab9837f01594028b66b71f021384/local/filestorage/sdk/readme_moodle.txt, but that is not up-to-date anymore).

This will most likely bring in a wrong version of GuzzleHttp, so keep reading...

= GuzzleHttp =

TL;DR - Make sure the code in here is the same version being used in Moodle LMS.

As of Moodle 4.2.0 (Q2 2023), GuzzleHttp is part of Moodle LMS, and the version
in core (7.5.0) collides with the one distributed in the AWS PHP SDK releases
(from https://github.com/aws/aws-sdk-php), which is 6.5.x at this time.

So, when GuzzleHttp version changes in core, here's what needs to be done:

# refresh your repository - use the latest branch
git co CLOUD-4.2.0/FEATURE-local_aws
git reset --hard origin/CLOUD-4.2.0/FEATURE-local_aws
cd local/aws/sdk

# Replace the old GuzzleHttp version with the one from core
rm -rf GuzzleHttp
cp -a ../../../lib/guzzlehttp/guzzle GuzzleHttp

# Flatten the whole thing to put the files in place as AWS SDK expects them to be
cd GuzzleHttp
mv src/* .
rmdir src

Commit, push, merge, update version.php, test, ...Update this file when things change.
