#!/usr/bin/env bash

PRODUCTION_DOCS_PATH="Resources/Public/Documentation"

if [[ "$IS_DDEV_PROJECT" == 1 || "$IS_DDEV_PROJECT" == "true" ]]; then
  >&2 echo "Can not run inside ddev container. Please use this command on host only."
  exit 1
fi

if ! command -v docker &> /dev/null; then
  >&2 echo "Docker is not installed on system, please install docker on your host to proceed."
  exit 1
fi

if ! docker run --rm --pull always -v "$(pwd)":/project -t ghcr.io/typo3-documentation/render-guides:latest --config=Documentation "$@"; then
  echo "Something went wrong on rendering the docs. Please check the output and affected documentation files of EXT:solr and fix them."
  exit 1;
else
  echo "Great job, the documentation is fine."
fi

if [[ "$BUILD_DOCS_FOR_PRODUCTION" == 1 || "$BUILD_DOCS_FOR_PRODUCTION" == "true" ]]; then
  rm -Rf "${PRODUCTION_DOCS_PATH}" "Documentation.HTML"
  mv -v "Documentation-GENERATED-temp" "${PRODUCTION_DOCS_PATH}"
  ln -s "${PRODUCTION_DOCS_PATH}" "Documentation.HTML"
  rm -Rf "Documentation-GENERATED-temp"
fi

exit 0;
