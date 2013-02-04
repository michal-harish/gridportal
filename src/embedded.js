document.observers = new Array();
function observer(portlet,fragments,url,last_modified,max_age) {
	this.portlet = portlet;
	this.fragments = fragments;
	this.last_modified = last_modified;
	this.max_age = max_age == undefined ? 5 : max_age;
	
	document.observers.push(this);
	this.i = document.observers.length-1;
	this.url = url;

	var that = this;

	if (window.XMLHttpRequest)  this.xmlhttp=new XMLHttpRequest(); // code for IE7+, Firefox, Chrome, Opera, Safari		  
	else this.xmlhttp=new ActiveXObject("Microsoft.XMLHTTP"); // code for IE6, IE5
	this.xmlhttp.onreadystatechange=function()	
	{
		if(that.xmlhttp.readyState==4) {
		  if (that.xmlhttp.status == 200 ) {	
			  fragments = that.fragments.split(",");
			  for(h=0; h<fragments.length; h++) {
				  fragment = fragments[h];
				  element = null;

				  fragmentInstances = document.getElementsByClassName(that.portlet+" fragment ");
				  for(j=0; j< fragmentInstances.length; j++) {
					  if (fragmentInstances[j].id == fragment) {
						  element = fragmentInstances[j];
						  break;
					  }
				  }

				  if (element == null) {
					  return; //quit and don't queue again if element is not found
				  }

				  x = that.xmlhttp.responseXML.documentElement.childNodes;
				  i=0; for(i=0; i<x.length; i++) {
					  if (x.item(i).nodeName=='#text') {
						  continue;
					  } else if (x.item(i).getAttribute("id") == fragment) {
						  element.innerHTML = (new XMLSerializer()).serializeToString(x.item(i));

					  }
				  }
			  }

			  m = that.xmlhttp.getResponseHeader("Last-Modified");
			  if (m!= undefined && m!=null) {
				  that.last_modified = m;
			  }
			  c = that.xmlhttp.getResponseHeader("Cache-Control")
			  if (c!= undefined && c!=null) {
				  p = c.indexOf("max-age");
				  if (p>=0) { 
					  that.max_age = c.substring(p+8).match(/^\s*[0-9]+/i);
				  }
			  } else {
				  //if the portlet doesn't say how often to poll default is 15 seconds
				  that.max_age = Math.max(that.max_age,15);
			  }
			  that.queue();
		  } else if (that.xmlhttp.status == 304) {
			  that.queue();
		  }
		}
	}

	this.go = function() {		
		this.xmlhttp.open("GET",window.location+"#"+this.portlet+"/"+this.fragments,true);

		this.xmlhttp.overrideMimeType('text/xml');
		this.xmlhttp.setRequestHeader("If-Modified-Since", this.last_modified);
		this.xmlhttp.setRequestHeader("Portlet-fragments", this.fragments);
		this.xmlhttp.setRequestHeader("X-domain-request", this.url);
		this.xmlhttp.send(null);

	}

	this.queue = function() {
		setTimeout("document.observers["+this.i+"].go()", this.max_age*1000);
	}
	
	this.queue();

}

