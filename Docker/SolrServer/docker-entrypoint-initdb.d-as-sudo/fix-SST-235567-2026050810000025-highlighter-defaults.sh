#!/usr/bin/env bash

PATH_SOLRCONFIG="${SOLR_HOME}/configsets/ext_solr_11_6_0_elts/conf"
PATH_AND_FILENAME_SOLRCONFIG="${PATH_SOLRCONFIG}/solrconfig.xml"
PATH_AND_FILENAME_BACKUP="${PATH_SOLRCONFIG}/solrconfig.xml.Backup-SST-235567"

PATCH_MARKER='CVE SST 235567 2026050810000025'
PATCH_COMMENT="<!-- ${PATCH_MARKER}: Unified Highlighter set, closes FieldExistsQuery HTTP 500 oracle -->"

# SST-235567 / 2026050810000025 — align /select and /browse highlighter
# defaults with the EXT:solr Layer-3 fix (Unified Highlighter). The FastVector
# Highlighter rewrote `field:*` queries to FieldExistsQuery and crashed with
# HTTP 500, turning the response into a field-existence oracle on the
# indexed schema.
#
# Idempotency: skip migration if either the patch marker is already present
# (script ran before) or `hl.method=original` is no longer present (fresh
# install from a patched configset, or manual edit).

if ! grep -q "${PATCH_MARKER}" "${PATH_AND_FILENAME_SOLRCONFIG}" \
	&& grep -q '<str name="hl.method">original</str>' "${PATH_AND_FILENAME_SOLRCONFIG}"; then
	echo "The Apache Solr instance is affected on SST-235567"
	echo "  migrating /select and /browse handler highlighter defaults to Unified Highlighter"

	cp "${PATH_AND_FILENAME_SOLRCONFIG}" "${PATH_AND_FILENAME_BACKUP}"

	# /select: replace hl.method=original with the Unified Highlighter set
	# preceded by the patch marker comment.
	# shellcheck disable=SC1004
	sed -i "/<str name=\"hl.method\">original<\/str>/c\\
			${PATCH_COMMENT}\\
			<str name=\"hl.method\">unified</str>\\
			<str name=\"hl.offsetSource\">ANALYSIS</str>\\
			<str name=\"hl.bs.type\">WORD</str>\\
			<str name=\"hl.fragsizeIsMinimum\">false</str>" "${PATH_AND_FILENAME_SOLRCONFIG}"

	# /browse: append the patch marker comment + Unified Highlighter set
	# after hl.simple.post.
	# shellcheck disable=SC1004
	sed -i "/<str name=\"hl.simple.post\">&lt;\/b&gt;<\/str>/a\\
			${PATCH_COMMENT}\\
			<str name=\"hl.method\">unified</str>\\
			<str name=\"hl.offsetSource\">ANALYSIS</str>\\
			<str name=\"hl.bs.type\">WORD</str>\\
			<str name=\"hl.fragsizeIsMinimum\">false</str>" "${PATH_AND_FILENAME_SOLRCONFIG}"
fi

# The Unified Highlighter does not support hl.alternateField: replace the
# leading-text fallback with its hl.defaultSummary equivalent.
# Runs independently of the block above so that cores migrated by an earlier
# version of this script are aligned as well. All checks and edits are scoped
# to the /select request handler to leave customized handlers untouched.
PATH_AND_FILENAME_BACKUP_SUMMARY="${PATH_SOLRCONFIG}/solrconfig.xml.Backup-unified-defaultSummary"
SELECT_HANDLER_RANGE='/<requestHandler name="\/select"/,/<\/requestHandler>/'

if sed -n "${SELECT_HANDLER_RANGE}p" "${PATH_AND_FILENAME_SOLRCONFIG}" | grep -q '<str name="f.content.hl.alternateField">content</str>' \
	&& ! sed -n "${SELECT_HANDLER_RANGE}p" "${PATH_AND_FILENAME_SOLRCONFIG}" | grep -q 'f.content.hl.defaultSummary'; then
	echo "  replacing f.content.hl.alternateField by f.content.hl.defaultSummary in the /select handler (alternateField is ignored by the Unified Highlighter)"

	cp "${PATH_AND_FILENAME_SOLRCONFIG}" "${PATH_AND_FILENAME_BACKUP_SUMMARY}"

	sed -i "${SELECT_HANDLER_RANGE} s|<str name=\"f.content.hl.alternateField\">content</str>|<str name=\"f.content.hl.defaultSummary\">true</str>|" "${PATH_AND_FILENAME_SOLRCONFIG}"
	sed -i "${SELECT_HANDLER_RANGE} {/<str name=\"f.content.hl.maxAlternateFieldLength\">/d}" "${PATH_AND_FILENAME_SOLRCONFIG}"
fi
