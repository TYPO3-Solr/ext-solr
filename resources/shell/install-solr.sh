#!/bin/bash
cd /opt
mkdir solr-tomcat
cd solr-tomcat/
TOMCAT_VER=6.0.20
wget http://mirrors.ibiblio.org/pub/mirrors/apache/tomcat/tomcat-6/v$TOMCAT_VER/bin/apache-tomcat-$TOMCAT_VER.zip
#find the latest nightly solr build
NIGHTLY=`wget -O - http://people.apache.org/builds/lucene/solr/nightly/ | grep '.zip' | sed 's/.*\(solr-20..-..-..\.zip\).*/\1/' | tail -1`
wget http://people.apache.org/builds/lucene/solr/nightly/$NIGHTLY
unzip apache-tomcat-$TOMCAT_VER.zip
unzip $NIGHTLY
cp apache-solr-nightly/dist/apache-solr-nightly.war apache-tomcat-$TOMCAT_VER/webapps/solr.war
cp -r apache-solr-nightly/example/solr .
chmod a+x apache-tomcat-$TOMCAT_VER/bin/*
./apache-tomcat-$TOMCAT_VER/bin/startup.sh
echo "Now browse to http://localhost:8080/solr/admin/"
#Note that the startup.sh script is run from the directory containing your solr home ./solr
#since the solr webapp looks for $CWD/solr by default.
#You can use JNDI or a System property to configure the solr home directory (described below)
#If you want to run the startup.sh from a different working directory.
