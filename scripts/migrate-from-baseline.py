#!/usr/bin/env python3
"""Regenerate src/ and tests/ from 7d0ed42 with Studly_Snake naming."""
from __future__ import annotations

import re
import subprocess
from pathlib import Path

REV = "7d0ed42"
ROOT = Path(__file__).resolve().parent.parent

CLASS_MAP = {
    "AppendStream": "Append_Stream",
    "BufferStream": "Buffer_Stream",
    "CachingStream": "Caching_Stream",
    "DroppingStream": "Dropping_Stream",
    "FnStream": "Fn_Stream",
    "HttpFactory": "Http_Factory",
    "InflateStream": "Inflate_Stream",
    "LazyOpenStream": "Lazy_Open_Stream",
    "LimitStream": "Limit_Stream",
    "MessageTrait": "Message_Trait",
    "MimeType": "Mime_Type",
    "MultipartStream": "Multipart_Stream",
    "NoSeekStream": "No_Seek_Stream",
    "PumpStream": "Pump_Stream",
    "ServerRequest": "Server_Request",
    "StreamDecoratorTrait": "Stream_Decorator_Trait",
    "StreamWrapper": "Stream_Wrapper",
    "UploadedFile": "Uploaded_File",
    "UriComparator": "Uri_Comparator",
    "UriNormalizer": "Uri_Normalizer",
    "UriResolver": "Uri_Resolver",
    "MalformedUriException": "Malformed_Uri_Exception",
    "HasToString": "Has_To_String",
    "ReadSeekOnlyStream": "Read_Seek_Only_Stream",
}

TEST_CLASS_MAP = {k + "Test": v + "_Test" for k, v in CLASS_MAP.items()}
TEST_CLASS_MAP["ServerRequestFromGlobalsTest"] = "Server_Request_From_Globals_Test"

# Repo-owned methods only (not PSR interface surface).
REPO_METHOD_MAP = {
    "addStream": "add_stream",
    "cacheEntireStream": "cache_entire_stream",
    "splitList": "split_list",
    "setOffset": "set_offset",
    "setLimit": "set_limit",
    "toString": "to_string",
    "bodySummary": "body_summary",
    "rewindBody": "rewind_body",
    "parseMessage": "parse_message",
    "parseRequestUri": "parse_request_uri",
    "parseRequest": "parse_request",
    "parseResponse": "parse_response",
    "setHeaders": "set_headers",
    "normalizeHeaderValue": "normalize_header_value",
    "trimAndValidateHeaderValues": "trim_and_validate_header_values",
    "assertHeader": "assert_header",
    "assertValue": "assert_value",
    "fromFilename": "from_filename",
    "fromExtension": "from_extension",
    "getBoundary": "get_boundary",
    "addElement": "add_element",
    "addNestedElements": "add_nested_elements",
    "createElement": "create_element",
    "getHeader": "get_header",
    "updateHostFromUri": "update_host_from_uri",
    "assertMethod": "assert_method",
    "assertStatusCodeIsInteger": "assert_status_code_is_integer",
    "assertStatusCodeRange": "assert_status_code_range",
    "normalizeFiles": "normalize_files",
    "createUploadedFileFromSpec": "create_uploaded_file_from_spec",
    "normalizeNestedFileSpec": "normalize_nested_file_spec",
    "fromGlobals": "from_globals",
    "extractHostAndPortFromAuthority": "extract_host_and_port_from_authority",
    "getUriFromGlobals": "get_uri_from_globals",
    "setStreamOrFile": "set_stream_or_file",
    "setError": "set_error",
    "isStringNotEmpty": "is_string_not_empty",
    "isOk": "is_ok",
    "isMoved": "is_moved",
    "validateActive": "validate_active",
    "composeComponents": "compose_components",
    "isDefaultPort": "is_default_port",
    "isAbsolute": "is_absolute",
    "isNetworkPathReference": "is_network_path_reference",
    "isAbsolutePathReference": "is_absolute_path_reference",
    "isRelativePathReference": "is_relative_path_reference",
    "isSameDocumentReference": "is_same_document_reference",
    "withoutQueryValue": "without_query_value",
    "withQueryValue": "with_query_value",
    "withQueryValues": "with_query_values",
    "fromParts": "from_parts",
    "applyParts": "apply_parts",
    "filterScheme": "filter_scheme",
    "filterUserInfoComponent": "filter_user_info_component",
    "filterHost": "filter_host",
    "filterPort": "filter_port",
    "getFilteredQueryString": "get_filtered_query_string",
    "generateQueryString": "generate_query_string",
    "removeDefaultPort": "remove_default_port",
    "filterPath": "filter_path",
    "filterQueryAndFragment": "filter_query_and_fragment",
    "rawurlencodeMatchZero": "rawurlencode_match_zero",
    "validateState": "validate_state",
    "isCrossOrigin": "is_cross_origin",
    "computePort": "compute_port",
    "isEquivalent": "is_equivalent",
    "capitalizePercentEncoding": "capitalize_percent_encoding",
    "decodeUnreservedCharacters": "decode_unreserved_characters",
    "removeDotSegments": "remove_dot_segments",
    "getRelativePath": "get_relative_path",
    "caselessRemove": "caseless_remove",
    "copyToStream": "copy_to_stream",
    "copyToString": "copy_to_string",
    "modifyRequest": "modify_request",
    "readLine": "read_line",
    "redactUserInfo": "redact_user_info",
    "streamFor": "stream_for",
    "tryFopen": "try_fopen",
    "tryGetContents": "try_get_contents",
    "uriFor": "uri_for",
    "getResource": "get_resource",
    "createStreamContext": "create_stream_context",
}

PROP_MAP = {
    "remoteStream": "remote_stream",
    "skipReadBytes": "skip_read_bytes",
    "maxLength": "max_length",
    "headerNames": "header_names",
    "tellPos": "tell_pos",
    "requestTarget": "request_target",
    "reasonPhrase": "reason_phrase",
    "statusCode": "status_code",
    "cookieParams": "cookie_params",
    "parsedBody": "parsed_body",
    "queryParams": "query_params",
    "serverParams": "server_params",
    "uploadedFiles": "uploaded_files",
    "customMetadata": "custom_metadata",
    "clientFilename": "client_filename",
    "clientMediaType": "client_media_type",
    "composedComponents": "composed_components",
    "userInfo": "user_info",
    "streamOrFile": "stream_or_file",
    "errorStatus": "error_status",
}


def git_show(path: str) -> str:
    return subprocess.check_output(["git", "show", f"{REV}:{path}"], text=True, cwd=ROOT)


def path_map(old_path: str) -> str:
    name = Path(old_path).name
    stem = Path(old_path).stem
    if stem in CLASS_MAP:
        return str(Path(old_path).parent / (CLASS_MAP[stem] + ".php"))
    if stem.endswith("Test"):
        base = stem[:-4]
        if base in CLASS_MAP:
            return str(Path(old_path).parent / (CLASS_MAP[base] + "_Test.php"))
    specials = {
        "ServerRequestFromGlobalsTest.php": "Server_Request_From_Globals_Test.php",
        "HasToString.php": "Has_To_String.php",
        "ReadSeekOnlyStream.php": "Read_Seek_Only_Stream.php",
    }
    if name in specials:
        return str(Path(old_path).parent / specials[name])
    return old_path


def rename_repo_methods(text: str, *, defs: bool) -> str:
    for old, new in sorted(REPO_METHOD_MAP.items(), key=lambda x: -len(x[0])):
        if defs:
            text = re.sub(rf"\bfunction\s+{re.escape(old)}\s*\(", f"function {new}(", text)
            text = re.sub(rf"\bfunction\s+&\s*{re.escape(old)}\s*\(", f"function &{new}(", text)
        text = re.sub(rf"::{re.escape(old)}\s*\(", f"::{new}(", text)
        text = re.sub(rf"->{re.escape(old)}\s*\(", f"->{new}(", text)
        text = re.sub(rf"\$this->{re.escape(old)}\s*\(", f"$this->{new}(", text)
        text = re.sub(rf"parent::{re.escape(old)}\s*\(", f"parent::{new}(", text)
    return text


def rename_props(text: str) -> str:
    for old, new in sorted(PROP_MAP.items(), key=lambda x: -len(x[0])):
        text = re.sub(rf"\${re.escape(old)}\b", f"${new}", text)
        text = re.sub(rf"->{re.escape(old)}\b", f"->{new}", text)
        text = re.sub(rf"\bthis\.{re.escape(old)}\b", f"this.{new}", text)
        text = re.sub(rf"(\W){re.escape(old)}(\s*=)", rf"\1{new}\2", text)
    return text


def apply_class_map(text: str) -> str:
    for old, new in sorted(CLASS_MAP.items(), key=lambda x: -len(x[0])):
        text = text.replace(old, new)
    for old, new in sorted(TEST_CLASS_MAP.items(), key=lambda x: -len(x[0])):
        text = text.replace(old, new)
    return text


def transform(content: str, *, is_test: bool) -> str:
    content = content.replace("namespace GuzzleHttp\\Psr7;", "namespace Guzzle_Http\\Psr7;")
    content = content.replace("namespace GuzzleHttp\\Tests\\Psr7;", "namespace Guzzle_Http\\Tests\\Psr7;")
    content = content.replace("namespace GuzzleHttp\\Tests\\Psr7\\Integration;", "namespace Guzzle_Http\\Tests\\Psr7\\Integration;")
    content = content.replace("use GuzzleHttp\\Psr7\\", "use Guzzle_Http\\Psr7\\")
    content = content.replace("use GuzzleHttp\\Tests\\Psr7\\", "use Guzzle_Http\\Tests\\Psr7\\")
    content = content.replace("\\GuzzleHttp\\Psr7\\", "\\Guzzle_Http\\Psr7\\")
    content = content.replace("GuzzleHttp\\Psr7\\", "Guzzle_Http\\Psr7\\")
    content = content.replace("GuzzleHttp\\Tests\\Psr7\\", "Guzzle_Http\\Tests\\Psr7\\")
    content = apply_class_map(content)
    content = rename_props(content)
    content = rename_repo_methods(content, defs=not is_test)
    content = content.replace("FnStream should never be unserialized", "Fn_Stream should never be unserialized")
    content = content.replace("is not implemented in the FnStream", "is not implemented in the Fn_Stream")
    return content


def main() -> None:
    old_src = subprocess.check_output(
        ["git", "ls-tree", "-r", "--name-only", REV, "src"], text=True, cwd=ROOT
    ).splitlines()
    old_tests = subprocess.check_output(
        ["git", "ls-tree", "-r", "--name-only", REV, "tests"], text=True, cwd=ROOT
    ).splitlines()

    for old in old_src:
        if not old.endswith(".php"):
            continue
        new = path_map(old)
        content = transform(git_show(old), is_test=False)
        out = ROOT / new
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(content)

    for old in old_tests:
        if not old.endswith(".php"):
            continue
        new = path_map(old)
        content = transform(git_show(old), is_test=True)
        out = ROOT / new
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(content)

    (ROOT / "tests/Integration/server.php").write_text(
        """<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

$request = Guzzle_Http\\Psr7\\Server_Request::from_globals();

$output = [
    'method' => $request->getMethod(),
    'uri' => $request->getUri()->__toString(),
    'body' => $request->getBody()->__toString(),
];

echo json_encode($output);
"""
    )
    print("Migrated", len(old_src), "src and", len(old_tests), "test files.")


if __name__ == "__main__":
    main()
