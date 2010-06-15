#!/bin/bash

TOMCAT_VER=6.0.26
SOLR_VER=1.4.0

cd /opt
mkdir solr-tomcat
cd solr-tomcat/

# Download Tomcat
wget http://www.apache.org/dist/tomcat/tomcat-6/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip
# Download Solr
wget http://www.apache.org/dist/lucene/solr/$SOLR_VER/apache-solr-$SOLR_VER.zip

unzip apache-tomcat-$TOMCAT_VER.zip
unzip apache-solr-$SOLR_VER.zip

cp apache-solr-$SOLR_VER/dist/apache-solr-$SOLR_VER.war apache-tomcat-$TOMCAT_VER/webapps/solr.war
cp -r apache-solr-$SOLR_VER/example/solr .

chmod a+x apache-tomcat-$TOMCAT_VER/bin/*
./apache-tomcat-$TOMCAT_VER/bin/startup.sh

echo "Now browse to http://localhost:8080/solr/admin/"

#Note that the startup.sh script is run from the directory containing your solr home ./solr
#since the solr webapp looks for $CWD/solr by default.
#You can use JNDI or a System property to configure the solr home directory (described below)
#If you want to run the startup.sh from a different working directory.
