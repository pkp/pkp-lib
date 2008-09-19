Welcome to Zend Framework 1.5.3! This is a maintenance release of the 
Zend Framework 1.5 series. This release maintains backwards compatibility with
the Zend Framework 1.0 series and has been rigorously tested with many
applications written for 1.0.
HOWEVER, PLEASE READ ALL SPECIAL NOTICES FOR UPGRADING IN THIS README *BEFORE*
UPGRADING APPLICATIONS WRITTEN ON ZF 1.0 TO ZF 1.5.

RELEASE INFORMATION
---------------

Zend Framework 1.5.3 (revision 10507).
Released on 2008-07-28.

SPECIAL NOTICES FOR UPGRADING TO ZF 1.5
---------------------------------------

* If you are upgrading from a 1.0 ZF release to a 1.5 ZF release and you are using
  Zend_Search_Lucene, you should be aware that Zend_Search_Lucene now works
  exclusively with Apache Lucene 2.1 index file format. Conversion from the
  previous format (1.9) is performed automatically during the first index update
  after the ZF 1.5 release is installed. *THIS CONVERSION CANNOT BE UNDONE*.
  Please backup your Lucene index if you plan to rollback to 1.0 versions of Zend
  Framework and wish to continue using this index.

* Some developers have relied on undocumented and unintended behavior of
  Zend_Controller in 1.0 ZF releases that allowed resolution from camelCased URLs to
  controller actions. This unintended behavior, however, causes indeterminate results
  later in the request dispatching process. For this reason, we have chosen to enforce
  our documented recommendations. URLs now require word separator characters when
  resolving to camelCased action methods. For more information, please see:

    http://framework.zend.com/manual/en/zend.controller.migration.html


NEW FEATURES IN 1.5
-------------------

* New Zend_Form component with support for AJAX-enabled form elements
* New action and view helpers for automating and facilitating AJAX requests and
  alternate response formats
* LDAP, Infocard, and OpenID authentication adapters
* Support for complex Lucene searches, including fuzzy, date-range, and wildcard
  queries
* Support for Lucene 2.1 index file format
* Partial, Placeholder, Action, and Header view helpers for advanced view
  composition and rendering
* New Zend_Layout component for automating and facilitating site layouts
* UTF-8 support for PDF documents

ENHANCEMENTS AND BUGFIXES IN 1.5
--------------------------------

* Zend_Json has been augmented to convert from XML to JSON format
* New Zend_TimeSync component supporting the Network Time Protocol (NTP)
* Improved performance of Zend_Translate with new caching option
* addRoute(), addRoutes(), addConfig(), removeRoute(), removeDefaultRoutes()
  methods of Zend_Controller_Router_Rewrite now support method chaining
* Yahoo web service supports Yahoo! Site Explorer and video searches
* Database adapter for Firebird/Interbase
* Query modifiers for fetch and find methods in Zend_Db_Table
* 'init' hook to modify initialization behaviour in subclasses Zend_Db_Table,
  Rowset, and Row
* Support for HTTP CONNECT requests in Zend_Http_Client
* Support for PHP's hash() for read/write control in Zend_Cache
* Zend_Cache_Backend_File may be configured to call ignore_user_abort() to
  maintain cache data integrity
* Timezone in Zend_Date may be set by locale
* Zend_Cache can now use custom frontend and backend classes

A detailed list of all features and bug fixes in the 1.5.0 release may be found at:

http://framework.zend.com/issues/secure/IssueNavigator.jspa?requestId=10710

A detailed list of all bug fixes between 1.5.1 and 1.5.2 release may be found at:

http://framework.zend.com/issues/secure/IssueNavigator.jspa?requestId=10743

A detailed list of all bug fixes between 1.5.2 and 1.5.3 release may be found at:

http://framework.zend.com/issues/secure/IssueNavigator.jspa?requestId=10811

SYSTEM REQUIREMENTS
-------------------

Zend Framework requires PHP 5.1.4 or later. Please see our reference guide for
more detailed system requirements:

http://framework.zend.com/manual/en/requirements.html

INSTALLATION
------------

Please see /INSTALL.txt.

QUESTIONS AND FEEDBACK
----------------------

Online documentation can be found at http://framework.zend.com/manual. Questions
that are not addressed in the manual should be directed to the appropriate
mailing list:

http://framework.zend.com/wiki/x/GgE#ContributingtoZendFramework-
Subscribetotheappropriatemailinglists

If you find code in this release behaving in an unexpected manner or contrary to
its documented behavior, please create an issue in the Zend Framework issue
tracker at:

http://framework.zend.com/issues

If you would like to be notified of new releases- including further maintenance 
releases for Zend Framework 1.5- you can subscribe to the fw-announce mailing list
by sending a blank message to fw-announce-subscribe@lists.zend.com.

LICENSE
-------

The files in this archive are released under the Zend Framework license. You can
find a copy of this license in /LICENSE.txt.

ACKNOWLEDGEMENTS
----------------

The Zend Framework team would like to thank all the contributors to the Zend
Framework project, our corporate sponsor (Zend Technologies), and you- the Zend
Framework user. Please visit us sometime soon at http://framework.zend.com!
