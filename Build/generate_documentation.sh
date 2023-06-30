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

if ! command -v dockrun_t3rd &> /dev/null; then
  echo "The command \"dockrun_t3rd\" is not initialized on system."
  echo "Making \"dockrun_t3rd\" available in current script."
  if [[ "$(docker images -q ghcr.io/t3docs/render-documentation 2> /dev/null)" == "" ]]; then
    docker pull ghcr.io/t3docs/render-documentation && docker tag ghcr.io/t3docs/render-documentation t3docs/render-documentation
  fi
  # shellcheck disable=SC2034
  DOCKRUN_FN_QUIET=1
  # shellcheck disable=SC1090
  source <(docker run --rm ghcr.io/t3docs/render-documentation show-shell-commands)
fi

dockrun_t3rd makehtml-no-cache

if [[ "$BUILD_DOCS_FOR_PRUCTION" == 1 || "$BUILD_DOCS_FOR_PRUCTION" == "true" ]]; then
  rm -Rf "${PRODUCTION_DOCS_PATH}"
  mv -v "Documentation-GENERATED-temp/Result/project/0.0.0" "${PRODUCTION_DOCS_PATH}"
  ln -sf "${PRODUCTION_DOCS_PATH}" "Documentation.HTML"
  rm -Rf "Documentation-GENERATED-temp"
fi
