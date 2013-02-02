document.observers = new Array();
function observer(portlet,fragment,url,last_modified,max_age) {
	this.portlet = portlet;
	this.fragment = fragment;
	this.last_modified = last_modified;
	if (max_age == undefined ) max_age = 5;
	this.max_age = Math.max(5,max_age);
	
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
			  
			  element = null;
			  //element = document.getElementById(that.fragment);
			  fragments = document.getElementsByClassName(that.portlet+" fragment ");
			  for(j=0; j< fragments.length; j++) {
				  if (fragments[j].id == that.fragment) {
					  element = fragments[j];
					  break;
				  }
			  }
			  
			  if (element == null) {
				  return; //quit and don't queue again if element is not found
			  }
			  /*
			  x = that.xmlhttp.responseXML.getElementsByTagName("body").item(0).childNodes;
			  i=0; while(x.item(i).nodeName=='#text') { i++;}
			  y= x.item(i);
			  var frag = that.xmlhttp.responseXML.createDocumentFragment();
			  frag.appendChild(y);
			  newContent= new XMLSerializer().serializeToString(frag);			  
			  */
			  newContent = that.xmlhttp.responseText;
			  element.innerHTML = newContent;
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
		this.xmlhttp.open("GET",window.location+"#"+this.portlet+"/"+this.fragment,true);		
		this.xmlhttp.overrideMimeType('text/xml');
		this.xmlhttp.setRequestHeader("If-Modified-Since", this.last_modified);
		this.xmlhttp.setRequestHeader("Portlet-fragments", this.fragment);
		this.xmlhttp.setRequestHeader("X-domain-request", this.url);
		this.xmlhttp.send(null);				
	}
	
	this.queue = function() {
		setTimeout("document.observers["+this.i+"].go()",Math.max(500,this.max_age*1000));
	}
	
	
	this.queue();

}

