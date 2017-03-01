# Chrome Driver packaged in Composer

This is the composer dependency you want if you happen to do some Behat testing with Selenium.

Use it like a regular composer dependency, although it's a composer plugin.

    composer require lbaey/chromedriver
    
Feel free to contribute, PR's are read and welcome

If you use se/selenium-server-standalone, run it with

    ./bin/selenium-server-standalone -Dwebdriver.chrome.driver=$PWD/bin/chromedriver
