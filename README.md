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
* supports cross-domain javascript 
* and more...

How to run it
=============
Requirements:  PHP5 + php_curl extension

To run gridportal you need to add it as git submodule into your application and include the main
runtime file at the very beginning of your script so that the output buffer hook can be created in time 
before your application starts flushing html out. If you have gridportal submodule in the root of your web
application in the directory 'gridportal' it could look like this: 

    <?php require("gridportal/.php"); ?>
    <html>
        <head>
            ..
            <portlet id="myportlet" url="http://my.portlets/portletHelloWorld.php"/>
            ..
        </head> 
        <body>
            <portlet id="myportlet" fragment="default"/>
        </body>
    </html>
 
Reference Manual
================
1.1 For HTTP Portlet Specification see [specification](/specification.html).
    
1.2 HTML Mark-Up Extension   
  
    1.2.1 Portlet declarations in the html document's head     
        <html>  
            <head>
            
                <!-- declaration of the portlet for entire document -->
                <portlet id="^[a-z_-0-9]+$" url="^/|https://">
                    
                    <!-- mapped url query string parameter; mandatory or optional-->                                                
                    <param name="" get="^[a-z_-0-9]+$" [required="yes"]/>
                    
                    <!-- mapped cookie; mandatory or optional; global $cookie or private portlet_id[cookie_name]        
                    <param name="" cookie="^\$[a-z_-0-9]+|[^\$\[]+\[[^\]]\]$" [required="yes"]/>
                    
                    <!-- a page-specific configuration param for portlet -->
                    
                    <!-- NOTE: mapped params will be part of the cache identifier whereas config params will not be -->                                   
                    <param name="" value="...some_value.."/>                    
                                                                                
                </portlet>
            </head>
            ...
        </html>
        
    1.2.2 References to portlet fragments from the html document's body
        <html>
            ... 
            <body>
                ...
                <!-- fragment refernce to portlet declared in head -->
                <portlet id="declared_portlet_id" fragment="<fragment_id>"/>        
                
                <!-- portlet fragment refernce with explicit url -->
                <portlet url="^/|https://" fragment="<fragment_id>"/>               
                
                <!-- all forms that don't have absolute actions will land on the target location-->
                <portlet url="^/|https://" fragment="<fragment_id>" target="some_url_or_uri"/>  
                ...
            </body>
        </html>
    
1.3 Portlet HTML Mark-Up Limitations

    1.3.1 Portlet Fragments
        - portlet is a standard html document wrapped in a <html> tag 
        - portlet's <body> is a container of fragments
        - fragments are any elements that have id attribute assigned            
        - fragments with class="ajax" must have id="<uniqueFragmentId>" and will be polled according to cache headers 

    1.3.2 Portlet Links (@href, @src and @action)
     
        - absolute links will not be touched
        - relative links that start with / will be extended to absolute links with the portlet base
        - all other relative links will have their query string extended for required portlet params
        - relative links that start with ? or # will not modify uri base        
        - relative links with uri base will have their base to / of the portal
            - therefore href="" will be pointing to the root of the portal /
        - portal query string params(non-portlet) will be preserved in the portlet links
        
        
    1.3.3 Portlet Styles
    
        - all inline style will be removed and warnings issued
        - class and id will be preserved but every fragment element will have it's class extended for the portlet_id
        - portlets may have style in the head element for design purpose ( see example )
        - upon insertion into the html documents fragments' class attribute will be prepended with "fragment" class 

1.4 Portlet Action Processing

    1.4.1 Action Process Overview
    
        - Only one portlet per request can be a target of action
        - Action's are declared via <form> tag  and are not cachable ( see 1.5 )    
        - both <form  method="get"> and <form method="post"> are allowed
        - either form has got a unique input pattern composed of named form elements (hidden,input, button, select, textarea)
        - or the portal will attach a hidden field with name="action" and value will be the portlet id
            - this will for example happen when there's more than one identical forms
        
1.5 Caching

    - gridportal caches only per reqeust/per client basis
    
    - the cache key is the portlet id + hash of the mapped parameters
    
    - actions as never cached ( that is parameters invoked via forms ) 
      it is down to portlet to implement caching (e.g. shared result sets in search-like portlets, etc.) 
      
    - if portlet does not use cach-control directive, response is cached until the portlet_prepare_request produces the same headers 

    - invalid portlet mark-up will cause initialization error and will not be cached    

     
        
1.6 Cookies

    There are two types of cookies
    1. shared - begin with '$' symbol and can be accessed from any portlet 
    2. private - each portlet has it's own transparent cookie  
    
    
1.7. Session
    - currently standard php session
    - session_benchmark($datetime) function is to be used after require(".php") to invalidate all sessions older than
        given date, which deals with upgrading the application
    
 
 

Backlog
=======
 * .js pass cookies (ideally using prepare_portlet_request from session cache)
 * SESSION needs to be abstracted away from default php session handler 
 * CACHE check for no-cache in portlet response headers and do not cache at all
 * CACHE aggregate min(portlets(expires)) for browser output 
 * AJAX HTTP_X_DOMAIN_REQUEST pass only portlet id, .php then opens the document
 * AJAX disable checking when fragment's form is modified 
 * XSD complete g:portlet/g:param elements schema
 * COOKIES hide `$` global cookies from client agents
 * COOKIES work out mechanism for user preferences ( cannot be simply override for globals )
 * unit tests
 * ACTIONS (1.4.2) Figure out how to handle redirects and links within portlets 
        if the level of isolation described in the GridPort API below is to be maintained.
        {Referer:  could be the URL of the client request on the portal}
        - 30x responses from portlets could either work as redirects from-portlet-to-portlet 
          or on behalf of client (like in the case of redirect web services )
 * CACHING create function that drops the whole portlet's cache and flags it as unavailable for timeout controlable by portlet header or default                    
        - and should be also used when load and cache retreival exceptions  
 * set-up continuous integration testing under tests.gridport.co        

