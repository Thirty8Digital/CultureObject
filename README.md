CultureObject v2.0.1
====================

Welcome
---------------------
CultureObject is an open source WordPress plugin designed to help you put your museum object records on the web. 

How it works
---------------------

* Install it from here (and shortly the WordPress plugin respository)
* Activate it
* Point it at your data
* Run the import
* Build the necessary theme pages to display your content. 
* Point in wonder at your beautiful new site before heading to the pub to celebrate.

At present the data you provide to CultureObject can be one of two flavours:

1. A publicly queryable RESTy API
2. A structured xml / json data file

The data "shape" - and how to deal with the data the plugin finds - is dealt with in what are called "providers". These are PHP classes which you'll find in the /providers directory.

The plugin is built to be extensible so that when other providers are added to this directory it'll pick them up in the interface and give you the option to select them and run the import.


Usage Instructions
---------------------
We use the master branch here for development. If you want a known working build, grab the latest tag. (Currently 1.1)

Originally an in-house project at [Thirty8Digital](http://www.thirty8.co.uk) by [Liam Gladdy](https://gladdy.uk)

We're currently building CultureObject into an expandable framework that will be listed in the WordPress plugin directory, and allow third-party plugins to add supply additional providers.

Presently, if you want to write a custom provider, you'll need to follow the default providers in the providers directory with the abstract class in CultureObject/Provider.class.php also giving you some pointers.

###A note of caution
We're building out CultureObject 2.0 to support field mapping and image importing into WordPress which will result in significant changes to provider classes as we move a bunch of common and new functionality into helper classes. If you're looking to go into a production environment in the near term, we'd recommend building against CultureObject 1.1, which we will continue to support until 2.0 is released.
