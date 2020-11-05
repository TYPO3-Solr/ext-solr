# Contributing to Apache Solr for TYPO3

You want to invest your time into EXT:solr? That's awesome, because there are
always things that you can do to help improve Apache Solr for TYPO3:

* Bug reports
* Feature requests
* Testing
* Documentation
* Support
* Translation
* Add-ons

In this guide we describe how you can contribute to EXT:solr, what are the
guidelines and where you can get further information.

## How To Develop

The source-code of EXT:solr is hosted on [GitHub](https://github.com/TYPO3-Solr/ext-solr).
The code is organized in branches

* master: The master branch is the branch where the development for the latest
  TYPO3 version and the latest features is happening. It is our goal to keep
  this branch always working, but nevertheless it is a development branch. It is
  not recommended to use this branch in production.

* release-X.X.x: Whenever major and minor releases are created from master we
  create a release branch. This is needed to allow providing bug fixes for older
  release branches.

If you want to help with development you should have the following things running locally:

* Some PHP environment - f.e. LAMP Stack - with TYPO3
* Apache Solr server with proposed version and configured schema & plugins -
  You can find a script included in the extension's "Resources" directory to
  help you to easily set up Solr and the required configuration
  
Please check the documentation for the required [versions](Documentation/Appendix/VersionMatrix.rst).

You can use the following preconfigured development environments to get started
very quickly:

### Docker using ddev

There is a ddev configuration with a preconfigured TYPO3 10 LTS and a
local Solr server (version 8.5.1):

https://github.com/TYPO3-Solr/solr-ddev-site

To start the ddev follow its README.

## Bug Reports & Feature Requests

You've found a bug or have an idea for a new feature or even better you've
developed a new feature and want to share it? That's Great!

In EXT:solr we organize the tasks that we are working on using [GitHub issues](https://github.com/TYPO3-Solr/ext-solr/issues).

The first step should be to create a new issue or start a discussion in our [Slack channel](https://typo3.slack.com/messages/ext-solr/).

### Bug Reports

In case of a bug report it is essential for us to know as much as possible about
how to reproduce the issue in order to quickly fix it.

The following information is useful:

* TYPO3 version
* Solr version
* PHP version
* Error messages / stack traces
* Expected behavior
* Actual behavior
* Steps how to reproduce the issue

In an ideal case you create a pull request and in the perfect case your pull
request already contains a regression test that demonstrates the issue and
prevents us from having the same issue again.

### Submitting Pull Requests

When you create a pull request, the message should

* Contain a meaningful title prefixed with the type of task ([BUGFIX] / [TASK] / [FEATURE])
* Contain a description with
** What was changed
** A relation to the issue the PR fixes by adding "Fixes #<issuenumber>"

You may also want to read the following:
https://github.com/blog/1943-how-to-write-the-perfect-pull-request

#### Commits

It is good practice if your pull request is as atomic as possible. Keep each
commit small and focus on one change. This way it is easier to follow your
changes during review.

#### Keep your fork up to date

Before you create a branch for new changes you should update your fork with the
latest changes from our master.

To do this you need to do the following steps:

1. Checkout your master branch
2. Make sure our repository is configured as upstream (You only need to do this once)
3. Rebase your master onto the upstream repository's changes
4. Push the changes to your master

```bash
git checkout master
git remote add upstream https://github.com/TYPO3-Solr/ext-solr.git
git pull --rebase upstream master
git push --force origin master
```

#### An example git workflow

In the beginning it seems hard but after some time it's really handy to follow
this example of how to prepare a pull request from your local branch.

Before you start, create your own fork on GitHub and follow these steps:

```bash
git clone https://github.com/YourGitHubAccount/ext-solr.git
git checkout master
git remote add upstream https://github.com/TYPO3-Solr/ext-solr.git
git checkout -b 'bugfix/4711-my-bugifx'

# Now do your coding and testing
git commit -m "initial commit"

# Now you do some implementation and testing and commit you improvements
git commit -m "do some cleanup"

# Check the log and rebase
git log
commit 0ee764f44de07ea97b19ad8272f60f3011bbf52b
Author: Timo Schmidt <timo.schmidt@dkd.de>
Date:   Thu Jan 14 10:17:04 2016 +0100

    Fixed wording

commit 1b8ba76ac15263876d71088ffc05983b40919919
Author: Timo Schmidt <timo.schmidt@dkd.de>
Date:   Thu Jan 14 10:16:20 2016 +0100

    First implementation

commit 1ea657dfed4d41a4e457a15520d4e95efba4a4c3
Author: Ingo Renner <ingo@typo3.org>
Date:   Thu Jan 7 22:12:37 2016 -0800

    Last commit from master

    Change-Id: I17905b641ef322da09d2b93ed8adbd279ec680f0

# Git rebase
git rebase -i I17905b641ef322da09d2b93ed8adbd279ec680f0

pick 1b8ba76 First implementation
s 0ee764f Fixed wording

# Rebase 1ea657d..0ee764f onto 1ea657d (2 command(s))
# ...

# Note that empty commits are commented out

# Push it to a remote branch of your fork
git push origin 'bugfix/4711-my-bugifx'
```

This was just an example, you can also push and rebase afterwards but digging
more into git would be a topic on its own.

### Review Process

To get a change merged you need to:

    * Create a pull request on GitHub, with a green build status.
    * Somebody else needs to test it and give a +1 before the feature can be merged.

## Testing and Quality Assurance

Our goal is to deliver working software, with as few bugs as possible and on the
other hand have the possibility to implement new features quickly. To make this
possible we have some conventions and use a set of tools that support us.

### Coding Guidelines

All files use the [PSR-2 coding standard](http://www.php-fig.org/psr/psr-2/).
The coding standard is automatically checked and enforced for every pull request
by our continuous integration scripts.

If you want to check the code-style locally before you commit or just
automatically want to fix it there is a great tool:

```bash
composer install
./.Build/bin/php-cs-fixer fix -v --level=psr2 --dry-run Classes
```

When you remove the --dry-run flag php-cs-fixer also fixes all violations.

### Namespacing and Package Structure

Since version 3.1 we've switched to namespaces and use the "PSR-4" standard for
class loading. In a nutshell this means:

1. All classes in the "Classes" folder have the root namespace `ApacheSolrForTypo3\Solr`
2. The namespace below `ApacheSolrForTypo3\Solr` represents the directory
   structure and should be in upper camel case.
3. Make sure that the casing matches since most server operating file systems
   are case sensitive.

### Manual testing

Changes should at least be tested manually to make sure the extension is still
working and no new problems are being introduced.

### Automated tests

To speed up the development process and allow major refactoring and improvements
in a reasonable time we want to improve our set of automated tests.
This also helps us to continuously integrate EXT:solr with the latest state of
TYPO3 CMS development.

#### Unit Tests

In "Tests/Unit" you can find unit tests for some classes. It is our goal and
it is highly appreciated if you add tests for your code or contribute tests for
existing code.

#### Integration Tests

To test the integration between different classes and components you can add
integration tests that cover these components.
In our test setup we currently do the following:

1. We use the TYPO3 Core functionality for "Functional" tests that sets up a
   dedicated test database and can be used in the integration test.
2. We use our install script to set up a local Solr server on the test system.
   By doing this, we can do "end-to-end" tests and check whether the whole
   "stack" is working.

The simplest way to use the testing framework is to just enable
[Travis CI](http://travis-ci.com) for your fork and automatically have Travis
execute the tests for you. If you want to run the tests on your local system
you need to follow these steps:

One time preparation:

```bash
cd <solrroot>
chmod u+x ./Build/Test/*.sh
./Build/Test/cleanup.sh
source ./Build/Test/bootstrap.sh --local
```

Each time you want to run the test suite:

```bash
./Build/Test/cibuild.sh
```

Make sure that the test suite is running, before you do a pull request.

As alternative for a local run you can use our docker test setup to run the tests

```bash
cd ./Docker/Ci
make bootstrap
```

and afterwards:

```bash
make test
```



## Documentation

The documentation for typo3-solr exists in the *Documentation* subdirectory.
 
It is rendered on docs.typo3.org:

* https://docs.typo3.org/p/apache-solr-for-typo3/solr/master/en-us/

It can be modified by changing the reStructuredText files (.rst).

The documentation for this extension has the same structure as the 
official TYPO3 documentation and is generated using the same workflow,
tools and infrastructure as the official TYPO3 documentation. 

Please look at the general information about TYPO3 documentation for 
more information:

* [Directory and file structure](https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/GeneralConventions/DirectoryFilenames.html)
* [How to contribute]()
* [reStructuredText & sphinx](https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/WritingReST/Index.html)
* [Render documentation with Docker](https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/RenderingDocs/Index.html)

For issues and pull requests, please use the tag [DOCS] in your commit 
messages / PR title / issue title, e.g.: 

    [DOCS] Fix typos


## Translations

### Up to Version 3.1.x

Until version 3.1.x the translation was done in the old TYPO3 locallang XML
format. These files are available on http://translation.typo3.org

### After 3.1.x

We've migrated the translation files to the new Xliff format. They can be found
in "Resources/Private/Language". We appreciate any help here and it would be
nice, if you could share your translations to complete the defaults here.

## Add-ons

EXT:solr provides a set of hooks to extend it or use objects from EXT:solr in
your own extension and build your custom functionality.
On https://github.com/TYPO3-Solr/ we provide a couple add-ons to use with EXT:solr.

If you want to share your own add-on and would like to make it available within
the [Apache Solr for TYPO3 GitHub organization](https://github.com/TYPO3-Solr/)
please don't hesitate to contact us!

## Support

### Community Support

The main support channel is our [Slack channel](https://typo3.slack.com/messages/ext-solr/).
You can get support there from other community members and also support other
users with the issues they might have.

### Supporting Further Development

You can support further development by becoming a partner. Check http://www.typo3-solr.com or call [dkd](http://www.dkd.de) (+49(0)69-24752180).

## Links

* http://www.dkd.de
* http://www.typo3-solr.com
* https://github.com/TYPO3-Solr
* https://forge.typo3.org/projects/extension-solr/wiki
* https://github.com/TYPO3-Solr/ext-solr/issues
