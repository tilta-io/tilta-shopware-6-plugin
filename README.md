Tilta Fintech GmbH - Shopware 6 payment module
============================================

| Module | Tilta as payment method for Shopware 6                  |
|--------|---------------------------------------------------------|
| Author | Tilta Fintech GmbH, [WEBiDEA](https://www.webidea24.de) |
| Link   | [https://www.tilta.io](https://www.tilta.io)            |
| Mail   | [support@tilta.io](mailto:support@tilta.io)             |

This extension provides the integration of the payment provider "Tilta" in Shopware 6.

## Introduction about Tilta

Tilta offers a white-labeled payment infrastructure, enabling B2B eCommerce shops to offer various payment options under
their own brand.

With Tilta, you can configure your own framework for payments. Decide which payment methods you want to offer, from
pay-now options like direct debit & direct transfer over pay-later options like invoice payment (7-120 days due date) or
installments (up to 180 days).

The relationship with your buyers is crucial for your success, therefore Tilta doesn’t want to intervene in the buyer's
journey.

Every payment method is white-labeled and leads to an end-to-end buyer journey.

As Tilta positions itself as infrastructure, you have full control over the payment methods.

You can choose which buyers can select which payment methods, how long the due dates for invoice purchases are, or which
fee buyers need to pay for the service.

Buyers love Tilta’s pre-approved financing facility, as it gives additional security and assurance during procurement.

As of today, Tilta can provide financing facilities of up to 250.000 € per buyer.
This limit can be provided with the help of Tilta’s new underwriting process, which not only includes financial
information but also the customer’s behavior on your platform.

## Installation

We highly recommend installing the extension via Composer to ensure that you're loading the precise version tailored to
your Shopware setup.

If Composer is unfamiliar territory for you or if you lack the assistance of a developer/agency, you can easily install
the extension by uploading the release package.

### Installation via Composer (recommend)

Load the extension by executing the following command in the root of your project.

```
composer require tilta/shopware6-payment-module
```

Update the list of extensions by executing the following command in the root of your project.

```
./bin/console plugin:refresh
```

Install the extension by executing the following command in the root of your project.

```
./bin/console plugin:install TiltaPaymentSW6 --activate
```

#### Update the extension via Composer (recommend)

Update the package by executing the following command in the root of your project.

```
composer require tilta/shopware6-payment-module
```

Update the extension by executing the following command in the root of your project.

```
./bin/console plugin:update TiltaPaymentSW6
```

### Installation via download of release

1. Open the [release page](https://github.com/tilta-io/tilta-shopware-6-plugin/releases) in GitHub
2. Pick the release which is compatible with your installed Shopware version
3. Have a look into the assets-panel and click (and download) the file, which matches the pattern
   TiltaPaymentSW6-x.x.x.zip
4. Upload this package in the administration (Extensions > My extensions > Upload extensions)
5. Maybe you need to confirm that this extension is not from the Shopware store
6. Click on "Install" of the uploaded extension
7. Toggle the Activate/Deactivate-Toggle of the uploaded extension

#### Update the extension via download of release

Just start over again with the installation

At least you have to click on the "Upgrade" Link in your extension list within the administration.

### Configuration

1. Open the extension configuration (three dots)
2. Enter the API credentials, which you got from Tilta
3. Test them by clicking the button "Test credentials"
4. Configure the mapping of salutations. The API always expects one of "Mrs." or "Mr." If you have custom salutations,
   please map them to one of them
5. For automatic informing Tilta about state changes, enable the "Notification about state changes" (recommend)
6. Save the extension configuration
7. Open the sales-channel in which you want to use the payment method
8. Add the payment method to the available payment methods

If you plan to use the Plugin for testing purposes, make sure you enable it by setting the Sandbox toggle to true and
make sure you have filled the Sandbox inputs.

Now you are ready to accept payments with Tilta.

**Please note:**
We always advise installing extensions within a test environment to ensure that the production instance remains unaffected by the installation process. Please thoroughly test the extension before deploying it in a production environment. 

