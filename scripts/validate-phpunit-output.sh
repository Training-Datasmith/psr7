#!/usr/bin/env bash

validate_phpunit_output() {
  local output_file="$1"
  local allowlist="${2:-}"
  local expected_ok="${3:-}"
  local expected_summary="${4:-}"

  if grep -Eq '^(PHP )?Warning:' "${output_file}"; then
    printf '%s\n' "PHPUnit output contains Warning:"
    grep -E '^(PHP )?Warning:' "${output_file}" || true
    return 1
  fi
  if grep -Eq '^(PHP )?Deprecated:' "${output_file}"; then
    printf '%s\n' "PHPUnit output contains Deprecated:"
    grep -E '^(PHP )?Deprecated:' "${output_file}" || true
    return 1
  fi
  if grep -Eq '^(PHP )?Notice:' "${output_file}"; then
    printf '%s\n' "PHPUnit output contains Notice:"
    grep -E '^(PHP )?Notice:' "${output_file}" || true
    return 1
  fi
  if grep -qE '^FAILURES!|^ERRORS!|^WARNINGS!' "${output_file}"; then
    printf '%s\n' "PHPUnit summary contains defect banner"
    return 1
  fi

  if [[ -n "${expected_ok}" && -f "${expected_ok}" ]]; then
    local want_ok
    want_ok="$(tr -d '\r' < "${expected_ok}" | sed '/^$/d' | tail -n 1)"
    if ! grep -Fxq "${want_ok}" "${output_file}"; then
      printf '%s\n' "PHPUnit output missing expected footer: ${want_ok}"
      return 1
    fi
  elif ! grep -Eq '^OK \([0-9]+ tests?, [0-9]+ assertions?\)$' "${output_file}"; then
    printf '%s\n' 'PHPUnit output is missing strict success line: OK (N tests, M assertions)'
    return 1
  fi

  if [[ -n "${expected_summary}" && -f "${expected_summary}" ]]; then
    local want_summary
    want_summary="$(tr -d '\r' < "${expected_summary}" | sed '/^$/d' | tail -n 1)"
    if ! grep -Fxq "${want_summary}" "${output_file}"; then
      printf '%s\n' "PHPUnit output missing expected summary: ${want_summary}"
      return 1
    fi
  fi

  if grep -qE 'Incomplete:' "${output_file}"; then
    printf '%s\n' "Incomplete tests are not allowed"
    return 1
  fi
  if grep -qE 'Risky:' "${output_file}"; then
    printf '%s\n' "Risky tests are not allowed"
    return 1
  fi

  if grep -qE 'Skipped: [1-9]' "${output_file}"; then
    local allowed=()
    if [[ -n "${allowlist}" && -f "${allowlist}" ]]; then
      while IFS= read -r line || [[ -n "${line}" ]]; do
        line="${line%%#*}"
        line="$(echo "${line}" | xargs)"
        [[ -z "${line}" ]] && continue
        allowed+=("${line}")
      done < "${allowlist}"
    fi
    if [[ ${#allowed[@]} -eq 0 ]]; then
      printf '%s\n' "Skipped tests present but allowlist is empty"
      return 1
    fi
  fi

  return 0
}
