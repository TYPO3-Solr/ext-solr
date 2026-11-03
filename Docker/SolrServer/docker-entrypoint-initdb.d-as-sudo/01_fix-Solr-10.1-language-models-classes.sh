#!/usr/bin/env bash

# Solr 10.1 moved the language-models classes out of the "textvectorisation" sub-package
# and the update processor factories into "update.processor.factory".
# Configsets persisted in volumes from Solr 10.0 still reference the old names and no core can load.

PATH_SOLRCONFIG="${SOLR_HOME}/configsets/ext_solr_14_0_0/conf"
PATH_AND_FILENAME_SOLRCONFIG="${PATH_SOLRCONFIG}/solrconfig.xml"
PATH_AND_FILENAME_BACKUP="${PATH_SOLRCONFIG}/solrconfig.xml.Backup-Solr-10.0"

# Also matches the processor name written by the first, incomplete version of this migration.
OUTDATED_CLASSES_PATTERN='languagemodels\.(textvectorisation\.|update\.processor\.TextToVector)'

if grep -qE "${OUTDATED_CLASSES_PATTERN}" "${PATH_AND_FILENAME_SOLRCONFIG}"; then
  echo "The EXT:solr configset references Apache Solr 10.0 language-models classes"
  echo "  migrating them to the Apache Solr 10.1 package names"

  # Keep the backup of the original Solr 10.0 state on repeated runs.
  if [ ! -f "${PATH_AND_FILENAME_BACKUP}" ]; then
    cp -p "${PATH_AND_FILENAME_SOLRCONFIG}" "${PATH_AND_FILENAME_BACKUP}"
  fi
  sed -i -E \
    -e 's/languagemodels\.(textvectorisation\.)?update\.processor\.TextToVectorUpdateProcessorFactory/languagemodels.update.processor.factory.TextToVectorUpdateProcessorFactory/g' \
    -e 's/languagemodels\.textvectorisation\.search\.TextToVectorQParserPlugin/languagemodels.search.TextToVectorQParserPlugin/g' \
    "${PATH_AND_FILENAME_SOLRCONFIG}"
fi
