# Board GitHub Plugin

A GitHub Power-Up for [Board](https://board.test). Adds read-only board lists
fed by a repository's **recent commits**, **open pull requests** or **open
issues**.

## Install

```
composer require board/plugin-github
```

Laravel auto-discovers the provider, which registers the plugin into Board's
`PluginRegistry`. Then, in a board: **Power-Ups → Install GitHub → Connect**
(OAuth), and create a list from the plugin.

## Requirements

- `board/plugin-sdk` (contracts)
- The host app must expose a GitHub OAuth app (`GITHUB_CLIENT_ID` /
  `GITHUB_CLIENT_SECRET`) — the token is stored, encrypted, by the host.

## Versioning

Semantic versioning. Pins the SDK with `"board/plugin-sdk": "^0.1"`.
