#!/bin/bash

clear

TOMCAT_VER=6.0.32
SOLR_VER=3.4.0
EXT_SOLR_VER=2.0
EXT_SOLR_PLUGIN_VER=1.2.0

SVNBRANCH_PATH="branches/solr_$EXT_SOLR_VER.x"


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

java -version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	cecho "ERROR couldn't find Java (Oracle Java is recommended)." $red
	PASSALLCHECKS=0
fi

wget --version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	cecho "ERROR couldn't find wget." $red
	PASSALLCHECKS=0
fi

unzip -v > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	cecho "ERROR: couldn't find unzip." $red
	PASSALLCHECKS=0
fi

if [ $PASSALLCHECKS -eq "0"  ]
then
	cecho "Please install all missing requirements listed above and try again." $red
	exit 1
else
	cecho "All requirements met, starting to install Solr." $green
fi

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cd /opt
mkdir solr-tomcat
cd solr-tomcat/

cecho "Using the mirror at Oregon State University Open Source Lab - OSUOSL." $green
cecho "Downloading Apache Tomcat $TOMCAT_VER" $green
TOMCAT_MAINVERSION=`echo "$TOMCAT_VER" | cut -d'.' -f1`
wget --progress=bar:force http://apache.osuosl.org/tomcat/tomcat-$TOMCAT_MAINVERSION/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip 2>&1 | progressfilt

cecho "Downloading Apache Solr $SOLR_VER" $green
wget --progress=bar:force http://apache.osuosl.org/lucene/solr/$SOLR_VER/apache-solr-$SOLR_VER.zip 2>&1 | progressfilt

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

# create / download english core configuration
mkdir -p typo3cores/conf/english
cd typo3cores/conf/english

# test if release branch exists, if so we'll download from there
wget --no-check-certificate -q -O /dev/null https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH
BRANCH_TEST_RETURN=$?

# download english configuration in /opt/solr-tomcat/solr/typo3cores/conf/english/
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/protwords.txt 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/schema.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/stopwords.txt 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/synonyms.txt 2>&1 | progressfilt
else
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/protwords.txt 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/schema.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/stopwords.txt 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/synonyms.txt 2>&1 | progressfilt
fi

# download general configuration in /opt/solr-tomcat/solr/typo3cores/conf/
cd ..
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/admin-extra.html 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/elevate.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/general_schema_fields.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/general_schema_types.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/mapping-ISOLatin1Accent.txt 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/solrconfig.xml 2>&1 | progressfilt
else
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/admin-extra.html 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/elevate.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/general_schema_fields.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/general_schema_types.xml 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/mapping-ISOLatin1Accent.txt 2>&1 | progressfilt
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/solrconfig.xml 2>&1 | progressfilt
fi

# download core configuration file solr.xml in /opt/solr-tomcat/solr/
cd ../..
rm solr.xml
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/solr.xml 2>&1 | progressfilt
else
wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/solr.xml 2>&1 | progressfilt
fi

# clean up
rm -rf bin
rm -rf conf
rm -rf data
rm README.txt

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cecho "Configuring Apache Tomcat." $green
cd /opt/solr-tomcat/tomcat/conf

rm server.xml

if [ $BRANCH_TEST_RETURN -eq "0" ]
then
	wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/tomcat/server.xml 2>&1 | progressfilt
else
	wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/tomcat/server.xml 2>&1 | progressfilt
fi

cd /opt/solr-tomcat/
mkdir -p tomcat/conf/Catalina/localhost
cd tomcat/conf/Catalina/localhost

# set property solr.home
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
	wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/tomcat/solr.xml 2>&1 | progressfilt
else
	wget --progress=bar:force --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/tomcat/solr.xml 2>&1 | progressfilt
fi

# copy libs
cd /opt/solr-tomcat/
mkdir solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-analysis-extras-$SOLR_VER.jar solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-cell-$SOLR_VER.jar solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-clustering-$SOLR_VER.jar solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-dataimporthandler-$SOLR_VER.jar solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-dataimporthandler-extras-$SOLR_VER.jar solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-uima-$SOLR_VER.jar solr/dist
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
