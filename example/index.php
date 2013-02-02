<?php
	error_reporting(E_ALL | E_STRICT);
	
	//$host = $_SERVER['HTTP_HOST'];
	//$URI = trim($_SERVER['PATH_INFO'],'/');
	$URI = current(explode('?',$_SERVER['REQUEST_URI']));		
	if (!isset($_COOKIE['$debug']))  {
		//if ($_SERVER['REQUEST_METHOD']=='POST') header("Location: $URI");
	}
	 
	require("../.php");

?><!DOCTYPE html>
<html xmlns:g="../portlets.xsd">
	<head>		
		<!-- standard html head -->
		<title>Test GridPortal</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>		
		<link rel="stylesheet" type="text/css" href="/layout.css" />				
		
		<!-- portlet declarations -->
		<g:portlet id="search" url="/portletSearch.phtml"/>		
		<g:portlet id="auth" url="/portletAuthOpenID.phtml"/>
		<g:portlet id="nav" url="/portletNav.phtml"/>
		<g:portlet id="inbox" url="/portletInbox.phtml"/>
		<g:portlet id="contact" url="/portletContact.phtml" />
		<g:portlet id="debug" url="/portletDebug.phtml">
			<g:param name="user" cookie="$email"/>
		</g:portlet>

	</head>
	<body>

		<div id="header" >				 				
			<h2>							
				<g:portlet id="auth" fragment="default"/>
				<g:portlet id="debug" fragment="default"/>
				<g:portlet id="search" fragment="searchTools"/>				
				<span>GridPortal Example</span>
				<g:portlet id="nav" fragment="default"/>
				<br class="clr"/>
			</h2>
		</div>

		<table style="width:100%;" >
			<tr>
				<td class="col1">
					<h1><span>Search Results</span></h1>
					<g:portlet id="search" fragment="searchResults"/>
					<br/>
					
					<?php if (isset($_COOKIE['$email'])) : ?>
						<div id="inboxView">			
							<h1><span>Inbox Messages</span></h1>									
							<g:portlet id="inbox" fragment="inboxView"/>						
						</div>
						<br/>
					<?php endif; ?>	
										
					<h1><span>gridport.co monitor</span></h1>
					<div class="menu tabular">
						<a href="?tab1=settings"><span>Settings</span></a>
						<a href="?tab1=lastlog"><span>Last Log Entry</span></a>
						<a href="?tab1=processlist"><span>Process List</span></a>
					</div>
					<div class="panel">
						<?php switch(isset($_GET['tab1']) ? $_GET['tab1'] :null) : case 'settings': ?>
						<blockquote>this is a non-ajax portlet </blockquote>
						<?php break;  default: case 'lastlog': ?>
						<blockquote>(this is an instant listener that "waits" on observed file which is modified by JMS receiver)</blockquote>
						<g:portlet class="ajax" id="gridport-log" url="http://services:88/log/monitor.php" fragment="activity"/>						
						<?php break; case 'processlist': ?>
						<blockquote>(initial poll after 3 seconds, then default 15 because the protlet doesn't send any caching headers)</blockquote>
						<g:portlet class="ajax" id="gridport" url="http://gridport.co/manage/" fragment="processlist"/>						
						<?php endswitch;?>
					</div>
					<br/>
				</td>
				<td class="col2">
					<h1><span>...</span></h1>
					...
					<br/>
				</td>
				<td class="col3">
					<h1><span>Send us a message</span></h1>
					<g:portlet id="contact" fragment="default"/>
				</td>				
			</tr>		
		</table>		
				

		<div id="footer">
			GridPortal Static Footer
		</div>
		
	</body>
</html>
