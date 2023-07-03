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

dockrun_t3rd makehtml-no-cache -c make_singlehtml 1

if [[ "$BUILD_DOCS_FOR_PRODUCTION" == 1 || "$BUILD_DOCS_FOR_PRODUCTION" == "true" ]]; then
  rm -Rf "${PRODUCTION_DOCS_PATH}" "Documentation.HTML"
  mv -v "Documentation-GENERATED-temp/Result/project/0.0.0" "${PRODUCTION_DOCS_PATH}"
  ln -s "${PRODUCTION_DOCS_PATH}" "Documentation.HTML"
  rm -Rf "Documentation-GENERATED-temp"
fi

if [[ "$BUILD_DOCS_IN_PDF" == 1 || "$BUILD_DOCS_IN_PDF" == "true" ]]; then
  rm -Rf "Documentation-GENERATED-temp"
  dockrun_t3rd  makeall
  dockrun_t3rd  makehtml -c make_latex 1 -c make_pdf 1
  docker run --rm \
    -v $(pwd):/PROJECT \
    -v $(pwd)/Documentation-GENERATED-temp:/RESULT \
    t3docs/render-documentation:develop \
    makeall -c jobfile /PROJECT/Documentation/jobfile.json
  docker run --rm \
    -v $(pwd)/Documentation-GENERATED-temp/Result/latex:/RESULT \
    --workdir="/RESULT/" \
    thomasweise/docker-texlive-full:latest \
    "./run-make.sh"
    mv Documentation-GENERATED-temp/Result/latex/PROJECT.pdf Documentation.pdf
    rm -Rf "Documentation-GENERATED-temp"
fi
