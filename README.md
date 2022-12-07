# Kirby 3 UTM

![Release](https://flat.badgen.net/packagist/v/bnomei/kirby3-utm?color=ae81ff)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby3-utm?color=272822)
[![Build Status](https://flat.badgen.net/travis/bnomei/kirby3-utm)](https://travis-ci.com/bnomei/kirby3-utm)
[![Coverage Status](https://flat.badgen.net/coveralls/c/github/bnomei/kirby3-utm)](https://coveralls.io/github/bnomei/kirby3-utm)
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby3-utm)](https://codeclimate.com/github/bnomei/kirby3-utm)
[![Twitter](https://flat.badgen.net/badge/twitter/bnomei?color=66d9ef)](https://twitter.com/bnomei)

## Install

Using composer:

```bash
composer require bnomei/kirby3-utm
```

Using git submodules:

```bash
git submodule add https://github.com/bnomei/kirby3-utm.git site/plugins/kirby3-utm
```

Using download & copy: download [the latest release of this plugin](https://github.com/bnomei/kirby3-utm/releases) then unzip and copy it to `site/plugins`

## Commercial Usage

> <br>
> <b>Support open source!</b><br><br>
> This plugin is free but if you use it in a commercial project please consider to sponsor me or make a donation.<br>
> If my work helped you to make some cash it seems fair to me that I might get a little reward as well, right?<br><br>
> Be kind. Share a little. Thanks.<br><br>
> &dash; Bruno<br>
> &nbsp;

| M | O | N | E | Y |
|---|----|---|---|---|
| [Github sponsor](https://github.com/sponsors/bnomei) | [Patreon](https://patreon.com/bnomei) | [Buy Me a Coffee](https://buymeacoff.ee/bnomei) | [Paypal dontation](https://www.paypal.me/bnomei/15) | [Hire me](mailto:b@bnomei.com?subject=Kirby) |

## Usage

### UTM Page

Create a new page at root level with the blueprint `utm`.

![dashboard](https://raw.githubusercontent.com/bnomei/kirby3-utm/main/screenshot-utm-dashboard.png)
<br>
![campaign](https://raw.githubusercontent.com/bnomei/kirby3-utm/main/screenshot-utm-campaign.png)

### Tracking

Events will automatically be tracked to a sqlite database and appear in the UTM page within the panel grouped by campaign and displaying multiple stats.

## UTM

Original UTM explanation from [Bonnie Kittle at cdgi.com](https://www.cdgi.com/2020/04/how-to-use-utm-codes-to-track-campaigns-in-google-analytics/).

### utm_source

The advertiser, site, publication, etc. that is sending traffic to your property

### utm_medium

The advertising or marketing medium, for example, CPC (cost-per-click), banner ad, email newsletter

### utm_campaign

The individual campaign name, slogan, promo code, etc.

### utm_term

Identify paid search keywords. If you’re manually tagging paid keyword campaigns, you should also use utm_term to specify the keyword.

### utm_content

Used to differentiate similar content or links within the same ad. For example, if you have two call-to-action links within the same email message, you can use utm_content and set different values for each so you can tell which version is more effective. (i.e. image, button, headline)

## Settings

| bnomei.utm.        | Default                                                                                          | Description                                              |
|--------------------|--------------------------------------------------------------------------------------------------|----------------------------------------------------------|
| enabled            | `true`                                                                                           |                                                          |
| cache.ipstack      | `true`                                     | seperate cache for ip data, expires at `ipstack.expire`                                                          |
| cache.ratelimit    | `true`                                  | seperate cache for ratelimit, expires at `ratelimit.expire`                                                         |
| cache.queries      | `true`  | seperate cache for most queries used in panel, flushes automatically with each event tracked                                                         |
| ipstack.access_key | `null`                                                                                           | string. access key                                       |
| ipstack.https      | `false`                                                                                          | boolean. if `true` will use premium https endpoint.      |
| ipstack.expire     | `60*24`                                                                                          | int. cache in minutes for ipstack IP resolution.         |
| sqlite.file        | `fn()`                                                                                           | path to sqlite file. like site/logs                      |
| stats.range        | `30`                                                                                             | int. half of range of days for bar and change percentage |
| ratelimit.expire   | `60`                                                                                             | int. in minutes before trials reset                      |
| ratelimit.trials   | `120`                                                                                            | int. number of allowed trials in given duration          |


## Dependencies

- (optional) [free ipstack account for IP geolocations](https://ipstack.com/)

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-utm/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.

