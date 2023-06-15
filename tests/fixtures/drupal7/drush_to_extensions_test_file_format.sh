#!/usr/bin/env sh

set -e

jq_filter='to_entries | [.[] | {
  name: .key,
  humanName: (.value.name | capture("(?<humanName>.+)(?<machineSuffix> \\(.*\\))") | .humanName),
  type: (if .value.type == "Module" then "module" else "theme" end),
  enabled: (.value.status == "Enabled"),
  version: .value.version,
}] | sort_by(.name)'

main () {
  which jq >/dev/null || jq_not_installed=true
  if test $jq_not_installed; then
    echo "You must have jq installed and available in your \$PATH. See https://stedolan.github.io/jq/."
  fi
  jq "$jq_filter" < /dev/stdin
}

main $@
