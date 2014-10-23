#!/bin/sh

set -ev
# Create a new CouchDB database.
curl -X PUT localhost:5984/test_db
# Enable Simpletest.
drush en --yes simpletest
drush cr
curl -v -H "Accept:application/json" -H "Content-Type:application/json" -X POST -d '{"source":"http://user:pass@localhost/relaxed/default","target":"localhost:5984/test_db"}' http://localhost:5984/_replicate | tee /tmp/test_couchdb.txt
sudo cat /var/log/apache2/access.log
export TEST_EXIT=${PIPESTATUS[0]}
# Analyze the output to ascertain whether the tests passed.
TEST_COUCHDB=$(! egrep -i "(error)" /tmp/test_couchdb.txt > /dev/null)$?
if [ $TEST_EXIT -eq 0 ] && [ $TEST_COUCHDB -eq 0 ]; then exit 0; else exit 1; fi