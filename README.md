RedBean German Porter Stemmer plugin
=======================

This is a plugin for the [RedBeanPHP ORM](http://www.redbeanphp.com/), which
is performing the german [Porter Stemmer algorithm](http://en.wikipedia.org/wiki/Stemming)
and using the root form of the stemmed word to issue a search on a specific Bean property.

Please note as the name says it's for german language. Implementations for other languages
are not planned by this project.

The algorithm is based on the [Drupal Plugin by Reiner Miericke](https://drupal.org/project/de_stemmer)

Current status:
Seems to work :)


Usage / Examples:
=======================

- Download the latest version of [RedBean from Github](https://github.com/gabordemooij/redbean) or
  install via Composer.
- Add the file RedBean_German_PorterStemmer.php to the RedBean/Plugin folder
- Either manually require the file or see the [RedBean instructions](http://www.redbeanphp.com/replica) for building your on RB.php file

There are two things you can do with the plugin. First just get a stemmed version of a word
by doing the following:

```php
   R::stem("YOUR SEARCH PHRASE");
```

Note that the search phrase may consist of several words and the module will provide you
the stems of each of them. The return value is an associative array with the original word as key
and the stemmed version as value.

```php
   R::stem("neuerliche Ausschreitungen");

   array
    'neuerliche' => string 'neuer' (length=5)
    'Ausschreitungen' => string 'ausschreit' (length=10)
```
The other feature and actually the main idea, is to provide a facetted search via stems.
This is done by using the following function:

```php
   R::stemmedSearch("YOUR SEARCH PHRASE");
```

Take a look at the included example.php for a detailed example

Stopwords and exception:
=======================
You can also define your custom stopwords and exceptions which won't get stemmed
or respect the exception. To modify those simply open the file data.inc and
populate the arrays with your values.

Defaults are already included
