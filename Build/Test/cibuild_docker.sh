#!/bin/bash

## BASH COLORS
# shellcheck disable=SC2034
BLACK='\033[0;30m'
RED='\033[0;31m'
GREEN='\033[0;32m'
BROWN_ORANGE='\033[0;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
LIGHT_GRAY='\033[0;37m'
DARK_GRAY='\033[1;30m'
LIGHT_RED='\033[1;31m'
LIGHT_GREEN='\033[1;32m'
YELLOW='\033[1;33m'
LIGHT_BLUE='\033[1;34m'
LIGHT_PURPLE='\033[1;35m'
LIGHT_CYAN='\033[1;36m'
WHITE='\033[1;37m'
NC='\033[0m'

## DEFAULT VOLUME EXPORT from original Solr Dockerfile:
# see Dockerfile in desired version https://github.com/docker-solr/docker-solr/blob/abb53a7/8.5/Dockerfile
DEFAULT_IMAGE_VOLUME_EXPORT_PATH="/var/solr"

## Local docker things.
LOCAL_VOLUME_PATH=${HOME}"/solrcivolume"
LOCAL_VOLUME_NAME="solrci-volume"
LOCAL_IMAGE_NAME="solrci-image:latest"
LOCAL_CONTAINER_NAME="solrci-container"

## In Schema configuration available solr cores
AVAILABLE_CORES=(
  "core_de"
  "core_en"
  "core_ar"
  "core_hy"
  "core_eu"
  "core_ptbr"
  "core_my"
  "core_ca"
  "core_zh"
  "core_cs"
  "core_da"
  "core_nl"
  "core_fi"
  "core_fr"
  "core_gl"
  "core_el"
  "core_hi"
  "core_hu"
  "core_id"
  "core_it"
  "core_ja"
  "core_km"
  "core_ko"
  "core_lo"
  "core_no"
  "core_fa"
  "core_pl"
  "core_pt"
  "core_ro"
  "core_ru"
  "core_es"
  "core_sv"
  "core_th"
  "core_tr"
  "core_uk"
  "core_rs"
  "core_ie"
  "core_lv"
)

DOCKER_CONTAINER_START_NUMBER=0
# shellcheck disable=SC2120
backupApacheLogs () {
  LOGS_DIRECTORY_SUFFIX=${1}
  if [[ -z "${VAR}" ]]
  then
    ((DOCKER_CONTAINER_START_NUMBER++))
    LOGS_DIRECTORY_SUFFIX="${DOCKER_CONTAINER_START_NUMBER}"
  fi
  echo -e "${GREEN}  Store Apache Solr log files to provide them as artifacts for docker container from step ${LOGS_DIRECTORY_SUFFIX} ${NC}"
  sudo cp -R "$LOCAL_VOLUME_PATH/logs" /tmp/docker_image_logs_"${LOGS_DIRECTORY_SUFFIX}"
  sudo chmod -R 777 /tmp/docker_image_logs_*
}

cleanUp ()
{
  backupApacheLogs
  echo "Clean up the artifacts"

  echo -n "  stop container $LOCAL_CONTAINER_NAME"
  prettyPrintOrExitOnError $? "$(docker stop "$LOCAL_CONTAINER_NAME" 2>&1)"

  echo -n "  remove container $LOCAL_CONTAINER_NAME"
  prettyPrintOrExitOnError $? "$(docker container rm "$LOCAL_CONTAINER_NAME" 2>&1)"

  echo -n "  remove volume $LOCAL_VOLUME_NAME"
  prettyPrintOrExitOnError $? "$(docker volume rm "$LOCAL_VOLUME_NAME" 2>&1)"

  echo -n "  remove \"$LOCAL_VOLUME_PATH\" directory"
  prettyPrintOrExitOnError $? "$(sudo rm -Rf "$LOCAL_VOLUME_PATH" 2>&1)"
  echo
}

prettyPrintOrExitOnError ()
{
  local output=${2}
  # shellcheck disable=SC2015
  if [[ "${1}" -eq 0 ]]
  then
    echo -en "${GREEN}"' ✔' "${output}"'\n'"${NC}"
  else
    echo -en "${RED}"' ✘' "${output}"'\n'"${NC}"
    cleanUp
    exit 1
  fi
}

prettyPrint ()
{
  local output=${2}
  # shellcheck disable=SC2015
  if [[ "${1}" -eq 0 ]]
  then
    echo -en "${GREEN}"' ✔' "${output}"'\n'"${NC}"
  else
    echo -en "${RED}"' ✘' "${output}"'\n'"${NC}"
  fi
}

isHTTP200 ()
{
  response=$(curl --write-out %\{http_code\} --silent --output /dev/null "${1}")
  if [[ "$response" -eq "200" ]]
  then
     return 0
  fi

  return 1
}

isPathOwnedBySolr ()
{
  local status=0
  for path in "$@"
  do
    pathOwner=$(sudo stat -c '%u' "$path")
    if [[ "$pathOwner" == 8983 ]]
    then
      echo -e '  '"${GREEN}"'✔'"${NC}" "$path"
    else
      echo -e '  '"${RED}"'✘'"${NC}" "$path"
      status=1
    fi
  done

  return $status
}

run_container ()
{
  echo -n "Creating testvolume"
  prettyPrintOrExitOnError $? "$(mkdir -p "$LOCAL_VOLUME_PATH" 2>&1)"

  echo -n "Add permissions to solr group"
  prettyPrintOrExitOnError $? "$(sudo chmod g+w "$LOCAL_VOLUME_PATH" 2>&1)"

  echo -n "Changing group of volume to solr user"
  prettyPrintOrExitOnError $? "$(sudo chown 8983:8983 "$LOCAL_VOLUME_PATH" 2>&1)"

  echo -n "Create named volume inside of ~/solrcivolume"
  prettyPrintOrExitOnError $? "$(docker volume create --name "$LOCAL_VOLUME_NAME" --opt type=none --opt device="$LOCAL_VOLUME_PATH" --opt o=bind 2>&1)"

  echo -n "Starting container"
  prettyPrintOrExitOnError $? "$(docker run --name="$LOCAL_CONTAINER_NAME" -d -p 127.0.0.1:8998:8983 -v "$LOCAL_VOLUME_NAME":"$DEFAULT_IMAGE_VOLUME_EXPORT_PATH" "$LOCAL_IMAGE_NAME" 2>&1)"
}

isCoreAvailable ()
{
  if ! isHTTP200 "http://localhost:8998/solr/${1}/select"
  then
    return 1
  fi

  if ! isHTTP200 "http://localhost:8998/solr/${1}/mlt?q=*"
  then
    return 2
  fi
  return 0
}

isCoreUnavailable ()
{
  if ! isCoreAvailable "$1"
  then
    return 0
  fi
  return 1
}

pingCore ()
{
  # shellcheck disable=SC2015
  if ! isHTTP200 "http://localhost:8998/solr/${1}/admin/ping"
  then
    return 1
  fi
  return 0
}

getExpandedListOfPathsAsSudo ()
{
  if [[ $EUID -ne 0 ]]
  then
    echo "Function is unusable as non root user, please call function as root."
    return 1
  fi

  local paths=(
    "${1}"/data
    "${1}"/data/data
    "${1}"/data/configsets/ext_solr_*/conf/
    "${1}"/data/configsets/*/conf/_schema_analysis*.json
  )
  echo "${paths[@]}"
}

assertVolumeExportHasNotBeenChanged ()
{
  echo -en "${LIGHT_CYAN}Check Dockerfile's VOLUME definition has not been changed.${NC}"
  local EXPORTED_VOLUME
  EXPORTED_VOLUME=$(docker image inspect --format='{{ range $a, $b := .Config.Volumes }}{{ printf "%s " $a }}{{end}}' $LOCAL_IMAGE_NAME)
  if [[ "$EXPORTED_VOLUME" == "$DEFAULT_IMAGE_VOLUME_EXPORT_PATH " ]]
  then
    prettyPrintOrExitOnError 0
  else
    prettyPrintOrExitOnError 1 "${RED}"'\n  The VOLUME definition of image has been changed to "'"$EXPORTED_VOLUME"'".\n\n"'"${NC}"
  fi
}

assertDataPathIsCreatedByApacheSolr ()
{
  local DATA_PATH
  DATA_PATH="$LOCAL_VOLUME_PATH""/data/data"
  echo -en "\nWaiting for data directory: ""$DATA_PATH"
  while true
  do
    ((iteration++))
    # wait 10 seconds(80 times 0.125s)
    if [[ $iteration -gt 80 ]]
    then
      echo -ne "${RED}"'\nTimeout by awaiting of data directory.\nApache Solr would normally have to do this.\n\n'"${NC}"
      cleanUp
      exit 1
    fi

    if sudo test -d "$DATA_PATH"
    then
      prettyPrintOrExitOnError 0
      return 0
    fi

    sleep 0.125
  done
}

assertCoresAreUp ()
{
  echo -e "\n${LIGHT_CYAN}Waiting for cores to boot up: ${NC}"

  local cores=("$@")
  local iteration=1
  local MAX_ITERATIONS=5
  while true
  do
    for key in "${!cores[@]}"
    do

      if ! pingCore "${cores[$key]}"
      then
        if [[ $iteration -lt $MAX_ITERATIONS ]]
        then
          continue
        fi
        echo -en "  ""${cores[$key]}"
        prettyPrint 1 " last try"
      else
        echo -en "  ""${cores[$key]}"
        unset 'cores[key]'
        prettyPrint 0
      fi
    done

    if [[ "${#cores[@]}" -eq 0 ]]
    then
      return 0
    fi

    if [[ $iteration -ge $MAX_ITERATIONS ]]
    then
      echo -ne "${RED}"'\nTimeout by pinging the cores.\n\n'"${NC}"
      backupApacheLogs
      cleanUp
      exit 1
    fi

    echo -ne "${YELLOW}"'Waiting 5 seconds to retry contacting the failed cores...\n'"${NC}"
    sleep 5
    ((iteration++))
  done
}

assertCoresAreQueriable ()
{
  echo -e "\n${LIGHT_CYAN}Check the cores are queriable:${NC}"

  for core in "$@"
  do
    echo -n "  $core"
    if isCoreAvailable "$core"
    then
      prettyPrintOrExitOnError 0
    else
      prettyPrintOrExitOnError 1
    fi
  done
}

assertNecessaryPathsAreOwnedBySolr ()
{
  echo -e "\n${LIGHT_CYAN}Check paths are owned by solr(8983):${NC}"
  local paths
  # shellcheck disable=SC2207
  paths=($(sudo /bin/bash -c "$(declare -f getExpandedListOfPathsAsSudo); getExpandedListOfPathsAsSudo $LOCAL_VOLUME_PATH"))

  if ! isPathOwnedBySolr "${paths[@]}"
  then
    echo -e "${RED}"'\nThe image has files, which are not owned by solr(8983) user.\n Please fix this issue.'"${NC}"
    cleanUp
    exit 1
  fi
}

assertCoresAreSwitchableViaEnvVar ()
{
  echo -e "\n${LIGHT_CYAN}Check the cores are disabled except desired by \$TYPO3_SOLR_ENABLED_CORES env:${NC}"
  cleanUp

  echo -n "Starting container"
  prettyPrintOrExitOnError $? "$(docker run --env TYPO3_SOLR_ENABLED_CORES='german english danish' --name="$LOCAL_CONTAINER_NAME" -d -p 127.0.0.1:8998:8983 -v "$LOCAL_VOLUME_NAME":"$DEFAULT_IMAGE_VOLUME_EXPORT_PATH" "$LOCAL_IMAGE_NAME" 2>&1)"

  ENABLED_CORES=(
    "core_de"
    "core_en"
    "core_da"
  )

  SOME_DISABLED_CORES=(
    "core_fi"
    "core_fr"
    "core_gl"
    "core_el"
    "core_hi"
    "core_hu"
    "core_id"
    "core_it"
    "core_ja"
  )

  echo -e "\n${LIGHT_CYAN}Check enabled cores are available:${NC}"
  assertCoresAreUp "${ENABLED_CORES[@]}"
  assertCoresAreQueriable "${ENABLED_CORES[@]}"

  echo -e "\n${LIGHT_CYAN}Check few other cores are really disabled:${NC}"
  for core in "${SOME_DISABLED_CORES[@]}"
  do
    echo -n "  $core is disabled"
    prettyPrintOrExitOnError $? "$(isCoreUnavailable "$core" 2>&1)"
  done

}

### run the tests

assertVolumeExportHasNotBeenChanged

run_container
assertCoresAreUp "${AVAILABLE_CORES[@]}"

assertCoresAreQueriable "${AVAILABLE_CORES[@]}"

assertDataPathIsCreatedByApacheSolr
assertNecessaryPathsAreOwnedBySolr

assertCoresAreSwitchableViaEnvVar

cleanUp

echo -e "${GREEN}"'\nAll checks passed successfully!\n'"${NC}"

exit 0
