CultureObject v2.0
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

1) eee



Usage Instructions
---------------------
We use the master branch here for development. If you want a known working build, grab the latest tag. (Currently 1.1)

Originally an in-house project at [Thirty8Digital](http://www.thirty8digital.co.uk) by [Liam Gladdy](https://gladdy.uk)

We're currently building CultureObject into an expandable framework that will be listed in the WordPress plugin directory, and allow third-party plugins to add supply additional providers.

Presently, if you want to write a custom provider, you'll need to follow the default providers in the providers directory with the abstract class in CultureObject/Provider.class.php also giving you some pointers.

###A note of caution
We're building out CultureObject 2.0 to support field mapping and image importing into WordPress which will result in significant changes to provider classes as we move a bunch of common and new functionality into helper classes. If you're looking to go into a production environment in the near term, we'd recommend building against CultureObject 1.1, which we will continue to support until 2.0 is released.
