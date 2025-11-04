Tiny Tiny RSS (tt-rss)
======================

Tiny Tiny RSS (tt-rss) is a free, flexible, open-source, web-based news feed (RSS/Atom/other) reader and aggregator.

## Getting started

Please refer to [the wiki](https://github.com/tt-rss/tt-rss/wiki).

## Some notes about this project

* The original tt-rss project, hosted at https://tt-rss.org/ and its various subdomains, [will be gone after 2025-11-01](https://community.tt-rss.org/t/the-end-of-tt-rss-org/7164).
  * Massive thanks to fox for creating tt-rss, and maintaining it (and absolutely everything else that went along with it) for so many years.
* This project (https://github.com/tt-rss/tt-rss) is a fork of tt-rss as of 2025-10-03, created by one of its long-time contributors (`wn_`/`wn_name` on `tt-rss.org`, `supahgreg` on `github.com`).
  * The goal is (as you might expect) to continue tt-rss development.
  * No major breaking changes are planned.
  * Like the original project:
    * The minimum PHP version supported by tt-rss will match what's in Debian's current [`stable` release](https://www.debian.org/releases/).
    * What's on the `main` branch (or `latest` and newest `sha-*` tag for the Docker images) is intended to be stable
      and safe for use.  Like all software, however, bugs sometimes slip through; the goal is to address those bugs promptly.
    * Using the latest code/image is strongly encouraged, and may be a prerequisite to getting support in certain situations.
  * Developer note: Due to use of `invalid@email.com` on `supahgreg`'s pre-2025-10-03 commits (which were done on `tt-rss.org`) GitHub incorrectly shows `ivanivanov884`
    (the GitHub user associated with that email address) as the author instead of `wn_`/`supahgreg`.  Apologies for any confusion.  `¯\_(ツ)_/¯`
* Plugins that were under https://gitlab.tt-rss.org/tt-rss/plugins have been mirrored to `https://github.com/tt-rss/tt-rss-plugin-*`.
  * Plugin repository names have changed to get a consistent `tt-rss-plugin-*` prefix.
* Documentation from https://tt-rss.org has been recreated in https://github.com/tt-rss/tt-rss/wiki .
  * The repository that held the content for https://tt-rss.org was mirrored to https://github.com/tt-rss/tt-rss-web-static .
    Some content tweaks were made after mirroring (prior to the wiki being set up), and the repository is now archived.
* Docker images (for `linux/amd64` and `linux/arm64`) are being built and published ([via GitHub Actions](https://github.com/tt-rss/tt-rss/actions/workflows/publish.yml)) to:
  * Docker Hub (as [supahgreg/tt-rss](https://hub.docker.com/r/supahgreg/tt-rss/) and [supahgreg/tt-rss-web-nginx](https://hub.docker.com/r/supahgreg/tt-rss-web-nginx/)).
  * GitHub Container Registry (as [ghcr.io/tt-rss/tt-rss](https://github.com/orgs/tt-rss/packages/container/package/tt-rss)
    and [ghcr.io/tt-rss/tt-rss-web-nginx](https://github.com/orgs/tt-rss/packages/container/package/tt-rss-web-nginx)).
  * See [the installation guide](https://github.com/tt-rss/tt-rss/wiki/Installation-Guide) for how the images can be used.

## Development and contributing

* Contributions (code, translations, reporting issues, etc.) are welcome.
* Development and issue tracking primarily happens in https://github.com/tt-rss/tt-rss .
* Help translate tt-rss into your own language using [Weblate](https://hosted.weblate.org/engage/tt-rss/).

## License

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

Copyright (c) 2005 Andrew Dolgov (unless explicitly stated otherwise).
