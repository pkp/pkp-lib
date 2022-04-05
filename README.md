# Open Preprint Systems

> Open Preprint Systems (OPS) has been developed by the Public Knowledge Project. For general information about OPS and other open research systems, visit the [PKP web site][pkp].

[![Build Status](https://app.travis-ci.com/pkp/ops.svg?branch=main)](https://app.travis-ci.com/pkp/ops)

## Using Git development source

Checkout submodules and copy default configuration :

    git submodule update --init --recursive
    cp config.TEMPLATE.inc.php config.inc.php

Install or update dependencies via Composer (https://getcomposer.org/):

    composer --working-dir=lib/pkp install
    composer --working-dir=plugins/generic/citationStyleLanguage install

Install or update dependencies via [NPM](https://www.npmjs.com/):

    # install [nodejs](https://nodejs.org/en/) if you don't already have it
    npm install
    npm run build

If your PHP version supports built-in development server :

    php -S localhost:8000

See the [Documentation Hub][doc-hub] for a more complete development guide.

## Community Code of Conduct
This repository is one of PKP's community spaces and all activities here are guided by [PKP's Code of Conduct](https://pkp.sfu.ca/code-of-conduct/). Please review the Code and help us create a welcoming environment for all participants.

## License

This software is released under the the [GNU General Public License][gpl-licence].

See the file [COPYING][gpl-licence] included with this distribution for the terms
of this license.

Third parties are welcome to modify and redistribute OPS in entirety or parts
according to the terms of this license. PKP also welcomes patches for
improvements or bug fixes to the software.

[pkp]: https://pkp.sfu.ca/
[readme]: docs/README.md
[doc-hub]: https://docs.pkp.sfu.ca/
[php-unit]: https://phpunit.de/
[gpl-licence]: docs/COPYING
