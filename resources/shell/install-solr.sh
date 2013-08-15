#!/usr/bin/env bash

# Usage:
#	sudo ./install-solr.sh
#	sudo ./install-solr.sh english german french

clear

TOMCAT_VER=6.0.37
SOLR_VER=3.6.2
EXT_SOLR_VER=2.8
EXT_SOLR_PLUGIN_VER=1.2.0

GITBRANCH_PATH="solr_$EXT_SOLR_VER.x"

# Set default language for cores to download to english, if no commandline parameters are given
if [ $# -eq 0 ]
then
	LANGUAGES=english
else
	LANGUAGES=$@
fi

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

mkdir -p /opt/solr-tomcat
cd /opt/solr-tomcat/

cecho "Using the mirror at Oregon State University Open Source Lab - OSUOSL." $green
cecho "Downloading Apache Tomcat $TOMCAT_VER" $green
TOMCAT_MAINVERSION=`echo "$TOMCAT_VER" | cut -d'.' -f1`
wget --progress=bar:force http://apache.osuosl.org/tomcat/tomcat-$TOMCAT_MAINVERSION/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip 2>&1 | progressfilt

cecho "Downloading Apache Solr $SOLR_VER" $green
wget --progress=bar:force http://archive.apache.org/dist/lucene/solr/$SOLR_VER/apache-solr-$SOLR_VER.zip 2>&1 | progressfilt

cecho "Unpacking Apache Tomcat." $green
unzip -q apache-tomcat-$TOMCAT_VER.zip

cecho "Unpacking Apache Solr." $green
unzip -q apache-solr-$SOLR_VER.zip

mv apache-tomcat-$TOMCAT_VER tomcat

cp apache-solr-$SOLR_VER/dist/apache-solr-$SOLR_VER.war tomcat/webapps/solr.war
cp -r apache-solr-$SOLR_VER/example/solr .

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Downloading TYPO3 Solr configuration files." $green
cd solr
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

# clean up
rm -rf bin
rm -rf conf
rm -rf data
rm README.txt

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Configuring Apache Tomcat." $green
cd /opt/solr-tomcat/tomcat/conf

rm server.xml

wgetresource tomcat/server.xml

cd /opt/solr-tomcat/
mkdir -p tomcat/conf/Catalina/localhost
cd tomcat/conf/Catalina/localhost

# install context descriptor for the solr context/webapp, sets the solr.home property
wgetresource tomcat/solr.xml

# copy libs
cd /opt/solr-tomcat/
cp -r apache-solr-$SOLR_VER/dist solr/
cp -r apache-solr-$SOLR_VER/contrib solr/

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Downloading the Solr TYPO3 plugin for access control. Version: $EXT_SOLR_PLUGIN_VER" $green
mkdir solr/typo3lib
cd solr/typo3lib
wget --progress=bar:force http://www.typo3-solr.com/fileadmin/files/solr/solr-typo3-plugin-$EXT_SOLR_PLUGIN_VER.jar 2>&1 | progressfilt

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Setting permissions." $green
cd /opt/solr-tomcat/
chmod a+x tomcat/bin/*

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Cleaning up." $green
rm -rf apache-solr-$SOLR_VER.zip
rm -rf apache-solr-$SOLR_VER
rm -rf apache-tomcat-$TOMCAT_VER.zip

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Starting Tomcat." $green
./tomcat/bin/startup.sh

cecho "Done." $green
cecho "Now browse to http://localhost:8080/solr/" $green
