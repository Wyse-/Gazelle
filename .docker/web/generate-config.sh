#!/usr/bin/env bash

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

if [ -f ${THIS_DIR}/../../classes/config.php ]; then
    exit 0;
fi

echo "GENERATING GAZELLE CONFIG..."
echo ""
cp ${THIS_DIR}/../../classes/config.template.php ${THIS_DIR}/../../classes/config.php
CONF_FILE=${THIS_DIR}/../../classes/config.php
sed -i -e "6s/''/'Gazelle Dev'/" ${CONF_FILE}
sed -i -e "7s/''/'localhost:8080'/" ${CONF_FILE}
sed -i -e "8s/''/'localhost:8080'/" ${CONF_FILE}

sed -i -e "10s/''/'localhost'/" ${CONF_FILE}
sed -i -e "11s|'https://'.SITE_HOST|'http://localhost:8080'|" ${CONF_FILE}
sed -i -e "13s|/path|/var/www|g" ${CONF_FILE}
sed -i -e "14s|/path|/var/www|g" ${CONF_FILE}
sed -i -e "16s|''|'http://localhost:${TRACKER_PORT}'|" ${CONF_FILE}
sed -i -e "17s|''|'http://localhost:${TRACKER_PORT}'|" ${CONF_FILE}

sed -i -e "38s/localhost/mysql/" ${CONF_FILE}
sed -i -e "39s/''/'${MYSQL_USER}'/" ${CONF_FILE}
sed -i -e "40s/''/'${MYSQL_PASSWORD}'/" ${CONF_FILE}
sed -i -e "41s/''/'${MYSQL_USER}'/" ${CONF_FILE}
sed -i -e "42s/''/'${MYSQL_PASSWORD}'/" ${CONF_FILE}
sed -i -e "50s/.*/    ['host' => 'memcached', 'port' => 11211]/" ${CONF_FILE}

sed -i -e "54s/localhost/sphinxsearch/" ${CONF_FILE}
sed -i -e "s/127.0.0.1/sphinxsearch/" ${CONF_FILE}

sed -i -e "63s/false/false/" ${CONF_FILE}
sed -i -e "64s/localhost/${TRACKER_HOST}/" ${CONF_FILE}
sed -i -e "65s/2710/${TRACKER_PORT}/" ${CONF_FILE}
sed -i -e "66s/''/'${TRACKER_SECRET}'/" ${CONF_FILE}
sed -i -e "67s/''/'${TRACKER_REPORTKEY}'/" ${CONF_FILE}

sed -i -e "83s/false/true/" ${CONF_FILE}

echo ""
