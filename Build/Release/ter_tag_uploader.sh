#!/usr/bin/env bash
EXTENSION_KEY="solr"

# This script is triggered by travis when a build has been triggered and was tagged
#
# See: http://insight.helhum.io/post/140850737265/automatically-upload-typo3-extensions-to-ter-with

echo "PWD: $(pwd)"
echo "Travis tag is: ${TRAVIS_TAG}"

export PATH="$PATH:$HOME/.composer/vendor/bin"

if [ -n "$TRAVIS_TAG" ] && [ -n "$TYPO3_ORG_USERNAME" ] && [ -n "$TYPO3_ORG_PASSWORD" ]; then
  php Build/Release/pre_upload_check.php
  if [ $? -eq 0 ]; then
      echo -e "Preparing upload of release ${TRAVIS_TAG} to TER\n"
      curl -sSL https://raw.githubusercontent.com/alrra/travis-after-all/1.4.4/lib/travis-after-all.js | node
      if [ $? -eq 0 ]; then
         echo "Symlinking current folder to extension folder."
         ln -s $(pwd) ../${EXTENSION_KEY}
         cd ../${EXTENSION_KEY}/

         git reset --hard HEAD && git clean -fx
         echo "Files in this package"

         ls -l

         TAG_MESSAGE=`git tag -n10 -l $TRAVIS_TAG | sed 's/^[0-9.]*[ ]*//g'`
         echo "Uploading release ${TRAVIS_TAG} to TER"
         upload . "$TYPO3_ORG_USERNAME" "$TYPO3_ORG_PASSWORD" "$TAG_MESSAGE"
      fi;
   fi;
else
  echo "Nothing todo"
fi;