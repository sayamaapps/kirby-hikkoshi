# Hikkoshi: privacy notes

This is an English summary of [DATENSCHUTZ.md](DATENSCHUTZ.md), the German text
block for privacy policies, which is the authoritative version. Both describe
the implementation. They are not legal advice. Each operator has to determine
the legal basis and the wording for their own site.

## What the visitor's browser does

Nothing towards any third party. The plugin sets no cookies, uses no
`localStorage`, `sessionStorage` or IndexedDB, and loads no script, font, image
or embed from another server. A redirect is a `Location` header; the plugin's
own 410 page is static text with no script. A site that replaces that page with
its own snippet is responsible for what the snippet contains.

The plugin records nothing about the visitor. It does not store the visitor's
IP address, user agent or referrer.

## What the server does for each request

For a request with the method `GET` or `HEAD` the plugin reads the request path
and the query string, and checks them against the operator's redirect list
(`site/config/hikkoshi.php`). If an entry matches and its target page is
published, the server answers with a redirect (`Location` header and a status
of 301, 302, 307 or 308). If the entry is removed for good (`410`), the server
answers with the 410 page. Otherwise the request goes on as it would without
the plugin.

Nothing from this is kept: the plugin writes no database, no file and no log
line for a request. Each request is decided on its own.

## What is sent to third parties

Nothing. The plugin makes no outbound request, not even to check a target.
External redirect targets are only used as the address of the `Location`
header, and only when the operator has listed their host in
`hikkoshi.allowedHosts`. The browser then contacts that host itself, as it would
for any link.

## What is stored, and where

The plugin stores nothing itself. The input is the redirect list that the
operator writes in the site's config files: old addresses and new page ids, with
their status codes. These are addresses of the website, not of people.

Configuration problems (for example an invalid status or a list error) are
written to the server's error log as a short message. The message contains the
configuration entry and no visitor data.

## What the operator should check

- Only list addresses of the website in the redirect list. Do not put personal
  data, such as an address with a name in it, into the list.
- `allowedHosts` is the operator's decision: only the hosts listed there can be
  targets of a redirect. Keep the list short.
- The web server's access logs are outside the plugin. They record the
  requests, including the old addresses and their query strings, and are
  covered by the site's own privacy policy and retention settings.
- The site test (`hikkoshiCheck()`) makes internal requests during the
  operator's tests only. It is not part of the live site.
