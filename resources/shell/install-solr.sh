#!/bin/bash

TOMCAT_VER=6.0.32
SOLR_VER=3.3.0
EXT_SOLR_VER=1.4
EXT_SOLR_PLUGIN_VER=1.2.0

SVNBRANCH_PATH="branches/solr_$EXT_SOLR_VER.x"

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

echo "Checking requirements."

PASSALLCHECKS=1

java -version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	echo "ERROR couldn't find Java (Oracle Java is recommended)."
	PASSALLCHECKS=0
fi

wget --version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	echo "ERROR couldn't find wget."
	PASSALLCHECKS=0
fi

unzip -v > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	echo "ERROR: couldn't find unzip."
	PASSALLCHECKS=0
fi

if [ $PASSALLCHECKS -eq "0"  ]
then
	echo "Please install all missing requirements listed above and try again."
	exit 1
else
	echo "All requirements met, starting to install Solr."
fi

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

cd /opt
mkdir solr-tomcat
cd solr-tomcat/

echo "Using the mirror at Oregon State University Open Source Lab - OSUOSL."
echo "Downloading Apache Tomcat."
TOMCAT_MAINVERSION=`echo "$TOMCAT_VER" | cut -d'.' -f1`
wget http://apache.osuosl.org/tomcat/tomcat-$TOMCAT_MAINVERSION/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip

echo "Downloading Apache Solr."
wget http://apache.osuosl.org/lucene/solr/$SOLR_VER/apache-solr-$SOLR_VER.zip

unzip apache-tomcat-$TOMCAT_VER.zip
unzip apache-solr-$SOLR_VER.zip

mv apache-tomcat-$TOMCAT_VER tomcat

cp apache-solr-$SOLR_VER/dist/apache-solr-$SOLR_VER.war tomcat/webapps/solr.war
cp -r apache-solr-$SOLR_VER/example/solr .

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

echo "Downloading TYPO3 Solr configuration files."
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
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/protwords.txt
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/schema.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/stopwords.txt
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/english/synonyms.txt
else
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/protwords.txt
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/schema.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/stopwords.txt
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/english/synonyms.txt
fi

# download general configuration in /opt/solr-tomcat/solr/typo3cores/conf/
cd ..
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/admin-extra.html
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/elevate.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/general_schema_fields.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/general_schema_types.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/mapping-ISOLatin1Accent.txt
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3cores/conf/solrconfig.xml
else
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/admin-extra.html
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/elevate.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/general_schema_fields.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/general_schema_types.xml
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/mapping-ISOLatin1Accent.txt
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3cores/conf/solrconfig.xml
fi

# download core configuration file solr.xml in /opt/solr-tomcat/solr/
cd ../..
rm solr.xml
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/solr.xml
else
wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/solr.xml
fi

# clean up
rm -rf bin
rm -rf conf
rm -rf data
rm README.txt

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

echo "Configuring Apache Tomcat."
cd /opt/solr-tomcat/tomcat/conf

rm server.xml

if [ $BRANCH_TEST_RETURN -eq "0" ]
then
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/tomcat/server.xml
else
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/tomcat/server.xml
fi

cd /opt/solr-tomcat/
mkdir -p tomcat/conf/Catalina/localhost
cd tomcat/conf/Catalina/localhost

# set property solr.home
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/tomcat/solr.xml
else
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/tomcat/solr.xml
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

echo "Downloading the Solr TYPO3 plugin for access control."
mkdir solr/typo3lib
cd solr/typo3lib
wget http://www.typo3-solr.com/fileadmin/files/solr/solr-typo3-plugin-$EXT_SOLR_PLUGIN_VER.jar

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

echo "Setting permissions."
cd /opt/solr-tomcat/
chmod a+x tomcat/bin/*

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

echo "Cleaning up."
rm -rf apache-solr-$SOLR_VER.zip
rm -rf apache-solr-$SOLR_VER
rm -rf apache-tomcat-$TOMCAT_VER.zip

# ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----

echo "Starting Tomcat."
./tomcat/bin/startup.sh

echo "Done."
echo "Now browse to http://localhost:8080/solr/"
