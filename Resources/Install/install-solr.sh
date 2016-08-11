#!/usr/bin/env bash

SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
EXTENSION_ROOTPATH="$SCRIPTPATH/../../"

clear

SOLR_VERSION=6.1.0
EXT_SOLR_VERSION=6.0
JAVA_VERSION=8


APACHE_MIRROR="http://mirror.dkd.de/apache/"
APACHE_ARCHIVE="http://archive.apache.org/dist/"

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

# check whether a given resource is available on a mirror
# if the resource is found it will download from the mirror
# it the resource is not found it will download from Apache archive
apachedownload ()
{
	# test mirror
	wget -q --spider "$APACHE_MIRROR$1"

	if [ $? -eq "0" ]
	then
		# download from mirror
		wget --progress=bar:force "$APACHE_MIRROR$1" 2>&1 | progressfilt
	else
		# download from archive
		wget --progress=bar:force "$APACHE_ARCHIVE$1" 2>&1 | progressfilt
	fi
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

# Make sure only root can run this script
if [[ $EUID -ne 0 ]]
then
	cecho "This script must be run as root." $red
	exit 1
fi

wget --version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR couldn't find wget." $red
	PASSALLCHECKS=0
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

ping -c 1 mirror.dkd.de > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0" ]
then
	cecho "ERROR couldn't ping Apache download mirror, try again using wget" $yellow
	wget -q --spider http://mirror.dkd.de/apache/
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


if [ $PASSALLCHECKS -eq "0" ]
then
	cecho "Please install all missing requirements or fix any other errors listed above and try again." $red
	exit 1
else
	cecho "All requirements met, starting to install Solr." $green
fi

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cd /tmp/

cecho "Downloading Apache Solr $SOLR_VERSION" $green

if [ ! -f solr-$SOLR_VERSION.zip ]; then
    apachedownload lucene/solr/$SOLR_VERSION/solr-$SOLR_VERSION.zip
fi

mkdir -p /tmp/solr/
cd /tmp/solr/

cp /tmp/solr-$SOLR_VERSION.zip .
unzip -q solr-$SOLR_VERSION.zip

./solr-$SOLR_VERSION/bin/install_solr_service.sh solr-$SOLR_VERSION.zip -p 8080

# copy the schema & config to the installed solr server
rm -fR /opt/solr/server/solr/*
mkdir -p /opt/solr/server/solr/
cp -r ${EXTENSION_ROOTPATH}/Resources/Solr/* /opt/solr/server/solr/

# todo set the memory limit before from outside
/opt/solr/bin/solr start -p 8080 -m 256m

