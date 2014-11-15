/**
 * @file tests/browserProfiles/firefox/user.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Set firefox preferences.
 * @see http://kb.mozillazine.org/User.js_file
 */


user_pref("browser.download.dir", "/home/travis/downloads");
user_pref("browser.download.folderList", 2);
