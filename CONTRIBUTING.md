# Contributing to EXT:solr

You want to invest your time into EXT:solr? That's awesome, because there are allways thinks that you can do to improve
ApacheSolrForTypo3:

* Bug reports
* Feature requests
* Testing
* Documentation
* Support
* Translation
* Addons

in this guid we want to describe how you can contribute to EXT:solr, what are the guidlines and where you can get further
information.

## HowTo Develop

The sourcecode of EXT:solr is hosted on github (https://github.com/TYPO3-Solr/ext-solr). The code is organized in branches

* dev-master: The dev-master branch is the branch where the development for the latest TYPO3 version and the newest features
is happening. It is our goal to keep this branch allways working, but nevertheless it is a development branch. It is not recommended
to use code from this branch directly in production.

* release-X.X.x: Whenever a release from dev-master is created, we create a release branch. This is needed to have the possibility
to provide bugfixes for an older release branch.

When you want to develop you should have the following things running locally:

*   LAMP Stack with configured TYPO3 Version
*   Solr Server with proposed Version and configured Schema & Plugins


The following version combinations are recommended:

* dev-master
    * TYPO3: 7.6.x (LTS) / dev-master
    * Solr: 4.10.4
    * Solr Plugins: solr-typo3-plugin-1.3.0.jar
    * Addons:
        * tika: dev-master
        * solrgrouping: dev-master
        * solrfal: dev-master

* 3.1.x
    * TYPO3: 6.2.x (LTS) / 7.6.x (LTS)
    * Solr: 4.10.4
    * Solr Plugins: solr-typo3-plugin-1.3.0.jar
    * Addons:
        * tika: 2.0.0
        * solrgrouping: 1.1.0
        * solrfal: 2.1.1

You can use the following preconfigured dev-environments to get started with development very quickly

### VagrantBox

There is a VagrantBox with a preconfigured TYPO3 6.2 LTS & 7.6 LTS with two installed local Solr servers (Version 4.8.0 &
Version 4.10.4):

https://github.com/TYPO3-Solr/solr-typo3-devbox

To start the box follow the README.

## Bug Reports & Feature Requests

You've found a bug or have an idea for a new feature, or even better you've developed a new feature and want to share it?
That's Great!

In EXT:solr we organize the Tasks where we are working on with github Issues:

https://github.com/TYPO3-Solr/ext-solr/issues

The first step should be, to create a new issue or start a discussion in our slack channel:

https://typo3.slack.com/messages/ext-solr/

### Bug Reports

In case of a bug report it is good for us to know as much as possible to reproduce the problem in order to fix it fast
and properly. The following information is useful:

* TYPO3 Version:
* Solr Version:
* PHP Version:
* Error Messages / Stacktraces:
* Steps how to reproduce:

In the ideal case you come up with a pull request and in the perfect case your pull request allready contains a regression test
case that demonstrates the edge case and prevents us from having the same issue again.

### Submitting Pull Requests

When you create a pull request, the message should

* Contain a meaningful title prefixed with the type of task ([BUGFIX] / [TASK] / [FEATURE])
* Contain a description with
** What was changed
** Add a fix relation to the Issue by adding "fixes #<issuenumber>"

Maybe read also:

https://github.com/blog/1943-how-to-write-the-perfect-pull-request

#### Commits

It is good when your pull request is as atomic as possible. In the best case it just contains one commit.
You can achieve this by "squashing" multiple commits in you branch to just one commit, before doing a pull request.

1. Check the log to find the first commit before your first commit
2. Rebase and squash all commits into a single one
3. Do a forced push and rewrite you commits

```bash
git log
git rebase -i <lastcommittokeep>
git push origin 'bugfix/4711-my-branch' -f
```
#### Keep your fork up to date

Before you create you branch it is good to get the latest changes from our master branch into your fork.
To do this you need to do the following steps:

1. Checkout your master branch
2. Make sure our repository is configured as upstream (You only need to do this once)
3. Rebase your master with upstream
4. Push the changes to your master

```bash
git checkout master
git remote add upstream https://github.com/TYPO3-Solr/ext-solr.git
git pull --rebase upstream master
git push --force origin master
```

#### An example git workflow

In the beginning it seems hard but after a time it's really handy the following example shows you how to
prepare a pull request from you local branch.

Before you start, create your own fork on github and the do the following steps:

```bash
git clone https://github.com/YourGitHubAccount/ext-solr.git
git checkout master
git remote add upstream https://github.com/TYPO3-Solr/ext-solr.git
git checkout -b 'bugfix/4711-my-bugifx'

# Now you do some implementation and testing
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

This was just an example, you can also push and rebase afterwards but digging more into git would be a complete topic.

### Review Process

To merge a feature you need to:

    * Create a pull request on github, with a green build status.
    * Somebody else needs to test it and give a +1 before the feature can be merged.

## Testing and Quality Assurance

Our goal is to deliver a working software, with less bugs a possible and on the other hand have the possibility
to implement new features quickly. To support all theses needs we have some conventions and use a set of tools that
support us.

### Coding Guidelines

Why do we need them? There are several reasons:

1. Everybody that is used to the standard is used to our code style.
2. Patches are easier to apply because the codestyle is unified.

Therefore we decided that:

All classes are using the psr-2 coding standard (http://www.php-fig.org/psr/psr-2/). The coding style is also automatically checked in all pull requests with our continuous integration scripts.

If you want to check the codestyle locally before you commit or just automatically want to fix it there is a great tool (when you remove --dry-run the php-cs-fixer also fixes all violations):

```bash
composer install
./.Build/bin/php-cs-fixer fix -v --level=psr2 --dry-run Classes
```

### Namespacing and Package Structure

Since 3.1 we've switched to namespace and use "psr-4" standard for class loading. In a nutshell this means:

1. All classes in the "Classes" folder have the root namespace "ApacheSolrForTypo3\Solr"
2. The namespace below "ApacheSolrForTypo3\Solr" represents the directory structure and should be in upper camel case.
3. Make sure that the casing matches since the most server operating file systems are case sensitive.

### Manual testing

Changes should at least be tested manually to make sure the extension is still working and no new problems are
coming up.

### Automated tests

To speedup the development process and allow major refactoring and improvements in a reasonable time we want to improve our set of automated tests.
This also helps us to continuously integrate EXT:solr with the latest state of TYPO3 dev-master.

#### Unit Tests

In "Tests/Unit" you can find unit tests for some classes. It is our goal and highly appreciated when you add tests for your code or
contribute tests for existing code.

#### Integration Tests

To test the integration between different classes and components you can add integration tests that cover these components.
In our test setup we currently do the following:

1. We use the TYPO3 Core functionality for "Functional" tests that is setting up a dedicated test database and
can be used in the integration test.
2. We use our install script to setup a local solr server on the test system. By doing this, we can do "end-to-end" tests
and check if the whole "stack" is working.

The simplest way to use the testing framework is, to just enable "travis-ci.org" for your fork and run the tests on travis.
If you want to run the tests on your local system you can do the following steps

One time preparation:

```bash
cd <solrroot>
chmod u+x ./Tests/Build/*.sh
./Tests/Build/cleanup.sh
source ./Tests/Build/bootstrap.sh --local
```

Each time you want to run the test suite:

```bash
./Tests/Build/cibuild.sh
```

Make sure that the testsuite is runnig, before you do a pull request.

## Documentation

The documentation is now mainly on forge(https://forge.typo3.org/projects/extension-solr/wiki) and we are on the way to migrate it to
github (https://github.com/TYPO3-Solr/ext-solr/issues/20).

## Translations

### In Version 3.1.x

Until version 3.1.x the translation was done in the old TYPO3 Locallang XML format. Therese files are available on
http://translation.typo3.org

### After 3.1.x

We've migrated the translation files to the new Xliff format. They can be found in "Resources/Private/Language".
We appreciate any help here and it would be nice, when you could share your translations to complete the defaults here.

## Addons

EXT:solr provides a setup of hooks to extend it or you can use objects from EXT:solr in your extension and build
your custom functionality. On https://github.com/TYPO3-Solr/ we provide a few addons for the EXT:solr TYPO3 extension.

If you want to share your own addon and what to get it available on (https://github.com/TYPO3-Solr/) contact us!

## Support

### Community Support

The main support channel is our [slack chat] (https://typo3.slack.com/messages/ext-solr/). You can get support there from
other community members and also support other users with the problems they have.

### Support the further development

You can support the further development by becoming a partner. Check typo3-solr.com or call DKD (+49(0)69-24752180)

## Links

* http://www.dkd.de
* http://www.typo3-solr.com
* https://github.com/TYPO3-Solr
* https://forge.typo3.org/projects/extension-solr/wiki
* https://github.com/TYPO3-Solr/ext-solr/issues
