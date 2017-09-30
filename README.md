# timey, a [Telegram](https://telegram.org) bot

Talk to this bot: [telegram.me/timeybot](https://telegram.me/timeybot).


## Deploying

### Deploy first time

#### Local requirements
- [composer](https://getcomposer.org)

#### Server requirements
- PHP 7
- HTTPS

#### Summary

Clone the repository, download the dependencies, create `config.json`, upload everything to your server.

Clone:

    git clone git@github.com:shoeffner/timey

Download dependencies:

    composer install

Creating `config.json`:

    cp config.json-example config.json

Edit `config.json` with your favorite text editor:

```json
{
    "github": {
        "api_token": "see https://github.com/settings/tokens",
        "owner": "shoeffner",
        "repository": "timeybot",
        "hook_UA": "Check the example hook request when creating a hook. https://github.com/<owner>/<repository>/settings/hooks"
    },
    "bot": {
        "api_key": "create using @BotFather",
        "username": "create using @BotFather"
    },
    "web": {
        "base_url": "https://timey.sebastian-hoeffner.de"
    }
}
```

Upload all files to your server.

#### Optional (recommended): Setting up webhook

Create a [Webhook](https://github.com/shoeffner/timeybot/settings/hooks)
pointing to your server's
[tools/gh_webhook_composer.lock-check.php](https://timey.sebastian-hoeffner.de/tools/gh_webhook_composer.lock-check.php).
This will perform dependency checks and allow for continuous deployment,
unless manual intervention is needed (see section about subsequent
deployments).


### Subsequent deployments

In case the dependencies changed, the checked in `composer.lock` should
differ from the server's. The webhook
`tools/gh_webhook_composer.lock-check.php` checks this for pull requests. In
case something changed, a server admin has to upload the new `composer.lock`
file, the new `vendor` directory, and fire up the check again.

Note that this might lead to some problems between the update of the bot's
source code and the vendor directory upload, but it seems like a good enough
solution for now.
