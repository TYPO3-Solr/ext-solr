#!/usr/bin/env bash

rm /etc/init.d/solr*
rm -fR /tmp/solr/*
rm -fR /opt/solr*
rm -fR /var/solr*
rm /etc/default/solr.in.sh
sudo unlink /opt/solr
sudo killall java