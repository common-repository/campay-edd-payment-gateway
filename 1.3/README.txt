=== CamPay EDD Payment Gateway ===
Contributors:gabinho12
Tags: payments, mobile money, MTN Money, Orange Money, EDD, WordPress
Requires EDD at least : 2.10.2
Requires at least: 5.7
Tested up to: 6.2
Requires PHP: 5.6
Stable tag: 1.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

CamPay is a Fintech service of the company TAKWID
GROUP which launched its financial services in Cameroon
from January 2021.

We provide businesses and institutions with solutions for
collecting and transferring money online, via primarily
Mobile Money(MTN and Orange). 

With CamPay, simplify the purchasing experience for
your customers thanks to our mobile money
payment solutions, accessible via your website
and/or mobile application.

= How it functions backend =
* Install CamPay Payment Gateway in your website with EDD activated
* Activate the plugin
* Go into EDD payment methods setting and activate CamPay Payment Gateway
* Set your App username and password (get it from https://campay.net/)
* Save your settings.

= How it function frontend =
* On Checkout page select CamPay Payment Gateway as your payment method.
* Input phone number to use for the payment (it must be a 9 digits valide MTN or Orange phone number)
* Click Command button
* On your mobile phone confirm payment 
* You will be automatically redirected if payment was successfull or receive a failure message in case payment failed.

== Installation ==

= Minimum Requirements =
* Easy Digital Downloads 2.10 or greater is recommended
* PHP 7.2 or greater is recommended
* MySQL 5.6 or greater is recommended

= Updating =
Automatic updates should work smoothly, but we still recommend you back up your site.

== Contributors & Developers ==
CamPay Payment Gateway REST API was develop by CamPay with INNO DS as contributor to develop the WordPress plugin for EDD

== Changelog ==

1.0 - 2021-05-25
*Dev - Development of the plugin
1.1 - 2021-10-28
*Dev - adding support of USD and EUR with user setting manually conversion rate in their dashboard
*Dev - Making errors more simple to understand
1.2 - 2022-09-19
*Dev - adding support of Credit Card Payments
1.3 - 2023-04-11
* Updating plugin to support WordPress 6.2
== Upgrade Notice ==
= 1.0 =
This version enables you to accept mobile money payments on your website
= 1.2 = 
This version enables you to accept credit card payments on your website