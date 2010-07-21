#!/bin/bash

TOMCAT_VER=6.0.26
SOLR_VER=1.4.1
EXT_SOLR_VER=1.2

SVNBRANCH_PATH="branches/$EXT_SOLR_VER"

TOMCAT_MAINVERSION=`echo "$TOMCAT_VER" | cut -d'.' -f1`

cd /opt
mkdir solr-tomcat
cd solr-tomcat/

# Download Tomcat
wget http://www.apache.org/dist/tomcat/tomcat-$TOMCAT_MAINVERSION/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip

# Download Solr
wget http://www.apache.org/dist/lucene/solr/$SOLR_VER/apache-solr-$SOLR_VER.zip

unzip apache-tomcat-$TOMCAT_VER.zip
unzip apache-solr-$SOLR_VER.zip

cp apache-solr-$SOLR_VER/dist/apache-solr-$SOLR_VER.war apache-tomcat-$TOMCAT_VER/webapps/solr.war
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

mkdir apache-tomcat-$TOMCAT_VER/conf/Catalina
mkdir apache-tomcat-$TOMCAT_VER/conf/Catalina/localhost

cd apache-tomcat-$TOMCAT_VER/conf/Catalina/localhost

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

chmod a+x apache-tomcat-$TOMCAT_VER/bin/*
./apache-tomcat-$TOMCAT_VER/bin/startup.sh

echo "Now browse to http://localhost:8080/solr/admin/"

#cleanup solr-tomcat
rm -rf apache-solr-$SOLR_VER.zip
rm -rf apache-solr-$SOLR_VER
rm -rf apache-tomcat-$TOMCAT_VER.zip


#Note that the startup.sh script is run from the directory containing your solr home ./solr
#since the solr webapp looks for $CWD/solr by default.
#You can use JNDI or a System property to configure the solr home directory (described below)
#If you want to run the startup.sh from a different working directory.
