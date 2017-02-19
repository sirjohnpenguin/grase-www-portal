# Grase Hotspot

The GRASE Hotspot is a project that glues individual components together easily, and provides a nice simple interface to administer the hotspot. With a lot of time and effort, most people can follow the tutorials on the internet and setup up MySQL, FreeRadius, CoovaChilli, Squid and any other optional components, and setup a Captive Portal Hotspot. But as soon as one component changes, for example FreeRadius changed how it’s config files are handled, the whole system breaks and the tutorial doesn’t help.

The GRASE Hotspot takes all the hard work out of keeping the individual components glued together, and provides a nice simple interface to manage the Hotspot and it’s users. As components change, the project is updated so the glue stays together.

## Installation

See the Wiki page <https://github.com/GraseHotspot/grase-www-portal/wiki/Installation>

### grase-www-portal package/repository

This is the main Grase Hotspot package. It contains the web interface, and depends on the config packages.

## qrcode support


QR Code support using phpqrcode library from <http://phpqrcode.sourceforge.net/>
Tested on ubuntu 14.04. Requires php5-gd

Uses the openssl php functions for encrypt/decrypt credentials for login in the hotspot.

