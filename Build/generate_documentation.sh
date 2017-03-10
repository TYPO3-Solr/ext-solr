#!/usr/bin/env bash

WORKING_DIR=`pwd`

function doInstallSphinxAndDependencies {
    sudo apt-get install -y python-pip texlive-base texlive-latex-recommended texlive-latex-extra texlive-fonts-recommended texlive-fonts-extra texlive-latex-base texlive-font-utils python-setuptools python-pygments python-sphinx xzdec make
    pip install --upgrade t3SphinxThemeRtd requests pygments sphinx setuptools imagesize pyyaml snowballstemmer t3fieldlisttable t3tablerows t3targets

    # set repo fix to 2015, newer does not work currently(2017.3)
    tlmgr option repository ftp://tug.org/historic/systems/texlive/2015/tlnet-final
    tlmgr init-usertree
    sudo tlmgr update --all
    sudo tlmgr install ec
    sudo tlmgr install cm-super
    # required fonts for PDF rendering
    sudo mkdir /usr/share/texmf/tex/latex/typo3
    wget https://raw.githubusercontent.com/TYPO3-Documentation/latex.typo3/7eaec1188da2c8c641d22433d07a4c46ca79a571/typo3.sty -O /tmp/typo3.sty
    wget https://raw.githubusercontent.com/TYPO3-Documentation/latex.typo3/7eaec1188da2c8c641d22433d07a4c46ca79a571/typo3_logo_color.png -O /tmp/typo3_logo_color.png
    sudo cp /tmp/typo3.sty /usr/share/texmf/tex/latex/typo3/.
    sudo cp /tmp/typo3_logo_color.png /usr/share/texmf/tex/latex/typo3/.
    # apply latex.typo3
    sudo texhash

    # apply font from TYPO3
    git clone git://git.typo3.org/Documentation/RestTools.git /tmp/RestTools
    cd /tmp/RestTools/LaTeX/font
    ./convert-share.sh


    cd $WORKING_DIR
}

if [ ! -f /usr/share/texmf/tex/latex/typo3/typo3.sty ] ; then
    echo "Sphinx & co. is not installed, proceed installation..."
    echo "This may take some time..."
    sleep 3
    doInstallSphinxAndDependencies
fi


if [ ! -d Documentation/_build/ ] ; then
    mkdir Documentation/_build/
elif [ -f Documentation/_build/latex/*.pdf ] ; then # Cleanup previous PDF builds
    rm -Rf Documentation/_build/{latex,latexpdf.output.log,warnings.txt}
fi

echo "Building latex, which is needed for pdf rendering."
echo "Please check output, which can contain a hints for the errors in your documents."
sleep 3
LANG=en_US.UTF-8
sphinx-build -b latex -c Documentation -d Documentation/_build/doctrees -w Documentation/_build/warnings.txt -D latex_paper_size=a4 Documentation Documentation/_build/latex


echo "Building PDF file. This may take some time..."
'/usr/bin/make' -C 'Documentation/_build/latex' clean all-pdf > Documentation/_build/latexpdf.output.log 2>&1


if [ -f Documentation/_build/latex/*.pdf ] ; then
    mv Documentation/_build/latex/*.pdf Documentation/_build/.
    echo "Done! You can find your PDF file inside Documentation/_build/"
else
    echo "Something has gone wrong! Please check the output above and following log files:"
    echo "    Documentation/_build/latexpdf.output.log"
    echo "    Documentation/_build/warnings.txt"
fi
