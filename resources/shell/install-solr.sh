#!/bin/bash

TOMCAT_VER=6.0.29
SOLR_VER=1.4.1
EXT_SOLR_VER=1.2
SOLR_T3AFP_VER=1.0.0

SVNBRANCH_PATH="branches/$EXT_SOLR_VER"

TOMCAT_MAINVERSION=`echo "$TOMCAT_VER" | cut -d'.' -f1`

#test if required tools java unzip and wget are installed

echo "check that all requirements are installed"

PASSALLCHECKS=1

java -version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	echo "ERROR couldn't find java (sun java is recommended)"
	PASSALLCHECKS=0
fi

wget --version > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	echo "ERROR couldn't find wget"
	PASSALLCHECKS=0
fi

unzip -v > /dev/null 2>&1
CHECK=$?
if [ $CHECK -ne "0"  ]
then
	echo "ERROR: couldn't find unzip"
	PASSALLCHECKS=0
fi

if [ $PASSALLCHECKS -eq "0"  ]
then
	echo "please install all missing packages and try again"
	exit 1
else
	echo "all requirements are installed, start to install solr"
fi

cd /opt
mkdir solr-tomcat
cd solr-tomcat/

# Download Tomcat
wget http://www.apache.org/dist/tomcat/tomcat-$TOMCAT_MAINVERSION/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip

# Download Solr
wget http://www.apache.org/dist/lucene/solr/$SOLR_VER/apache-solr-$SOLR_VER.zip

unzip apache-tomcat-$TOMCAT_VER.zip
unzip apache-solr-$SOLR_VER.zip

#rename dirctory of tomcat to resolve problems when update the tomcat
mv apache-tomcat-$TOMCAT_VER apache-tomcat$TOMCAT_MAINVERSION

cp apache-solr-$SOLR_VER/dist/apache-solr-$SOLR_VER.war apache-tomcat$TOMCAT_MAINVERSION/webapps/solr.war
cp -r apache-solr-$SOLR_VER/example/solr .

#Download the TYOP3 Solrconfig

cd solr/conf
rm schema.xml
rm solrconfig.xml
rm protwords.txt

#test if branch exist
wget --no-check-certificate -q -O /dev/null https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH
BRANCH_TEST_RETURN=$?

if [ $BRANCH_TEST_RETURN -eq "0" ]
then
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/singlecore/schema.xml
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/singlecore/solrconfig.xml
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/singlecore/protwords.txt
else
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/singlecore/schema.xml
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/singlecore/solrconfig.xml
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/singlecore/protwords.txt
fi

#Set the solr.home property

cd /opt/solr-tomcat/

mkdir apache-tomcat$TOMCAT_MAINVERSION/conf/Catalina
mkdir apache-tomcat$TOMCAT_MAINVERSION/conf/Catalina/localhost

cd apache-tomcat$TOMCAT_MAINVERSION/conf/Catalina/localhost

if [ $BRANCH_TEST_RETURN -eq "0" ]
then
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/tomcat/solr.xml
else
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/tomcat/solr.xml
fi
cd /opt/solr-tomcat/


#copy libs

mkdir solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-cell-$SOLR_VER.jar solr/dist
cp apache-solr-$SOLR_VER/dist/apache-solr-clustering-$SOLR_VER.jar solr/dist
cp -r apache-solr-$SOLR_VER/contrib solr/

# copy accessfilterplugin
mkdir solr/accessFilterPlugin
cd solr/accessFilterPlugin
if [ $BRANCH_TEST_RETURN -eq "0" ]
then
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/$SVNBRANCH_PATH/resources/solr/typo3-accessfilter-$SOLR_T3AFP_VER.jar
else
	wget --no-check-certificate https://svn.typo3.org/TYPO3v4/Extensions/solr/trunk/resources/solr/typo3-accessfilter-$SOLR_T3AFP_VER.jar
fi

cd /opt/solr-tomcat/

chmod a+x apache-tomcat$TOMCAT_MAINVERSION/bin/*
./apache-tomcat$TOMCAT_MAINVERSION/bin/startup.sh

echo "Now browse to http://localhost:8080/solr/admin/"

#cleanup solr-tomcat
rm -rf apache-solr-$SOLR_VER.zip
rm -rf apache-solr-$SOLR_VER
rm -rf apache-tomcat-$TOMCAT_VER.zip


#Note that the startup.sh script is run from the directory containing your solr home ./solr
#since the solr webapp looks for $CWD/solr by default.
#You can use JNDI or a System property to configure the solr home directory (described below)
#If you want to run the startup.sh from a different working directory.
