import template from './tilta-test-api-credentials-button.twig';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const {Component, Mixin} = Shopware;

Component.register('tilta-test-api-credentials-button', {
    template,

    inject: [
        'tiltaApiService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    props: {
        apiMode: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            isTestSuccessful: false
        };
    },

    methods: {

        onTestFinish() {
            this.isTestSuccessful = false;
        },

        testCredentials() {
            this.isTestSuccessful = false;
            this.isLoading = true;

            let merchantExternalId = document.querySelector(`[name="TiltaPaymentSW6.config.${this.apiMode}ApiMerchantExternalId"]`).value;
            let authToken = document.querySelector(`[name="TiltaPaymentSW6.config.${this.apiMode}ApiAuthToken"]`).value;
            let isSandbox = this.apiMode === 'sandbox';

            this.tiltaApiService.testCredentials(merchantExternalId, authToken, isSandbox).then((response) => {
                this.isLoading = false;

                if (response.success) {
                    this.isTestSuccessful = true;
                    this.createNotificationSuccess({
                        message: this.$tc('tilta.config.notification.validCredentials')
                    });
                } else {
                    if (response.code && this.$te('tilta.config.notification.' + response.code)) {
                        this.createNotificationError({
                            message: this.$tc('tilta.config.notification.' + response.code)
                        });
                    } else {
                        this.createNotificationError({
                            message: this.$tc('tilta.config.notification.invalidCredentials')
                        });
                    }
                }
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('tilta.config.notification.failedToTestCredentials')
                });
            });
        }
    }
});
