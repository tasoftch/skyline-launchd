# Skyline Launchd
The launchd package allows your application to interact with command line tasks.  
In the Public/ directory the main skyline.php is located as main entry point of your application.  
This file usually gets called from a webserver to deliver a response for a request. In some cases it can be necessary to handle different.

That's why Skyline Launchd exists: It extends your application to respond command line tasks.  
Those tasks are different than requests.

#### Installation
```bin
$ composer require skyline/launchd
```
