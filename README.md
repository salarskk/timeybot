# timey, a [Telegram](https://telegram.org) bot

Talk to this bot: [telegram.me/timeybot](https://telegram.me/timeybot).

Visit this bot: [timey.sebastian-hoeffner.de](https://timey.sebastian-hoeffner.de).


## Everyone loves badges

[![Build Status](https://semaphoreci.com/api/v1/projects/a9bbe7d9-31d8-413b-8861-63a84dd0a160/1547718/badge.svg)](https://semaphoreci.com/timey/timeybot)


## Test

Add an API key to [tests/config.test.json](tests/config.test.json) ([Google API Dashboard](https://console.developers.google.com/apis/dashboard)).
Then run:

    phpunit -c phpunit.xml

Be careful to not commit the config.test.json with your API Key!

Alternatively you can just push some commits to a pull requests, semaphore will build and test your branch then using its dedicated API key.
