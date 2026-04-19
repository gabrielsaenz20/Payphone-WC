=== Payphone Payment Box Gateway ===
Contributors: Payphone
Tags: Woocommerce, Gateway Payment, Payphone
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.4
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Payphone Payment Box add a new payment gateway to collect payments for your WooCommerce products using PayPhone.

== Description ==
Payphone Payment Box adds a new payment gateway to charge woocommerce products through PayPhone. To be able to use this plugin you must first become a PayPhone Store, if you are not yet you can enter to [PayPhone](https://payphone.app)

This plugin get the total amount, taxes, shipping and order id, and sends them to PayPhone for payment processing. Once the payment is completed, the response is received and the corresponding order is updated.

== Installation ==

= Minimum Requirements =

* WordPress 6.0 or greater
* WooCommerce installed and activated

= Automatic installation =

Automatic installation is the easiest option, as WordPress handles the file transfers automatically, and you don’t need to leave your web browser. To automatically install the plugin, log in to your WordPress dashboard, navigate to **Plugins > Add New**, and search for "Payphone Payment Box Gateway". Once you find the plugin, click **Install Now** and then **Activate**.

= Manual installation =

If you prefer manual installation, download the plugin and upload it to your server via your favorite FTP client. For detailed instructions, refer to the [WordPress Codex on Manual Plugin Installation](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Configuration ==

You can access the plugin settings by going to Woocommerce > Settings > Payments > Payphone Payment Box

- **Enable/Disable**: Use this checkbox to enable or disable the payment gateway on the checkout page.
- **Token & Test Token**: Required to communicate with PayPhone. You can retrieve the credentials by visiting [this page](https://appdeveloper.payphonetodoesposible.com).
- **Store Id**: Optional, if you want to specify the store. You can retrieve the credentials by visiting [this page](https://appdeveloper.payphonetodoesposible.com).


== Frequently Asked Questions ==

= Does this plugin work with credit cards or just PayPhone? =

This plugin supports payments via **credit and debit cards**, as well as PayPhone.

= Does this support both production mode and sandbox mode for testing? =

Yes, the plugin supports both **production** and **sandbox** modes. You can choose the mode based on your credentials, and switch between them as needed. To obtain the correct credentials, visit [this page](https://appdeveloper.payphonetodoesposible.com).

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.payphone.app/)

= Where can I get support? =

If you need assistance, you can reach out via email at **info@payphone.app**.

== Changelog ==

= 1.0.4 2025-10-08 =
* Fix bugs (Add the diners and discover icon)

= 1.0.3 2025-09-15 =
* Fix bugs (Prevent page reloading after a payment processing error)
* Fix bugs (Display the navigation bar on the transaction response page)
* Fix bugs (Translation of missing texts)
* Fix bugs (Verify the correct loading of the payphone library)

= 1.0.2 2025-07-17 = 
* Fix bugs (Modify WooCommerce version validation)

= 1.0.1 2025-04-22 = 
* Add origin parameter for payment box

= 1.0.0 2024-06-13 = 
* Initial release.