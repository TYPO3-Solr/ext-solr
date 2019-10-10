#!/bin/bash

isHTTP200 ()
{
  response=$(curl --write-out %{http_code} --silent --output /dev/null "${1}")
  if [ $response -eq "200" ] ; then
     return 0
  fi

  return 1
}

assertCoreIsAvailable ()
{
    echo "checking ${1}"
    isHTTP200 "http://localhost:8998/solr/${1}/select" || { echo "${1} failed" ; exit 1; }
    isHTTP200 "http://localhost:8998/solr/${1}/mlt?q=*" || { echo "${1} failed" ; exit 1; }
}

echo "Building docker image"
docker build -t solrci -f Docker/SolrServer/Dockerfile .

echo "Creating testvolume"
mkdir -p ~/solrcivolume

echo "Add permissions to solr group"
sudo chmod g+w ~/solrcivolume

echo "Changing group of volume to solr user"
sudo chown :8983 ~/solrcivolume

echo "Starting container"
docker run -d -p 127.0.0.1:8998:8983 -v ~/solrcivolume:/var/solr/data/data solrci

echo "Waiting for container to boot"
while true; do
    isHTTP200 "http://localhost:8998/solr/"
    if [ $? = 0 ]; then
        echo "solr is up"
        break
    else
        echo "#"
    fi
    sleep 1
done

echo "Give container some time to initialize cores"
sleep 20

assertCoreIsAvailable "core_de"
assertCoreIsAvailable "core_en"
assertCoreIsAvailable "core_ar"
assertCoreIsAvailable "core_hy"
assertCoreIsAvailable "core_eu"
assertCoreIsAvailable "core_ptbr"
assertCoreIsAvailable "core_my"
assertCoreIsAvailable "core_ca"
assertCoreIsAvailable "core_zh"
assertCoreIsAvailable "core_cs"
assertCoreIsAvailable "core_da"
assertCoreIsAvailable "core_nl"
assertCoreIsAvailable "core_fi"
assertCoreIsAvailable "core_fr"
assertCoreIsAvailable "core_gl"
assertCoreIsAvailable "core_el"
assertCoreIsAvailable "core_hi"
assertCoreIsAvailable "core_hu"
assertCoreIsAvailable "core_id"
assertCoreIsAvailable "core_it"
assertCoreIsAvailable "core_ja"
assertCoreIsAvailable "core_km"
assertCoreIsAvailable "core_ko"
assertCoreIsAvailable "core_lo"
assertCoreIsAvailable "core_no"
assertCoreIsAvailable "core_fa"
assertCoreIsAvailable "core_pl"
assertCoreIsAvailable "core_pt"
assertCoreIsAvailable "core_ro"
assertCoreIsAvailable "core_ru"
assertCoreIsAvailable "core_es"
assertCoreIsAvailable "core_sv"
assertCoreIsAvailable "core_th"
assertCoreIsAvailable "core_tr"
assertCoreIsAvailable "core_uk"
assertCoreIsAvailable "core_rs"
assertCoreIsAvailable "core_ie"
assertCoreIsAvailable "core_lv"

echo "all checks passed"
exit 0

