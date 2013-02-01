gridportal
==========

Micro-framework for HTTP Portlels for PHP insipred by Java Portlet Specification.

In short, an application that includes the main file of the framework may 
output may a special <portlet url="http://..."/> tag which will be picked up by the gridportal
hook, which will in turn invoke server-to-server http request to the given url and replaced the tag 
withthe response.

Apart from just pulling the url and inserting the response in place of the <portlet> tag, it
does a few cool things
* it passes through all the request headers to each portlet
* http caching instructions received from the portlet response are obeyed including conditional requests
* ordinary cookies are completely transpart so any portlet can use cookies just like normally
* it has an extra concept of global cookies that are visible in all portlets embedded on a single portal
* portlets can contain <form> tags and receive POSTs against them transparently without any special knowledge
* and more...

