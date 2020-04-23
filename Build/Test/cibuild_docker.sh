#!/bin/bash


## BASH COLORS
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

## DEFAULT VOLUME EXPORT from original Solr Dockerfile:
# see Dockerfile in desired version https://github.com/docker-solr/docker-solr/blob/abb53a7/8.5/Dockerfile
DEFAULT_IMAGE_VOLUME_EXPORT_PATH="/var/solr"

## Local docker things.
LOCAL_VOLUME_PATH=${HOME}"/solrcivolume"
LOCAL_VOLUME_NAME="solrci-volume"
LOCAL_IMAGE_NAME="solrci-image"
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

prettyPrintOrExitOnError ()
{
  local output=${2}
  [ ${1} -eq 0 ] && echo -en ${GREEN}' ✔\n'${NC} || { echo -en ${RED}' ✘\n'${NC} "${output[@]}"; cleanUp; exit 1; }
}

isHTTP200 ()
{
  response=$(curl --write-out %{http_code} --silent --output /dev/null "${1}")
  if [ $response -eq "200" ] ; then
     return 0
  fi

  return 1
}

isPathOwnedBySolr ()
{
  local status=0
  for path in "$@"
  do
    [ `sudo stat -c '%u' $path` == 8983 ] && echo -e '  '${GREEN}'✔'${NC} $path || { echo -e '  '${RED}'✘'${NC} $path; status=1; }
  done

  return $status;
}

build_image ()
{
  echo -n "Building docker image"
  prettyPrintOrExitOnError $? "$(docker build -t $LOCAL_IMAGE_NAME -f Docker/SolrServer/Dockerfile . 2>&1)"
}

run_container ()
{
  echo -n "Creating testvolume"
  prettyPrintOrExitOnError $? "$(mkdir -p $LOCAL_VOLUME_PATH 2>&1)"

  echo -n "Add permissions to solr group"
  prettyPrintOrExitOnError $? "$(sudo chmod g+w LOCAL_VOLUME_PATH 2>&1)"

  echo -n "Changing group of volume to solr user"
  prettyPrintOrExitOnError $? "$(sudo chown 8983:8983 LOCAL_VOLUME_PATH 2>&1)"

  echo -n "Create named volume inside of ~/solrcivolume"
  prettyPrintOrExitOnError $? "$(docker volume create --name $LOCAL_VOLUME_NAME --opt type=none --opt device=$LOCAL_VOLUME_PATH --opt o=bind 2>&1)"

  echo -n "Starting container"
  prettyPrintOrExitOnError $? "$(docker run --name=$LOCAL_CONTAINER_NAME -d -p 127.0.0.1:8998:8983 -v $LOCAL_VOLUME_NAME:$DEFAULT_IMAGE_VOLUME_EXPORT_PATH $LOCAL_IMAGE_NAME 2>&1)"
}

cleanUp ()
{
  echo "Clean up the artifacts"

  echo -n "  stop container $LOCAL_CONTAINER_NAME"
  prettyPrintOrExitOnError $? "$(docker stop $LOCAL_CONTAINER_NAME 2>&1)"

  echo -n "  remove container $LOCAL_CONTAINER_NAME"
  prettyPrintOrExitOnError $? "$(docker container rm $LOCAL_CONTAINER_NAME 2>&1)"

  echo -n "  remove volume $LOCAL_VOLUME_NAME"
  prettyPrintOrExitOnError $? "$(docker volume rm $LOCAL_VOLUME_NAME 2>&1)"

  echo -n "  remove image LOCAL_IMAGE_NAME"
  prettyPrintOrExitOnError $? "$(docker image rm $LOCAL_IMAGE_NAME 2>&1)"

  echo -n "  remove \"$LOCAL_VOLUME_PATH\" directory"
  prettyPrintOrExitOnError $? "$(sudo rm -Rf $LOCAL_VOLUME_PATH 2>&1)"
}

isCoreAvailable ()
{
  isHTTP200 "http://localhost:8998/solr/${1}/select" || { return 1; }
  isHTTP200 "http://localhost:8998/solr/${1}/mlt?q=*" || { return 1; }
  return 0;
}

pingCore ()
{
  isHTTP200 "http://localhost:8998/solr/${1}/admin/ping" && { return 0; } || { return 1; }
}

assertVolumeExportHasNotBeenChanged ()
{
  echo -n "Check Dockerfile's VOLUME defintion has not been changed"
  local EXPORTED_VULUME=$(docker image inspect --format='{{ range $a, $b := .Config.Volumes }}{{ printf "%s " $a }}{{end}}' $LOCAL_IMAGE_NAME)
  if [[ "$EXPORTED_VULUME" == "$DEFAULT_IMAGE_VOLUME_EXPORT_PATH " ]]; then
    prettyPrintOrExitOnError 0;
  else
    prettyPrintOrExitOnError 1 ${RED}'\n  The VOLUME defintion of image has been changed to "'"$EXPORTED_VULUME"'".\n\n"'"${NC}"
  fi
}

assertAllCoresAreUp ()
{
  echo "Waiting for cores:"

  local cores=("${AVAILABLE_CORES[@]}")
  local iteration=0
  while true ; do
    ((iteration++))
    if [ $iteration -gt 30 ] ; then
      echo -ne ${RED}'\nTimeout by pinging the cores.\n\n'${NC}
      cleanUp
      exit 1;
    fi

    for key in "${!cores[@]}" ; do
      pingCore "${cores[$key]}"
      if [[ $? == 0 ]]; then
        echo -en "  ""${cores[$key]}"
        unset 'cores[key]'
        prettyPrintOrExitOnError 0
      fi
    done

    if [ "${#cores[@]}" -eq 0 ] ; then
      return 0
    fi

    sleep 1
  done
}

assertAllCoresAreQueriable ()
{
  echo -e "\nCheck all cores are queriable:"
  for core in "${AVAILABLE_CORES[@]}"
  do
    echo -n "  $core"
    prettyPrintOrExitOnError $? "$(isCoreAvailable $core 2>&1)"
  done
}

assertNeccesseryPathsAreOwnedBySolr ()
{
  echo -e "\nCheck paths are owned by solr(8983)":
  local paths=(
    $LOCAL_VOLUME_PATH/data
    $LOCAL_VOLUME_PATH/data/data
    $LOCAL_VOLUME_PATH/data/configsets/ext_solr_*/conf/
    $LOCAL_VOLUME_PATH/data/configsets/*/conf/_schema_analysis*.json
  )

  isPathOwnedBySolr "${paths[@]}" || { echo -e ${RED}'\nThe image has files, which are not owned by solr(8983) user.\n'${NC}; exit 1; }
}

### run the tests

build_image
assertVolumeExportHasNotBeenChanged

run_container
assertAllCoresAreUp

assertNeccesseryPathsAreOwnedBySolr

assertAllCoresAreQueriable

echo -e "${GREEN}"'\nAll checks passed successfully!\n'"${NC}"

cleanUp

exit 0