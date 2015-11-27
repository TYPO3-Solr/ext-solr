# Apache Solr for TYPO3 CMS

[![Build Status](https://travis-ci.org/TYPO3-Solr/ext-solr.svg?branch=master)](https://travis-ci.org/TYPO3-Solr/ext-solr)

An extension that integrates the Apache Solr enterprise search server with TYPO3 CMS.

The extension has initially been developed by dkd Internet Service GmbH and is now being continued as a community project. The version you find here is a version that does not contain all the features that have been implemented yet. These features can be obtained through add-ons for the Extension.

In case you need access to the full feature set, please feel free to contact us for details.

Things we are working on or got working already include the following:

- Statistics
- An Indexing Queue to be independent from frontend rendering and adding content to Solr as soon as an editor creates a content element in the backend
- Suggest / Autocomplete
- More Like This
- Several Reports
- Advanced faceting features including hierarchical facets
- Backend Module for Solr administration
- Results Grouping
- Language Detection
- Crawling of External non-TYPO3 Websites
- more ...

We're open for [contributions](#Contributions) !

Please find further information regarding Apache Solr and its related projects at the following links:

- http://www.typo3-solr.com/
- http://www.dkd.de/de/agentur/produkte/apache-solr/
- https://github.com/TYPO3-Solr
- http://lucene.apache.org/
- http://lucene.apache.org/solr/
- http://tomcat.apache.org
- http://www.eclipse.org/jetty/

To try out Apache Solr for TYPO3 visit [www.typo3-solr.com](http://www.typo3-solr.com) where we've indexed TYPO3 mailing lists as a showcase. Another showcase can be found here: [search.dkd.de](http://search.dkd.de/solr.html)

![dkd Internet Service GmbH](http://www.dkd.de/typo3conf/ext/dkd_standard/res/dkd.de/assets/images/logo-top.png)

## Documentation and Support

-   **Main Documentation:**

    https://forge.typo3.org/projects/extension-solr/wiki


-   **Slack Channel:**

    https://typo3.slack.com/messages/ext-solr/
    
    (request your invite for Slack here: https://forger.typo3.org/slack)


-   **Mailinglist:**

    http://lists.typo3.org/cgi-bin/mailman/listinfo/typo3-project-solr

## <a name="Contributions"></a>Contributions

1. Fork the repository on Github
2. Clone repository
3. Make your changes
4. Commit your changes to your fork. In your commit message refer to the issue number if there is already one, e.g. `[BUGFIX] short description of fix (resolves #4711)`
5. Submit a Pull Request using GitHub (here are some hints on [How to write the perfect pull request](https://github.com/blog/1943-how-to-write-the-perfect-pull-request))

### Keep your fork in sync with original repository

1. git remote add upstream https://github.com/TYPO3-Solr/ext-solr.git
2. git fetch upstream
3. git checkout master
4. git merge upstream/master
5. git push origin master
