#!/usr/bin/env bash

# Usage:
#	sudo ./install-multi-solr-existing-tomcat.sh
#	sudo ./install-multi-solr-existing-tomcat.sh -s 4.8.1
#	sudo ./install-multi-solr-existing-tomcat.sh -l english,german,french
#	sudo ./install-multi-solr-existing-tomcat.sh -s 4.8.1 -l english,german,french

clear

DEFAULT_SOLR_VERSION=4.8.1
EXT_SOLR_VERSION=3.0
EXT_SOLR_PLUGIN_VERSION=1.2.0 # for solr version older than 4x
EXT_SOLR_PLUGIN_ACCESS_VERSION=2.0
EXT_SOLR_PLUGIN_UTILS_VERSION=1.1
EXT_SOLR_PLUGIN_LANG_VERSION=3.1
JAVA_VERSION=7

# Tomcat specific settings
TOMCAT_USER="tomcat6"
TOMCAT_GROUP="tomcat6"
TOMCAT_HOME="/usr/share/tomcat6"
TOMCAT_BASE="/var/lib/tomcat6"
TOMCAT_CONFIG_DIR="/etc/tomcat6"
TOMCAT_LOG_DIR="/var/log/tomcat6"
TOMCAT_TMP_DIR="/tmp/tomcat6-tmp"
TOMCAT_WORK_DIR="/var/cache/tomcat6"
TOMCAT_CONTEXT_DIR="${TOMCAT_CONFIG_DIR}/Catalina/localhost"
TOMCAT_WEBAPP_DIR="/var/lib/tomcat6/webapps"

GITBRANCH_PATH="release-$EXT_SOLR_VERSION.x"

AVAILABLE_LANGUAGES="arabic,armenian,basque,brazilian_portuguese,bulgarian,burmese,catalan,chinese,czech,danish,dutch,english,finnish,french,galician,german,greek,hindi,hungarian,indonesian,italian,japanese,khmer,korean,lao,norwegian,persian,polish,portuguese,romanian,russian,spanish,swedish,thai,turkish,ukrainian"

usage()
{
cat << EOF
usage: sudo $0 options

OPTIONS:
   -s      Solr versions to install, e.g. "4.7.0" or "4.5.0,4.6.0"
   -l      Languages to install, e.g. "english" or "english,german"
   -h      Show this help
EOF
}

SOLR_VERSION=""
LANGUAGES=""

while getopts "h:s:l:" OPTION
do
     case $OPTION in
         h)
             usage
             exit 1
             ;;
         s)
             SOLR_VERSION=$OPTARG
             ;;
         l)
             LANGUAGES=$OPTARG
             ;;
         ?)
             usage
             exit
             ;;
     esac
done

if [ -z "$LANGUAGES" ]
then
  LANGUAGES=$AVAILABLE_LANGUAGES
fi

if [ -z "$SOLR_VERSION" ]
then
  SOLR_VERSION=$DEFAULT_SOLR_VERSION
fi

# replace , with whitespaces
LANGUAGES=$(echo $LANGUAGES|sed 's/,/ /g')
# replace , with whitespaces
SOLR_VERSION=$(echo $SOLR_VERSION|sed 's/,/ /g')

clear

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

progressfilt ()
{
	local flag=false c count cr=$'\r' nl=$'\n'
	while IFS='' read -d '' -rn 1 c
	do
		if $flag
		then
			printf '%c' "$c"
		else
			if [[ $c != $cr && $c != $nl ]]
			then
				count=0
			else
				((count++))
				if ((count > 1))
				then
					flag=true
				fi
			fi
		fi
	done
}

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

# wgetresource
# usage: wgetresource relative/filepath/inside/resourcesdir [justcheck]
# second parameter is optional, if set, do not download, only check if resource exists
wgetresource ()
{
	local wget_result

	if [ $BRANCH_TEST_RETURN -eq "0" ]
	then
  		RESOURCE="https://raw.githubusercontent.com/TYPO3-Solr/ext-solr/$GITBRANCH_PATH/Resources/"$1
	else
		RESOURCE="https://raw.githubusercontent.com/TYPO3-Solr/ext-solr/master/Resources/"$1
	fi

	if [ "$2" ]
	then
		# If second parameter is set, just check if resource exists, no output
		wget -q -O /dev/null --no-check-certificate $RESOURCE
	else
		echo "wget $RESOURCE"
		wget --progress=bar:force --no-check-certificate $RESOURCE 2>&1 | progressfilt
	fi

	# return wget error code
	return $?
}

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

# color echo http://www.faqs.org/docs/abs/HTML/colorizing.html

black="\033[30m"
red="\033[31m"
green="\033[32m"
yellow="\033[33m"
blue="\033[34m"
magenta="\033[35m"
cyan="\033[36m"
white="\033[37m"


# Color-echo, Argument $1 = message, Argument $2 = color
cecho ()
{
	local default_msg="No message passed."

	# Defaults to default message.
	message=${1:-$default_msg}

	# Defaults to black, if not specified.
	color=${2:-$black}

	echo -e "$color$message"

	# Reset text attributes to normal + without clearing screen.
	tput sgr0

	return
}

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Checking requirements." $green

PASSALLCHECKS=1

# test if release branch exists, if so we'll download from there
wget --no-check-certificate -q -O /dev/null https://raw.githubusercontent.com/TYPO3-Solr/ext-solr/$GITBRANCH_PATH/Resources/Solr/solr.xml
BRANCH_TEST_RETURN=$?

# Make sure only root can run this script
if [[ $EUID -ne 0 ]]
then
	cecho "This script must be run as root." $red
	exit 1
fi

java -version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR couldn't find Java (Oracle Java is recommended)." $red
	PASSALLCHECKS=0
fi

JAVA_VERSION_INSTALLED=$(java -version 2>&1 | grep -Eom1 "[._0-9]{5,}")
# extract the main Java version from 1.7.0_11 => 7
JAVA_VERSION_INSTALLED=${JAVA_VERSION_INSTALLED:2:1}
# check if java version is 7 or newer
if [ $JAVA_VERSION_INSTALLED -lt $JAVA_VERSION ]
then
  cecho "You have installed Java version $JAVA_VERSION_INSTALLED. Please install Java $JAVA_VERSION or newer." $red
  PASSALLCHECKS=0
fi

wget --version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR couldn't find wget." $red
	PASSALLCHECKS=0
fi

ping -c 1 mirror.dkd.de > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR couldn't ping Apache download mirror, try again using wget" $yellow
	wget -q -O /dev/null http://mirror.dkd.de/apache/
	if [ $? -ne "0" ]
	then
		cecho "ERROR Also couldn't reach the Apache download mirror using wget. Please check your internet connection." $red
		PASSALLCHECKS=0
	fi
fi

unzip -v > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR: couldn't find unzip." $red
	PASSALLCHECKS=0
fi

# Check if solr scheme files etc. for specified languages are available
for LANGUAGE in ${LANGUAGES[*]}
do
	echo -n "Checking availability of language \"$LANGUAGE\": "
	wgetresource Solr/typo3cores/conf/"$LANGUAGE"/schema.xml justcheck
	if [ $? -ne 0 ]
	then
		cecho "ERROR: Could not find Solr configuration files for language \"$LANGUAGE\"" $red
		exit 1
	else cecho "available" $green
	fi
done

if [ $PASSALLCHECKS -eq "0" ]
then
	cecho "Please install all missing requirements or fix any other errors listed above and try again." $red
	exit 1
else
	cecho "All requirements met, starting to install Solr." $green
fi

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Stopping Tomcat." $green
service tomcat6 stop

mkdir -p /opt/solr-tomcat
mkdir -p /opt/solr-tomcat/solr
cd /opt/solr-tomcat/

for SOLR in ${SOLR_VERSION[*]}
do
  SOLR_VERSION_PLAIN=$SOLR_VERSION
  SOLR_VERSION_PLAIN=$(echo $SOLR_VERSION_PLAIN|sed 's/\.//g')

  if [ $SOLR_VERSION_PLAIN -le "400" ]
  then
    SOLR_PACKAGE_NAME="apache-solr"
  else
 	SOLR_PACKAGE_NAME="solr"
  fi

  cd /opt/solr-tomcat
  cecho "Downloading Apache Solr $SOLR" $green
  wget --progress=bar:force http://mirror.dkd.de/apache/lucene/solr/$SOLR_VERSION/$SOLR_PACKAGE_NAME-$SOLR_VERSION.zip 2>&1 | progressfilt
  cecho "Unpacking Apache Solr." $green
  unzip -q $SOLR_PACKAGE_NAME-$SOLR.zip

  cp $SOLR_PACKAGE_NAME-$SOLR/dist/$SOLR_PACKAGE_NAME-$SOLR.war ${TOMCAT_BASE}/webapps/solr-$SOLR.war
  cp -r $SOLR_PACKAGE_NAME-$SOLR/example/solr solr/solr-$SOLR

  if [ $SOLR_VERSION_PLAIN -ge "430" ]
  then
  	cp $SOLR_PACKAGE_NAME-$SOLR/example/lib/ext/*.jar ${TOMCAT_HOME}/lib
  	cp $SOLR_PACKAGE_NAME-$SOLR/example/resources/log4j.properties ${TOMCAT_HOME}/lib/log4j.properties
  fi

  # ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

  cecho "Downloading TYPO3 Solr configuration files." $green
  cd solr/solr-$SOLR
  SOLRDIR=`pwd`

  for LANGUAGE in ${LANGUAGES[*]}
  do
    cecho "Downloading configuration for language: $LANGUAGE" $green

    cd $SOLRDIR
    # create / download $LANGUAGE core configuration
    mkdir -p typo3cores/conf/$LANGUAGE
    cd typo3cores/conf/$LANGUAGE

    wgetresource Solr/typo3cores/conf/$LANGUAGE/protwords.txt
    wgetresource Solr/typo3cores/conf/$LANGUAGE/schema.xml
    wgetresource Solr/typo3cores/conf/$LANGUAGE/synonyms.txt

    if [ $LANGUAGE = "german" ]
    then
      wgetresource Solr/typo3cores/conf/$LANGUAGE/german-common-nouns.txt
    fi

    cd $SOLRDIR/typo3cores/conf
    wgetresource Solr/typo3cores/conf/$LANGUAGE/_schema_analysis_stopwords_$LANGUAGE.json
  done

  # download general configuration in /opt/solr-tomcat/solr/typo3cores/conf/
  cecho "Downloading general configruation" $green
  cd $SOLRDIR/typo3cores/conf
  wgetresource Solr/typo3cores/conf/currency.xml
  wgetresource Solr/typo3cores/conf/elevate.xml
  wgetresource Solr/typo3cores/conf/general_schema_fields.xml
  wgetresource Solr/typo3cores/conf/general_schema_types.xml
  wgetresource Solr/typo3cores/conf/solrconfig.xml

  # download core configuration file solr.xml in /opt/solr-tomcat/solr/
  cd ../..
  rm solr.xml

  wgetresource Solr/solr.xml

  # Set permissions for typo3cores
  cecho "Setting permissions for ${SOLRDIR}/typo3cores/." $green
  chown -R ${TOMCAT_USER}:${TOMCAT_GROUP} ${SOLRDIR}/typo3cores/

  # clean up
  rm -rf bin
  rm -rf conf
  rm -rf data
  rm README.txt

  # copy libs
  cd /opt/solr-tomcat/
  cp -r $SOLR_PACKAGE_NAME-$SOLR/dist solr/solr-$SOLR
  cp -r $SOLR_PACKAGE_NAME-$SOLR/contrib solr/solr-$SOLR

  cecho "Cleaning up." $green
  rm -rf /opt/solr-tomcat/$SOLR_PACKAGE_NAME-$SOLR.zip
  rm -rf /opt/solr-tomcat/$SOLR_PACKAGE_NAME-$SOLR

  # ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

  mkdir solr/solr-$SOLR/typo3lib
  cd solr/solr-$SOLR/typo3lib

  if [ $SOLR_VERSION_PLAIN -ge "400" ]
  then
	cecho "Downloading the Solr TYPO3 plugin for access control. Version: $EXT_SOLR_PLUGIN_ACCESS_VERSION" $green
	wget --progress=bar:force http://www.typo3-solr.com/fileadmin/files/solr/Solr4x/solr-typo3-access-$EXT_SOLR_PLUGIN_ACCESS_VERSION.jar 2>&1 | progressfilt
	wget --progress=bar:force http://www.typo3-solr.com/fileadmin/files/solr/Solr4x/solr-typo3-utils-$EXT_SOLR_PLUGIN_UTILS_VERSION.jar 2>&1 | progressfilt
	wget --progress=bar:force http://www.typo3-solr.com/fileadmin/files/solr/Solr4x/commons-lang3-$EXT_SOLR_PLUGIN_LANG_VERSION.jar 2>&1 | progressfilt
  else
	cecho "Downloading the Solr TYPO3 plugin for access control. Version: $EXT_SOLR_PLUGIN_VERSION" $green
	wget --progress=bar:force http://www.typo3-solr.com/fileadmin/files/solr/solr-typo3-plugin-$EXT_SOLR_PLUGIN_VERSION.jar 2>&1 | progressfilt
  fi

done

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Configuring Apache Tomcat." $green

cd ${TOMCAT_CONTEXT_DIR}

# set property solr.home
for SOLR in ${SOLR_VERSION[*]}
do
  touch solr-$SOLR.xml
  echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>" > solr-$SOLR.xml
  echo "<Context docBase=\"${TOMCAT_BASE}/webapps/solr-$SOLR.war\" debug=\"0\" crossContext=\"true\" >" >> solr-$SOLR.xml
  echo "  <Environment name=\"solr/home\" type=\"java.lang.String\" value=\"/opt/solr-tomcat/solr/solr-$SOLR\" override=\"true\" />" >> solr-$SOLR.xml
  echo "</Context>" >> solr-$SOLR.xml
done

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Starting Tomcat." $green
service tomcat6 start

cecho "Done." $green
cecho "Tomcat is running and available on port 8080." $green
