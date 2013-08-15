#!/usr/bin/env bash

# Usage:
#	sudo ./install-multi-solr.sh
#	sudo ./install-multi-solr.sh -s 3.5.0
#	sudo ./install-multi-solr.sh -l english,german,french
#	sudo ./install-multi-solr.sh -s 3.5.0 -l english,german,french

clear

TOMCAT_VER=6.0.37
DEFAULT_SOLR_VER=3.6.2
EXT_SOLR_VER=2.8
EXT_SOLR_PLUGIN_VER=1.2.0

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

GITBRANCH_PATH="solr_$EXT_SOLR_VER.x"

AVAILABLE_LANGUAGES="arabic,armenian,basque,brazilian_portuguese,bulgarian,burmese,catalan,chinese,czech,danish,dutch,english,finnish,french,galician,german,greek,hindi,hungarian,indonesian,italian,japanese,khmer,korean,lao,norwegian,persian,polish,portuguese,romanian,russian,spanish,swedish,thai,turkish,ukrainian"

usage()
{
cat << EOF
usage: sudo $0 options

OPTIONS:
   -s      Solr versions to install, e.g. "3.6.0" or "3.5.0,3.6.0"
   -l      Languages to install, e.g. "english" or "english,german"
   -h      Show this help
EOF
}

SOLR_VER=
LANGUAGES=
while getopts "h:s:l:" OPTION
do
     case $OPTION in
         h)
             usage
             exit 1
             ;;
         s)
             SOLR_VER=$OPTARG
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

if [ -z "$SOLR_VER" ]
then
  SOLR_VER=$DEFAULT_SOLR_VER
fi

# replace , with whitespaces
LANGUAGES=$(echo $LANGUAGES|sed 's/,/ /g')
# replace , with whitespaces
SOLR_VER=$(echo $SOLR_VER|sed 's/,/ /g')

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
  		RESOURCE="http://forge.typo3.org/projects/extension-solr/repository/revisions/$GITBRANCH_PATH/raw/resources/"$1
	else
		RESOURCE="http://forge.typo3.org/projects/extension-solr/repository/revisions/master/raw/resources/"$1
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
wget --no-check-certificate -q -O /dev/null http://forge.typo3.org/projects/extension-solr/repository/revisions/$GITBRANCH_PATH/raw/
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

wget --version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR couldn't find wget." $red
	PASSALLCHECKS=0
fi

ping -c 1 apache.osuosl.org > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR couldn't ping Apache download mirror, try again using wget" $yellow
	wget -q -O /dev/null http://apache.osuosl.org
	if [ $? -ne "0" ]
	then
		cecho "ERROR also couldn't wget Apache download mirror at Oregon State University Open Source Lab - OSUOSL" $red
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
	wgetresource solr/typo3cores/conf/"$LANGUAGE"/schema.xml justcheck
	if [ $? -ne 0 ]
	then
		cecho "ERROR: Could not find Solr configuration files for language \"$LANGUAGE\"" $red
		exit 1
	else cecho "passed" $green
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

# We are using an existing tomcat installation
#
# cecho "Using the mirror at Oregon State University Open Source Lab - OSUOSL." $green
# cecho "Downloading Apache Tomcat $TOMCAT_VER" $green
# TOMCAT_MAINVERSION=`echo "$TOMCAT_VER" | cut -d'.' -f1`
# wget --progress=bar:force http://apache.osuosl.org/tomcat/tomcat-$TOMCAT_MAINVERSION/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip 2>&1 | progressfilt
#
# cecho "Unpacking Apache Tomcat." $green
# unzip -q apache-tomcat-$TOMCAT_VER.zip
# mv apache-tomcat-$TOMCAT_VER tomcat

for SOLR in ${SOLR_VER[*]}
do
  cd /opt/solr-tomcat
  cecho "Downloading Apache Solr $SOLR" $green
  wget --progress=bar:force http://archive.apache.org/dist/lucene/solr/$SOLR_VER/apache-solr-$SOLR_VER.zip 2>&1 | progressfilt
  cecho "Unpacking Apache Solr." $green
  unzip -q apache-solr-$SOLR.zip

  cp apache-solr-$SOLR/dist/apache-solr-$SOLR.war ${TOMCAT_BASE}/webapps/solr-$SOLR.war
  cp -r apache-solr-$SOLR/example/solr solr/solr-$SOLR
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

    wgetresource solr/typo3cores/conf/$LANGUAGE/protwords.txt
    wgetresource solr/typo3cores/conf/$LANGUAGE/schema.xml
    wgetresource solr/typo3cores/conf/$LANGUAGE/stopwords.txt
    wgetresource solr/typo3cores/conf/$LANGUAGE/synonyms.txt

    if [ $LANGUAGE = "german" ]
    then
      wgetresource solr/typo3cores/conf/$LANGUAGE/german-common-nouns.txt
    fi
  done

  # download general configuration in /opt/solr-tomcat/solr/typo3cores/conf/
  cecho "Downloading general configruation" $green
  cd ..
  wgetresource solr/typo3cores/conf/admin-extra.html
  wgetresource solr/typo3cores/conf/currency.xml
  wgetresource solr/typo3cores/conf/elevate.xml
  wgetresource solr/typo3cores/conf/general_schema_fields.xml
  wgetresource solr/typo3cores/conf/general_schema_types.xml
  wgetresource solr/typo3cores/conf/solrconfig.xml

  # download core configuration file solr.xml in /opt/solr-tomcat/solr/
  cd ../..
  rm solr.xml

  wgetresource solr/solr.xml

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
  cp -r apache-solr-$SOLR/dist solr/solr-$SOLR
  cp -r apache-solr-$SOLR/contrib solr/solr-$SOLR

  cecho "Cleaning up." $green
  rm -rf /opt/solr-tomcat/apache-solr-$SOLR.zip
  rm -rf /opt/solr-tomcat/apache-solr-$SOLR

  # ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

  cecho "Downloading the Solr TYPO3 plugin for access control. Version: $EXT_SOLR_PLUGIN_VER" $green
  mkdir solr/solr-$SOLR/typo3lib
  cd solr/solr-$SOLR/typo3lib
  wget --progress=bar:force http://www.typo3-solr.com/fileadmin/files/solr/solr-typo3-plugin-$EXT_SOLR_PLUGIN_VER.jar 2>&1 | progressfilt
done

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Configuring Apache Tomcat." $green
# We are using an existing tomcat installation
#
# cd ${TOMCAT_CONFIG_DIR}
#
# rm server.xml
#
# wgetresource tomcat/server.xml

cd ${TOMCAT_CONTEXT_DIR}

# set property solr.home
for SOLR in ${SOLR_VER[*]}
do
  touch solr-$SOLR.xml
  echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>" > solr-$SOLR.xml
  echo "<Context docBase=\"${TOMCAT_BASE}/webapps/solr-$SOLR.war\" debug=\"0\" crossContext=\"true\" >" >> solr-$SOLR.xml
  echo "  <Environment name=\"solr/home\" type=\"java.lang.String\" value=\"/opt/solr-tomcat/solr/solr-$SOLR\" override=\"true\" />" >> solr-$SOLR.xml
  echo "</Context>" >> solr-$SOLR.xml
done

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

# We are using an existing tomcat installation
#
# cecho "Setting permissions." $green
# cd /opt/solr-tomcat/
# chmod a+x tomcat/bin/*

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

# We are using an existing tomcat installation
#
# cecho "Cleaning up." $green
# rm -rf apache-tomcat-$TOMCAT_VER.zip

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Starting Tomcat." $green
service tomcat6 start

cecho "Done." $green
cecho "Tomcat is running and available on port 8080." $green
